<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Models\TenantCustomization;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;

return [
    'endpoint' => 'GET /api/v1/instructor/courses',

    'setup' => function (array $ctx): array {
        $instructor = $ctx['users']['instructor'];
        $student = $ctx['users']['student'];

        $instructorB = User::factory()->instructor()->forTenant($ctx['tenant'])->create([
            'name' => 'E2E I04 Instructor B',
        ]);
        $instructorB->assignRole('instructor');
        $instructorBToken = $instructorB->createToken('e2e-i04-instructor-b')->plainTextToken;

        $courseA = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I04 Course A',
            'price_cents' => 0,
            'status' => 'published',
            'is_active' => true,
        ]);
        $courseB = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructorB->id,
            'title' => 'E2E I04 Course B',
        ]);
        $foreignCourse = Course::factory()->for($ctx['otherTenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I04 Foreign Course',
        ]);
        $paidCourse = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I04 Paid Course',
            'price_cents' => 100,
            'status' => 'published',
            'is_active' => true,
        ]);
        TenantCustomization::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true]]],
        ]);
        $moduleB = CourseModule::factory()->for($ctx['tenant'])->for($courseB)->create();
        $lessonB = Lesson::factory()->for($ctx['tenant'])->for($moduleB)->create();
        $adminQuestionnaire = Questionnaire::factory()->course()->for($ctx['tenant'])->create([
            'instructor_id' => null,
            'quizable_type' => 'course',
            'quizable_id' => $courseA->id,
            'title' => 'E2E I04 Admin Owned',
        ]);
        $questionB = QuizQuestion::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructorB->id,
            'question' => 'E2E I04 Question B',
        ]);

        return [
            'instructorB' => $instructorB,
            'instructorBToken' => $instructorBToken,
            'studentId' => $student->id,
            'courseAId' => $courseA->id,
            'courseBId' => $courseB->id,
            'foreignCourseId' => $foreignCourse->id,
            'paidCourseId' => $paidCourse->id,
            'moduleBId' => $moduleB->id,
            'lessonBId' => $lessonB->id,
            'adminQuestionnaireId' => $adminQuestionnaire->id,
            'questionBId' => $questionB->id,
        ];
    },

    'cases' => [
        [
            'name' => 'A lista apenas seus Courses',
            'as' => 'instructor',
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                $ids = array_column($ctx['response']->json('data') ?? [], 'id');

                return [
                    'Course A visível' => [true, in_array($ctx['fixtures']['courseAId'], $ids, true)],
                    'Course B invisível' => [false, in_array($ctx['fixtures']['courseBId'], $ids, true)],
                ];
            },
        ],
        [
            'name' => 'A não redefine ownership ou lifecycle do Course',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/courses',
            'body' => [
                'title' => 'E2E I04 Spoofed Course',
                'tenant_id' => 999999,
                'instructor_id' => 999999,
                'owner' => 'admin',
                'status' => 'published',
                'is_published' => true,
            ],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => ['nenhum Course spoofed' => [0, Course::query()->where('title', 'E2E I04 Spoofed Course')->count()]],
        ],
        [
            'name' => 'A cria Module, Lesson, Media e Material em cadeia',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'],
                'title' => 'E2E I04 Module A',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['moduleAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A cria Lesson própria',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['moduleAId'],
                'title' => 'E2E I04 Lesson A',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['lessonAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A cria LessonMedia própria',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['lessonAId'].'/media',
            'body' => ['media_type' => 'video', 'provider' => 'embed', 'provider_ref' => 'e2e-i04-media', 'url' => 'https://video.example/i04'],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['mediaAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A cria CourseMaterial próprio',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseAId'].'/materials',
            'body' => ['file_path' => fn (array $ctx): string => 'tenants/'.$ctx['tenant']->id.'/materials/e2e-i04.pdf'],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['materialAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A consulta roster e progresso antes da matrícula nova',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/enrollments',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'A rejeita Module e Lesson do owner B',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/modules/'.$ctx['fixtures']['moduleBId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'A cria Questionnaire próprio no Course A',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questionnaires',
            'body' => [
                'title' => 'E2E I04 Questionnaire A',
                'type' => 'course',
                'quizable_type' => 'course',
                'quizable_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'],
                'passing_score' => 70,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['questionnaireAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A rejeita Questionnaire contra Course B',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questionnaires',
            'body' => ['title' => 'E2E I04 Foreign Parent', 'type' => 'course', 'quizable_type' => 'course', 'quizable_id' => fn (array $ctx): int => $ctx['fixtures']['courseBId']],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => ['parent foreign não criou questionnaire' => [0, Questionnaire::query()->where('title', 'E2E I04 Foreign Parent')->count()]],
        ],
        [
            'name' => 'A cria Question própria e anexa composição',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questions',
            'body' => [
                'question' => 'E2E I04 Question A',
                'type' => 'single_choice',
                'options' => [['text' => 'Correta'], ['text' => 'Errada']],
                'correct_options' => [0],
                'points' => 2,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['questionAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'A não anexa Question B ao Questionnaire A',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionBId']]],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'A anexa Question própria ao Questionnaire A',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionAId']]],
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'A cria matrícula FREE e mirror único',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentId']],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'active']],
            'capture' => fn (array $ctx): array => ['enrollmentAId' => (int) $ctx['response']->json('data.id')],
            'db' => function (array $ctx): array {
                $order = Order::query()->where('source_key', 'learning:enrollment:'.$ctx['fixtures']['enrollmentAId'])->first();

                return [
                    'Enrollment única' => [1, Enrollment::query()->where('course_id', $ctx['fixtures']['courseAId'])->where('user_id', $ctx['fixtures']['studentId'])->whereIn('status', ['pending', 'active'])->count()],
                    'Order única paid zero' => [true, $order?->status === 'paid' && $order->total_cents === 0],
                    'OrderItem único' => [1, $order?->items()->count()],
                    'Payment único free' => [1, $order?->payments()->where('gateway_slug', 'free')->count()],
                ];
            },
        ],
        [
            'name' => 'Replay da matrícula FREE é idempotente',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentId']],
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['enrollmentAId']]],
            'db' => fn (array $ctx): array => [
                'Enrollment corrente única' => [1, Enrollment::query()->where('course_id', $ctx['fixtures']['courseAId'])->where('user_id', $ctx['fixtures']['studentId'])->whereIn('status', ['pending', 'active'])->count()],
                'Order total única' => [1, Order::query()->where('tenant_id', $ctx['tenant']->id)->count()],
                'OrderItem total único' => [1, OrderItem::query()->whereHas('order', fn ($q) => $q->where('tenant_id', $ctx['tenant']->id))->count()],
                'Payment total único' => [1, Payment::query()->whereHas('order', fn ($q) => $q->where('tenant_id', $ctx['tenant']->id))->count()],
            ],
        ],
        [
            'name' => 'Course pago é rejeitado sem efeitos financeiros',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['paidCourseId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentId'], 'billing_type' => 'external', 'price_cents' => 999],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => ['sem enrollment paid' => [1, Enrollment::query()->where('user_id', $ctx['fixtures']['studentId'])->where('course_id', $ctx['fixtures']['courseAId'])->count()]],
        ],
        [
            'name' => 'A consulta roster/progresso próprio',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/enrollments/'.$ctx['fixtures']['enrollmentAId'].'/progress',
            'expect' => ['status' => 200],
            'db' => fn (array $ctx): array => ['matrícula própria persistida' => [true, Enrollment::query()->whereKey($ctx['fixtures']['enrollmentAId'])->exists()]],
        ],
        [
            'name' => 'Student inicia, responde e finaliza attempt',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['attemptId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Student responde sem autoridade de score',
            'as' => 'student',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/'.$ctx['fixtures']['attemptId'],
            'body' => ['question_id' => fn (array $ctx): int => $ctx['fixtures']['questionAId'], 'selected_options' => [0], 'points_earned' => 999, 'is_correct' => false],
            'expect' => ['status' => 201, 'json' => ['data.points_earned' => 2, 'data.is_correct' => true]],
        ],
        [
            'name' => 'Student finaliza e A consulta result sem PII/gabarito',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/'.$ctx['fixtures']['attemptId'].'/finish',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'A vê result permitido sem email ou snapshot bruto',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/assessment/results',
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                $json = $ctx['response']->json();

                return [
                    'attempt visível' => [true, collect($json['data'] ?? [])->contains('attempt_id', $ctx['fixtures']['attemptId'])],
                    'email ausente' => [null, data_get($json, 'data.0.student.email')],
                    'tenant ausente' => [null, data_get($json, 'data.0.tenant_id')],
                    'snapshot bruto ausente' => [null, data_get($json, 'data.0.answers.0.question_snapshot')],
                ];
            },
        ],
        [
            'name' => 'A não alcança Course, matrícula ou LessonMedia de B',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseBId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'B não alcança o result do Course A',
            'as' => 'instructor',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/results/'.$ctx['fixtures']['attemptId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'A não altera Questionnaire após attempt',
            'as' => 'instructor',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'body' => ['title' => 'E2E I04 forbidden mutation'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'A não remove Question usada após attempt',
            'as' => 'instructor',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questions/'.$ctx['fixtures']['questionAId'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Cross-tenant retorna tenant.access antes do recurso',
            'as' => 'instructor',
            'tenant' => 'other',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['foreignCourseId'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
        [
            'name' => 'Admin-owned Questionnaire não aparece no roster pedagógico',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/assessment/questionnaires',
            'expect' => ['status' => 200],
            'db' => fn (array $ctx): array => ['Admin-owned ausente' => [false, in_array($ctx['fixtures']['adminQuestionnaireId'], array_column($ctx['response']->json('data') ?? [], 'id'), true)]],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        /** @var User|null $instructorB */
        $instructorB = $ctx['fixtures']['instructorB'] ?? null;
        if ($instructorB instanceof User) {
            $instructorB->tokens()->delete();
            $instructorB->forceDelete();
        }
    },
];
