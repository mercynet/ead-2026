<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;

return [
    'endpoint' => 'POST /api/v1/learning/modules',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            // O instructor do harness precisa ser dono do curso: create-check
            // (CourseModulePolicy) só autoriza módulo em curso próprio.
            'instructor_id' => $ctx['users']['instructor']->id,
            'title' => 'Curso Modulo E2E',
            'slug' => 'curso-modulo-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        CourseModule::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'course_id' => $course->id,
            'title' => 'Módulo Existente E2E',
            'sort_order' => 1,
        ]);

        $otherCourse = Course::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'title' => 'Curso Outro Tenant E2E',
            'slug' => 'curso-outro-tenant-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'access_days' => 30,
            'is_featured' => false,
            'is_active' => true,
        ]);

        return compact('course', 'otherCourse');
    },

    'cases' => [
        [
            'name' => 'instructor cria módulo e entra no fim da ordem',
            'as' => 'instructor',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['course']->id,
                'title' => 'Novo Módulo E2E',
            ],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.title' => 'Novo Módulo E2E',
                ],
            ],
            'db' => function (array $ctx): array {
                $module = CourseModule::query()
                    ->where('tenant_id', $ctx['tenant']->id)
                    ->where('title', 'Novo Módulo E2E')
                    ->latest('id')
                    ->first();

                return [
                    'módulo existe' => [true, $module !== null],
                    'curso correto' => [$ctx['fixtures']['course']->id, $module?->course_id],
                    'sort_order append' => [2, $module?->sort_order],
                ];
            },
        ],
        [
            'name' => 'student proibido',
            'as' => 'student',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['course']->id, 'title' => 'Módulo Proibido E2E'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
        [
            'name' => 'sem auth → 401',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['course']->id, 'title' => 'Módulo Sem Auth E2E'],
            'expect' => ['status' => 401],
        ],
        [
            'name' => 'curso de outro tenant → 422',
            'as' => 'instructor',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['otherCourse']->id, 'title' => 'Módulo Cross Tenant E2E'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        CourseModule::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->delete();
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
