<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;

/**
 * Spec E2E — GET /api/v1/learning/courses/{id} (ver curso, admin view).
 *
 * Arquivo:    tests/e2e-http/learning/courses-show.php
 * Roda com:   php artisan e2e:run learning/courses-show
 *
 * Contexto disponível nas closures:
 *   $ctx['tenant']        Tenant primário (X-Tenant-ID default das requests)
 *   $ctx['otherTenant']   Segundo tenant (casos de isolamento)
 *   $ctx['users'][role]   User efêmero por papel: admin|instructor|student|developer|otherAdmin
 *   $ctx['fixtures']      Retorno do setup() — cursos pré-criados
 *   $ctx['response']      Última resposta HTTP
 *
 * Chaves do caso:
 *   name    rótulo
 *   as      papel autenticado (omite = sem auth → 401)
 *   tenant  'primary' (default) | 'other'
 *   path    closure(ctx) => string — resolve a URL com id dinâmico
 *   expect  ['status' => int, 'json' => ['path.dot' => valorEsperado]]
 */
return [
    'endpoint' => 'GET /api/v1/learning/courses/{id}',

    'setup' => function (array $ctx): array {
        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'Curso Show E2E',
            'slug' => 'curso-show-e2e',
            'description' => 'descrição',
            'status' => 'draft',
            'price_cents' => 0,
            'is_featured' => false,
            'is_active' => true,
        ]);

        return ['course' => $course];
    },

    'cases' => [
        [
            'name' => 'admin vê curso do próprio tenant (happy path)',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['course']->id,
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.title' => 'Curso Show E2E',
                    'data.slug' => 'curso-show-e2e',
                    'data.status' => 'draft',
                    'data.is_free' => true,
                ],
            ],
        ],

        [
            'name' => 'student também vê (permission view é ampla)',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['course']->id,
            'expect' => ['status' => 200, 'json' => ['data.status' => 'draft']],
        ],

        [
            'name' => 'admin de outro tenant não alcança curso (isolamento → 404)',
            'as' => 'otherAdmin',
            'tenant' => 'other',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['course']->id,
            'expect' => ['status' => 404],
        ],

        [
            'name' => 'curso inexistente → 404',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/999999',
            'expect' => ['status' => 404],
        ],

        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/learning/courses/'.$ctx['fixtures']['course']->id,
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
