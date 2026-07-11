<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;

return [
    'endpoint' => 'GET /api/v1/learning/courses/{id}/modules',

    'setup' => function (array $ctx): array {
        $activeCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Aluno E2E',
            'slug' => 'curso-aluno-e2e',
            'description' => 'Fluxo aluno',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $activeModule = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $activeCourse->id,
            'title' => 'Módulo E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $paidLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $activeModule->id,
            'title' => 'Aula Paga E2E',
            'slug' => 'aula-paga-e2e',
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);

        $freeLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $activeModule->id,
            'title' => 'Aula Preview E2E',
            'slug' => 'aula-preview-e2e',
            'status' => 'published',
            'sort_order' => 2,
            'is_free' => true,
            'is_active' => true,
        ]);

        $activeEnrollment = Enrollment::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $activeCourse->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'progress_percentage' => 0,
        ]);

        $expiredCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Expirado E2E',
            'slug' => 'curso-expirado-e2e',
            'description' => 'Fluxo aluno expirado',
            'status' => 'published',
            'price_cents' => 9900,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $expiredModule = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $expiredCourse->id,
            'title' => 'Módulo Expirado E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $expiredLesson = Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $expiredModule->id,
            'title' => 'Aula Expirada E2E',
            'slug' => 'aula-expirada-e2e',
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);

        $expiredEnrollment = Enrollment::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $expiredCourse->id,
            'status' => 'expired',
            'enrolled_at' => now()->subDays(40),
            'access_expires_at' => now()->subDay(),
            'progress_percentage' => 80,
        ]);

        LessonProgress::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $expiredCourse->id,
            'enrollment_id' => $expiredEnrollment->id,
            'lesson_id' => $expiredLesson->id,
            'is_completed' => true,
            'progress_percentage' => 100,
            'time_spent_seconds' => 300,
            'current_time_seconds' => 300,
            'total_time_seconds' => 300,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);

        return compact('activeCourse', 'paidLesson', 'freeLesson', 'activeEnrollment', 'expiredCourse', 'expiredLesson');
    },

    'cases' => [
        [
            'name' => 'aluno matriculado vê aulas pagas e preview acessíveis',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['activeCourse']->id.'/modules',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.lessons.0.title' => 'Aula Paga E2E',
                    'data.0.lessons.0.can_access' => true,
                    'data.0.lessons.0.can_access_paid_content' => true,
                    'data.0.lessons.1.title' => 'Aula Preview E2E',
                    'data.0.lessons.1.can_access' => true,
                    'data.0.lessons.1.can_access_paid_content' => true,
                ],
            ],
        ],
        [
            'name' => 'matrícula expirada vê vitrine sem progresso nem conteúdo pago',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['expiredCourse']->id.'/modules',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.lessons.0.can_access' => false,
                    'data.0.lessons.0.can_access_paid_content' => false,
                    'data.0.lessons.0.progress' => null,
                ],
            ],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['activeCourse']->id.'/modules',
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        LessonProgress::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->delete();
        Course::query()->where('tenant_id', $ctx['tenant']->id)->forceDelete();
    },
];
