<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;

return [
    'endpoint' => 'GET /api/v1/learning/lessons/{id}',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Lesson Show E2E',
            'slug' => 'curso-lesson-show-e2e',
            'description' => 'Fluxo aula',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $module = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $course->id,
            'title' => 'Módulo Lesson Show E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $paidLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $module->id,
            'title' => 'Aula Liberada E2E',
            'slug' => 'aula-liberada-e2e',
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);

        $freeLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $module->id,
            'title' => 'Aula Degustação E2E',
            'slug' => 'aula-degustacao-e2e',
            'status' => 'published',
            'sort_order' => 2,
            'is_free' => true,
            'is_active' => true,
        ]);

        Enrollment::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'progress_percentage' => 0,
        ]);

        LessonMedia::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'lesson_id' => $paidLesson->id,
            'media_type' => 'video',
            'provider' => 'embed',
            'provider_ref' => 'lesson-show-active',
            'url' => 'https://video.example/lesson-show-active',
            'duration_seconds' => 300,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $expiredCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Lesson Expirado E2E',
            'slug' => 'curso-lesson-expirado-e2e',
            'description' => 'Fluxo aula expirada',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $expiredModule = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $expiredCourse->id,
            'title' => 'Módulo Lesson Expirado E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $expiredLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $expiredModule->id,
            'title' => 'Aula Bloqueada E2E',
            'slug' => 'aula-bloqueada-e2e',
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

        LessonMedia::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'lesson_id' => $expiredLesson->id,
            'media_type' => 'video',
            'provider' => 'embed',
            'provider_ref' => 'lesson-show-expired',
            'url' => 'https://video.example/lesson-show-expired',
            'duration_seconds' => 300,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return compact('paidLesson', 'freeLesson', 'expiredLesson');
    },

    'cases' => [
        [
            'name' => 'aluno matriculado acessa aula paga',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['paidLesson']->id,
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.title' => 'Aula Liberada E2E',
                    'data.can_access' => true,
                    'data.media.0.provider_ref' => 'lesson-show-active',
                    'data.media.0.url' => 'https://video.example/lesson-show-active',
                    'data.progress.is_completed' => false,
                ],
            ],
        ],
        [
            'name' => 'aula degustação fica acessível',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['freeLesson']->id,
            'expect' => ['status' => 200, 'json' => ['data.can_access' => true, 'data.is_free' => true]],
        ],
        [
            'name' => 'matrícula expirada vê aula sem acesso ao conteúdo',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['expiredLesson']->id,
            'expect' => ['status' => 200, 'json' => ['data.can_access' => false, 'data.media' => null, 'data.progress' => null]],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/learning/lessons/'.$ctx['fixtures']['paidLesson']->id,
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        LessonMedia::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        LessonProgress::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Course::query()->where('tenant_id', $ctx['tenant']->id)->forceDelete();
    },
];
