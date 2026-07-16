<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;

return [
    'endpoint' => 'POST /api/v1/learning/lessons',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            // O instructor do harness precisa ser dono do curso: create-check
            // (LessonPolicy) só autoriza aula em módulo de curso próprio.
            'instructor_id' => $ctx['users']['instructor']->id,
            'title' => 'Curso Aula E2E',
            'slug' => 'curso-aula-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $module = CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $course->id,
            'title' => 'Módulo Aula E2E',
            'sort_order' => 1,
        ]);

        Lesson::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_module_id' => $module->id,
            'title' => 'Aula Existente E2E',
            'slug' => 'aula-existente-e2e',
            'status' => 'draft',
            'sort_order' => 1,
            'is_free' => true,
            'is_active' => true,
        ]);

        $otherCourse = Course::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'title' => 'Curso Outro Aula E2E',
            'slug' => 'curso-outro-aula-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $otherModule = CourseModule::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'course_id' => $otherCourse->id,
            'title' => 'Módulo Outro Aula E2E',
            'sort_order' => 1,
        ]);

        return compact('module', 'otherModule');
    },

    'cases' => [
        [
            'name' => 'instructor cria aula com defaults e ordem no fim',
            'as' => 'instructor',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['module']->id,
                'title' => 'Nova Aula E2E',
            ],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.title' => 'Nova Aula E2E',
                    'data.sort_order' => 2,
                    'data.is_free' => false,
                ],
            ],
            'db' => function (array $ctx): array {
                $lesson = Lesson::query()
                    ->where('tenant_id', $ctx['tenant']->id)
                    ->where('title', 'Nova Aula E2E')
                    ->latest('id')
                    ->first();

                return [
                    'aula existe' => [true, $lesson !== null],
                    'slug derivado' => ['nova-aula-e2e', $lesson?->slug],
                    'status draft' => ['draft', $lesson?->status],
                    'course_module correto' => [$ctx['fixtures']['module']->id, $lesson?->course_module_id],
                ];
            },
        ],
        [
            'name' => 'student proibido',
            'as' => 'student',
            'body' => ['course_module_id' => fn (array $ctx): int => $ctx['fixtures']['module']->id, 'title' => 'Aula Proibida E2E'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
        [
            'name' => 'sem auth → 401',
            'body' => ['course_module_id' => fn (array $ctx): int => $ctx['fixtures']['module']->id, 'title' => 'Aula Sem Auth E2E'],
            'expect' => ['status' => 401],
        ],
        [
            'name' => 'módulo de outro tenant → 422',
            'as' => 'instructor',
            'body' => ['course_module_id' => fn (array $ctx): int => $ctx['fixtures']['otherModule']->id, 'title' => 'Aula Cross Tenant E2E'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Lesson::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->delete();
        CourseModule::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->delete();
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
