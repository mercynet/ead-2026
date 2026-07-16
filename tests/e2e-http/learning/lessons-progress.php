<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Certificate;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;

/**
 * Spec E2E — POST /api/v1/learning/lessons/{id}/progress (progresso + emissão de certificado).
 *
 * Arquivo:    tests/e2e-http/learning/lessons-progress.php
 * Roda com:   php artisan e2e:run learning/lessons-progress
 *
 * Cobre o side effect cross-module: transição do enrollment para 100% dispara
 * CourseCompletedEvent (Learning) → IssueCertificateAction (Assessment), honrando
 * as colunas certificate_* do curso.
 */
return [
    'endpoint' => 'POST /api/v1/learning/lessons/{id}/progress',

    'setup' => function (array $ctx): array {
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        /** @var User $student */
        $student = $ctx['users']['student'];

        $makeEnrolledCourse = function (array $courseAttributes) use ($tenant, $student): array {
            $course = Course::factory()->for($tenant)->create(array_merge([
                'certificate_enabled' => true,
                'certificate_min_progress' => 100,
                'certificate_requires_quiz' => false,
                'certificate_min_score' => 70,
            ], $courseAttributes));

            $module = CourseModule::factory()->for($tenant)->for($course)->create();
            $lesson = Lesson::factory()->for($tenant)->for($module)->create();

            $enrollment = Enrollment::factory()
                ->for($tenant)
                ->for($student)
                ->for($course)
                ->active()
                ->create();

            return ['course' => $course, 'module' => $module, 'lesson' => $lesson, 'enrollment' => $enrollment];
        };

        $makeCourseQuestionnaire = fn (array $fx): Questionnaire => Questionnaire::factory()
            ->for($tenant)
            ->course()
            ->create([
                'quizable_type' => $fx['course']->getMorphClass(),
                'quizable_id' => $fx['course']->id,
            ]);

        $cert = $makeEnrolledCourse([]);
        $disabled = $makeEnrolledCourse(['certificate_enabled' => false]);

        $quizNoPass = $makeEnrolledCourse(['certificate_requires_quiz' => true]);
        $makeCourseQuestionnaire($quizNoPass);

        $quizPass = $makeEnrolledCourse(['certificate_requires_quiz' => true]);
        $questionnaire = $makeCourseQuestionnaire($quizPass);
        QuizAttempt::factory()
            ->for($tenant)
            ->for($student)
            ->for($questionnaire)
            ->completed()
            ->create(['score' => 85, 'passed' => true]);

        $partial = $makeEnrolledCourse([]);
        Lesson::factory()->for($tenant)->for($partial['module'])->create();

        $noEnroll = $makeEnrolledCourse([]);
        $noEnroll['enrollment']->delete();

        $foreignCourse = Course::factory()->for($ctx['otherTenant'])->create();
        $foreignModule = CourseModule::factory()->for($ctx['otherTenant'])->for($foreignCourse)->create();
        $foreignLesson = Lesson::factory()->for($ctx['otherTenant'])->for($foreignModule)->create();

        return [
            'cert' => $cert,
            'disabled' => $disabled,
            'quizNoPass' => $quizNoPass,
            'quizPass' => $quizPass,
            'partial' => $partial,
            'noEnroll' => $noEnroll,
            'foreignLesson' => $foreignLesson,
        ];
    },

    'cases' => [
        [
            'name' => 'happy path: completar única aula fecha curso e emite certificado',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['cert']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.progress_percentage' => 100,
                    'data.is_completed' => true,
                ],
            ],
            'db' => function (array $ctx): array {
                $enrollment = $ctx['fixtures']['cert']['enrollment']->fresh();
                $certificate = Certificate::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->first();

                return [
                    'enrollment progress = 100' => [100, (int) $enrollment->progress_percentage],
                    'enrollment completed_at estampado' => [true, $enrollment->completed_at !== null],
                    'certificado emitido' => [true, $certificate !== null],
                    'certificado status issued' => ['issued', $certificate?->status],
                    'certificado tenant do contexto' => [$ctx['tenant']->id, $certificate?->tenant_id],
                    'certificado do aluno autenticado' => [$ctx['users']['student']->id, $certificate?->user_id],
                    'certificado aponta o curso' => [$ctx['fixtures']['cert']['course']->id, $certificate?->course_id],
                    'número no formato CERT-ANO-HEX8' => [1, preg_match('/^CERT-\d{4}-[0-9A-F]{8}$/', (string) $certificate?->certificate_number)],
                    'issued_at preenchido' => [true, $certificate?->issued_at !== null],
                ];
            },
        ],

        [
            'name' => 'repetir o report de 100% não duplica certificado (idempotente)',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['cert']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 400,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => ['status' => 200],
            'db' => fn (array $ctx): array => [
                'segue exatamente 1 certificado' => [1, Certificate::query()
                    ->where('enrollment_id', $ctx['fixtures']['cert']['enrollment']->id)
                    ->count()],
            ],
        ],

        [
            'name' => 'certificate_enabled=false: completa curso sem emitir certificado',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['disabled']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => ['status' => 201],
            'db' => fn (array $ctx): array => [
                'enrollment fechou em 100' => [100, (int) $ctx['fixtures']['disabled']['enrollment']->fresh()->progress_percentage],
                'nenhum certificado' => [0, Certificate::query()
                    ->where('enrollment_id', $ctx['fixtures']['disabled']['enrollment']->id)
                    ->count()],
            ],
        ],

        [
            'name' => 'requires_quiz sem tentativa aprovada: sem certificado',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['quizNoPass']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => ['status' => 201],
            'db' => fn (array $ctx): array => [
                'nenhum certificado' => [0, Certificate::query()
                    ->where('enrollment_id', $ctx['fixtures']['quizNoPass']['enrollment']->id)
                    ->count()],
            ],
        ],

        [
            'name' => 'requires_quiz com tentativa aprovada (score ≥ min): emite certificado',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['quizPass']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => ['status' => 201],
            'db' => fn (array $ctx): array => [
                'certificado emitido' => [1, Certificate::query()
                    ->where('enrollment_id', $ctx['fixtures']['quizPass']['enrollment']->id)
                    ->where('status', 'issued')
                    ->count()],
            ],
        ],

        [
            'name' => 'curso com 2 aulas: completar 1 deixa 50%, sem certificado',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['partial']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ],
            'expect' => ['status' => 201, 'json' => ['data.is_completed' => true]],
            'db' => function (array $ctx): array {
                $enrollment = $ctx['fixtures']['partial']['enrollment']->fresh();

                return [
                    'enrollment em 50%' => [50, (int) $enrollment->progress_percentage],
                    'completed_at ainda vazio' => [null, $enrollment->completed_at],
                    'nenhum certificado' => [0, Certificate::query()
                        ->where('enrollment_id', $enrollment->id)
                        ->count()],
                ];
            },
        ],

        [
            // Contrato fixado (2026-07-16): sem matrícula, o heartbeat de progresso
            // devolve 404 not_found (UpdateProgressAction resolve o Enrollment com
            // firstOrFail). Semântica escolhida: ausência de matrícula = recurso de
            // progresso inexistente (também reduz enumeração), não 403.
            'name' => 'student sem enrollment na aula → 404',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['noEnroll']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 10,
                'is_completed' => false,
            ],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],

        [
            'name' => 'admin não tem learning.progress.update → 403',
            'as' => 'admin',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['cert']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 10,
                'is_completed' => false,
            ],
            'expect' => ['status' => 403],
        ],

        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['cert']['lesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 10,
                'is_completed' => false,
            ],
            'expect' => ['status' => 401],
        ],

        [
            'name' => 'payload sem campos obrigatórios → 422',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['cert']['lesson']->id}/progress",
            'body' => ['progress_percentage' => 50],
            'expect' => ['status' => 422],
        ],

        [
            'name' => 'isolamento: aula de outro tenant não resolve → 404',
            'as' => 'student',
            'path' => fn (array $ctx): string => "/api/v1/learning/lessons/{$ctx['fixtures']['foreignLesson']->id}/progress",
            'body' => [
                'time_spent_seconds' => 10,
                'is_completed' => false,
            ],
            'expect' => ['status' => 404],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $tenantIds = [$ctx['tenant']->id, $ctx['otherTenant']->id];

        Certificate::query()->whereIn('tenant_id', $tenantIds)->delete();
        QuizAttempt::query()->whereIn('tenant_id', $tenantIds)->delete();
        Questionnaire::query()->whereIn('tenant_id', $tenantIds)->delete();
        LessonProgress::query()->whereIn('tenant_id', $tenantIds)->delete();
        Enrollment::query()->whereIn('tenant_id', $tenantIds)->delete();
        Course::query()->whereIn('tenant_id', $tenantIds)->forceDelete();
    },
];
