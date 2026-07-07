<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;

return [
    'endpoint' => 'POST /api/v1/admin/courses/{id}/publish',

    'setup' => function (array $ctx): array {
        $draftCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Publish E2E',
            'slug' => 'curso-publish-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $archivedCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Archived Publish E2E',
            'slug' => 'curso-archived-publish-e2e',
            'description' => 'descrição',
            'status' => 'archived',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $otherTenantCourse = Course::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'title' => 'Curso Outro Publish E2E',
            'slug' => 'curso-outro-publish-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        return compact('draftCourse', 'archivedCourse', 'otherTenantCourse');
    },

    'cases' => [
        [
            'name' => 'admin publica curso draft',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
            'db' => function (array $ctx): array {
                $course = $ctx['fixtures']['draftCourse']->fresh();

                return [
                    'status publicado' => ['published', $course->status],
                    'published_at preenchido' => [true, $course->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'developer publica por hierarquia',
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
        ],
        [
            'name' => 'student barrado pela área',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'instructor barrado pela área',
            'as' => 'instructor',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'curso archived → 422',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['archivedCourse']->id.'/publish',
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'curso de outro tenant → 404',
            'as' => 'otherAdmin',
            'tenant' => 'other',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 404],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['draftCourse']->id.'/publish',
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
