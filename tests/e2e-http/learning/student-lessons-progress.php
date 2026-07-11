<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;

return [
    'endpoint' => 'POST /api/v1/learning/lessons/{id}/progress',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Progress E2E',
            'slug' => 'curso-progress-e2e',
            'description' => 'Fluxo progresso',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $module = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $course->id,
            'title' => 'Módulo Progress E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $lesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $module->id,
            'title' => 'Aula Progress E2E',
            'slug' => 'aula-progress-e2e',
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);

        $enrollment = Enrollment::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'progress_percentage' => 0,
        ]);

        $expiredCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Progress Expirado E2E',
            'slug' => 'curso-progress-expirado-e2e',
            'description' => 'Fluxo progresso expirado',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $expiredModule = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $expiredCourse->id,
            'title' => 'Módulo Progress Expirado E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $expiredLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $expiredModule->id,
            'title' => 'Aula Progress Bloqueada E2E',
            'slug' => 'aula-progress-bloqueada-e2e',
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);

        Enrollment::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $expiredCourse->id,
            'status' => 'expired',
            'enrolled_at' => now()->subDays(40),
            'access_expires_at' => now()->subDay(),
            'progress_percentage' => 80,
        ]);

        return compact('course', 'lesson', 'enrollment', 'expiredLesson');
    },

    'cases' => [
        [
            'name' => 'aluno matriculado atualiza progresso e conclui aula',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['lesson']->id.'/progress',
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
                $progress = LessonProgress::query()
                    ->where('tenant_id', $ctx['tenant']->id)
                    ->where('lesson_id', $ctx['fixtures']['lesson']->id)
                    ->where('enrollment_id', $ctx['fixtures']['enrollment']->id)
                    ->first();

                return [
                    'progress criado' => [true, $progress !== null],
                    'enrollment progress atualizado' => [100, $ctx['fixtures']['enrollment']->refresh()->progress_percentage],
                ];
            },
        ],
        [
            'name' => 'matrícula expirada não atualiza progresso',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['expiredLesson']->id.'/progress',
            'body' => [
                'time_spent_seconds' => 120,
                'current_time_seconds' => 60,
                'total_time_seconds' => 300,
                'progress_percentage' => 20,
                'is_completed' => false,
            ],
            'expect' => ['status' => 403],
        ],
        [
            'name' => 'payload inválido → 422',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['lesson']->id.'/progress',
            'body' => ['time_spent_seconds' => 10],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['lesson']->id.'/progress',
            'body' => ['time_spent_seconds' => 10, 'is_completed' => false],
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        LessonProgress::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Course::query()->where('tenant_id', $ctx['tenant']->id)->forceDelete();
    },
];
