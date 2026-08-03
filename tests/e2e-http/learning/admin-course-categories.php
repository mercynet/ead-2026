<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use Illuminate\Support\Facades\DB;

return [
    'endpoint' => 'PUT /api/v1/admin/courses/{id}/categories',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Categorias E2E',
            'slug' => 'curso-categorias-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $tenantCategory = Category::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'is_system' => false,
            'name' => 'Categoria Tenant E2E',
            'slug' => 'categoria-tenant-e2e',
            'normalized_name' => 'categoria tenant e2e',
            'status' => 'active',
        ]);

        $systemCategory = Category::query()->create([
            'tenant_id' => null,
            'is_system' => true,
            'name' => 'Categoria Sistema E2E',
            'slug' => 'categoria-sistema-e2e',
            'normalized_name' => 'categoria sistema e2e',
            'status' => 'active',
        ]);

        $otherTenantCategory = Category::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'is_system' => false,
            'name' => 'Categoria Outro Tenant E2E',
            'slug' => 'categoria-outro-tenant-e2e',
            'normalized_name' => 'categoria outro tenant e2e',
            'status' => 'active',
        ]);

        return compact('course', 'tenantCategory', 'systemCategory', 'otherTenantCategory');
    },

    'cases' => [
        [
            'name' => 'admin sincroniza categorias na ordem do payload',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => fn (array $ctx): array => [
                'categories' => [
                    ['id' => $ctx['fixtures']['systemCategory']->id, 'is_featured' => true],
                    ['id' => $ctx['fixtures']['tenantCategory']->id],
                ],
            ],
            'expect' => ['status' => 200, 'json' => [
                'data.categories.0.id' => fn (array $ctx): int => $ctx['fixtures']['systemCategory']->id,
                'data.categories.1.id' => fn (array $ctx): int => $ctx['fixtures']['tenantCategory']->id,
            ]],
            'db' => function (array $ctx): array {
                $rows = DB::table('category_course')
                    ->where('course_id', $ctx['fixtures']['course']->id)
                    ->orderBy('sort_order')
                    ->get(['category_id', 'sort_order', 'is_featured', 'tenant_id']);

                return [
                    'dois vínculos' => [2, $rows->count()],
                    'sistema em primeiro' => [$ctx['fixtures']['systemCategory']->id, (int) $rows[0]->category_id],
                    'ordem 1' => [1, (int) $rows[0]->sort_order],
                    'destaque no primeiro' => [1, (int) $rows[0]->is_featured],
                    'tenant do pivô é do curso' => [$ctx['tenant']->id, (int) $rows[0]->tenant_id],
                    'tenant em segundo' => [$ctx['fixtures']['tenantCategory']->id, (int) $rows[1]->category_id],
                    'ordem 2' => [2, (int) $rows[1]->sort_order],
                ];
            },
        ],
        [
            'name' => 'categoria de outro tenant → 422 sem alterar vínculos',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => fn (array $ctx): array => [
                'categories' => [['id' => $ctx['fixtures']['otherTenantCategory']->id]],
            ],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => function (array $ctx): array {
                return [
                    'vínculos preservados' => [2, DB::table('category_course')
                        ->where('course_id', $ctx['fixtures']['course']->id)
                        ->count()],
                ];
            },
        ],
        [
            'name' => 'lista vazia limpa os vínculos',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => ['categories' => []],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return [
                    'nenhum vínculo' => [0, DB::table('category_course')
                        ->where('course_id', $ctx['fixtures']['course']->id)
                        ->count()],
                ];
            },
        ],
        [
            'name' => 'developer é barrado pela guarda de área → 403',
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => fn (array $ctx): array => [
                'categories' => [['id' => $ctx['fixtures']['tenantCategory']->id]],
            ],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'instructor barrado pela área',
            'as' => 'instructor',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => ['categories' => []],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'student barrado pela área',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => ['categories' => []],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'curso de outro tenant → 404',
            'as' => 'otherAdmin',
            'tenant' => 'other',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => ['categories' => []],
            'expect' => ['status' => 404],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['course']->id.'/categories',
            'body' => ['categories' => []],
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $courseIds = Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->pluck('id');

        DB::table('category_course')->whereIn('course_id', $courseIds)->delete();

        Course::query()->whereIn('id', $courseIds)->forceDelete();

        Category::withTrashed()
            ->whereIn('slug', [
                'categoria-tenant-e2e',
                'categoria-sistema-e2e',
                'categoria-outro-tenant-e2e',
            ])
            ->forceDelete();
    },
];
