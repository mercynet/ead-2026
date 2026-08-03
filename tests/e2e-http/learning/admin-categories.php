<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Category;

return [
    'endpoint' => 'POST /api/v1/admin/categories',

    'setup' => function (array $ctx): array {
        $systemCategory = Category::query()->create([
            'tenant_id' => null,
            'is_system' => true,
            'name' => 'Categoria Sistema Reslot E2E',
            'slug' => 'categoria-sistema-reslot-e2e',
            'normalized_name' => 'categoria sistema reslot e2e',
            'status' => 'active',
        ]);

        $tenantCategory = Category::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'is_system' => false,
            'name' => 'Categoria Tenant Reslot E2E',
            'slug' => 'categoria-tenant-reslot-e2e',
            'normalized_name' => 'categoria tenant reslot e2e',
            'status' => 'active',
        ]);

        return compact('systemCategory', 'tenantCategory');
    },

    'cases' => [
        [
            'name' => 'admin cria categoria do próprio tenant',
            'as' => 'admin',
            'path' => '/api/v1/admin/categories',
            'body' => ['name' => 'Categoria Nova Reslot E2E'],
            'expect' => ['status' => 201, 'json' => ['data.is_system' => false]],
            'db' => function (array $ctx): array {
                $created = Category::query()->where('slug', 'categoria-nova-reslot-e2e')->first();

                return [
                    'categoria criada' => [true, $created !== null],
                    'pertence ao tenant' => [$ctx['tenant']->id, (int) $created?->tenant_id],
                    'não é de sistema' => [false, (bool) $created?->is_system],
                ];
            },
        ],
        [
            'name' => 'admin não pode pedir is_system → 422',
            'as' => 'admin',
            'path' => '/api/v1/admin/categories',
            'body' => ['name' => 'Categoria Sistema Pelo Admin', 'is_system' => true],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => [
                'nada criado' => [false, Category::query()->where('name', 'Categoria Sistema Pelo Admin')->exists()],
            ],
        ],
        [
            'name' => 'admin não edita categoria de sistema → 403',
            'as' => 'admin',
            'method' => 'PUT',
            'path' => fn (array $ctx): string => '/api/v1/admin/categories/'.$ctx['fixtures']['systemCategory']->id,
            'body' => ['name' => 'Tentativa Admin Reslot'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
            'db' => fn (array $ctx): array => [
                'nome preservado' => ['Categoria Sistema Reslot E2E', $ctx['fixtures']['systemCategory']->fresh()->name],
            ],
        ],
        [
            'name' => 'developer é barrado da área admin → 403',
            'as' => 'developer',
            'path' => '/api/v1/admin/categories',
            'body' => ['name' => 'Categoria Pelo Developer'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'developer edita categoria de sistema na área mzrt',
            'as' => 'developer',
            'method' => 'PUT',
            'tenant' => null,
            'path' => fn (array $ctx): string => '/api/v1/mzrt/categories/'.$ctx['fixtures']['systemCategory']->id,
            'body' => ['name' => 'Categoria Sistema Reslot Atualizada'],
            'expect' => ['status' => 200, 'json' => ['data.name' => 'Categoria Sistema Reslot Atualizada']],
            'db' => fn (array $ctx): array => [
                'nome atualizado' => ['Categoria Sistema Reslot Atualizada', $ctx['fixtures']['systemCategory']->fresh()->name],
                'segue global' => [true, $ctx['fixtures']['systemCategory']->fresh()->tenant_id === null],
            ],
        ],
        [
            'name' => 'mzrt não enxerga categoria de tenant → 404',
            'as' => 'developer',
            'method' => 'PUT',
            'tenant' => null,
            'path' => fn (array $ctx): string => '/api/v1/mzrt/categories/'.$ctx['fixtures']['tenantCategory']->id,
            'body' => ['name' => 'Tentativa Mzrt Reslot'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
            'db' => fn (array $ctx): array => [
                'nome preservado' => ['Categoria Tenant Reslot E2E', $ctx['fixtures']['tenantCategory']->fresh()->name],
            ],
        ],
        [
            'name' => 'admin é barrado da área mzrt → 403',
            'as' => 'admin',
            'path' => '/api/v1/mzrt/categories',
            'body' => ['name' => 'Categoria Mzrt Pelo Admin'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'escrita legada em catalog não existe mais → 405',
            'as' => 'admin',
            'path' => '/api/v1/learning/catalog/categories',
            'body' => ['name' => 'Categoria Legada'],
            'expect' => ['status' => 405],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => '/api/v1/admin/categories',
            'body' => ['name' => 'Categoria Sem Auth'],
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Category::withTrashed()
            ->whereIn('slug', [
                'categoria-sistema-reslot-e2e',
                'categoria-tenant-reslot-e2e',
                'categoria-nova-reslot-e2e',
                'categoria-sistema-reslot-atualizada',
            ])
            ->forceDelete();
    },
];
