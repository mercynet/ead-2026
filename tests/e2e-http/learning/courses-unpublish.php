<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;

return [
    'endpoint' => 'POST /api/v1/admin/courses/{id}/unpublish',

    'setup' => function (array $ctx): array {
        $publishedCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Unpublish E2E',
            'slug' => 'curso-unpublish-e2e',
            'description' => 'descrição',
            'status' => 'published',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $archivedCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Archived Unpublish E2E',
            'slug' => 'curso-archived-unpublish-e2e',
            'description' => 'descrição',
            'status' => 'archived',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        return compact('publishedCourse', 'archivedCourse');
    },

    'cases' => [
        [
            'name' => 'developer é barrado pela guarda de área → 403',
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['publishedCourse']->id.'/unpublish',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
            'db' => function (array $ctx): array {
                $course = $ctx['fixtures']['publishedCourse']->fresh();

                return [
                    'status permanece publicado' => ['published', $course->status],
                    'published_at preservado' => [true, $course->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'admin despublica curso',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['publishedCourse']->id.'/unpublish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'draft']],
            'db' => function (array $ctx): array {
                $course = $ctx['fixtures']['publishedCourse']->fresh();

                return [
                    'status draft' => ['draft', $course->status],
                    'published_at preservado' => [true, $course->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'student barrado pela área',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['publishedCourse']->id.'/unpublish',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'curso archived → 422',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['archivedCourse']->id.'/unpublish',
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['publishedCourse']->id.'/unpublish',
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
