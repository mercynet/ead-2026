<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;

/**
 * Spec E2E — POST /api/v1/learning/courses (criar curso).
 *
 * Arquivo:    tests/e2e-http/learning/courses-store.php
 * Roda com:   php artisan e2e:run learning/courses-store
 *
 * Contexto disponível nas closures:
 *   $ctx['tenant']       Tenant primário (X-Tenant-ID default das requests)
 *   $ctx['otherTenant']  Segundo tenant (casos de isolamento)
 *   $ctx['users'][role]  User efêmero por papel: admin|instructor|student|developer|otherAdmin
 *   $ctx['response']     Última resposta HTTP (Illuminate\Http\Client\Response)
 *
 * Chaves do caso:
 *   name    rótulo
 *   as      papel autenticado (omite = sem auth → 401)
 *   tenant  'primary' (default) | 'other'
 *   body    payload JSON
 *   expect  ['status' => int, 'json' => ['path.dot' => valorEsperado]]
 *   db      closure(ctx) => ['rótulo' => [esperado, obtido]]  (asserts de side effect)
 */
return [
    'endpoint' => 'POST /api/v1/learning/courses',

    'cases' => [
        [
            'name' => 'admin cria curso (happy path)',
            'as' => 'admin',
            'body' => ['title' => 'Curso E2E', 'description' => 'teste', 'price_cents' => 9900, 'access_days' => 365],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.title' => 'Curso E2E',
                    'data.slug' => 'curso-e2e',
                    'data.status' => 'draft',
                    'data.price_cents' => 9900,
                    'data.is_free' => false,
                ],
            ],
            'db' => function (array $ctx): array {
                $c = Course::query()->where('title', 'Curso E2E')->latest('id')->first();

                return [
                    'curso existe' => [true, $c !== null],
                    'tenant_id vem do contexto (não do body)' => [$ctx['tenant']->id, $c?->tenant_id],
                    'instructor_id = ator autenticado' => [$ctx['users']['admin']->id, $c?->instructor_id],
                    'slug derivado do título' => ['curso-e2e', $c?->slug],
                    'draft não seta published_at' => [null, $c?->published_at],
                    'soft delete intacto' => [null, $c?->deleted_at],
                ];
            },
        ],

        [
            'name' => 'defaults aplicados quando só title é enviado',
            'as' => 'admin',
            'body' => ['title' => 'Curso Defaults E2E'],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.status' => 'draft',
                    'data.price_cents' => 0,
                    'data.is_free' => true,
                ],
            ],
            'db' => function (array $ctx): array {
                $c = Course::query()->where('title', 'Curso Defaults E2E')->first();

                return [
                    'is_active default true' => [true, (bool) $c?->is_active],
                    'price_cents default 0' => [0, $c?->price_cents],
                ];
            },
        ],

        [
            // Contrato: StoreCourseAction força status=draft; o status do body é
            // ignorado (publicação só via endpoint administrativo dedicado).
            'name' => 'status published no body é ignorado (curso nasce draft)',
            'as' => 'admin',
            'body' => ['title' => 'Curso Publicado E2E', 'status' => 'published'],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'draft']],
            'db' => function (array $ctx): array {
                $c = Course::query()->where('title', 'Curso Publicado E2E')->first();

                return [
                    'status persistido draft' => ['draft', $c?->status],
                    'published_at vazio' => [null, $c?->published_at],
                ];
            },
        ],

        [
            'name' => 'tenant_id e instructor_id no body são ignorados (não-spoofáveis)',
            'as' => 'admin',
            'body' => ['title' => 'Curso Spoof E2E', 'tenant_id' => 999999, 'instructor_id' => 999999],
            'expect' => ['status' => 201],
            'db' => function (array $ctx): array {
                $c = Course::query()->where('title', 'Curso Spoof E2E')->first();

                return [
                    'tenant_id ignora body' => [$ctx['tenant']->id, $c?->tenant_id],
                    'instructor_id ignora body' => [$ctx['users']['admin']->id, $c?->instructor_id],
                ];
            },
        ],

        [
            'name' => 'instructor pode criar',
            'as' => 'instructor',
            'body' => ['title' => 'Curso Instrutor E2E'],
            'expect' => ['status' => 201],
        ],

        [
            'name' => 'student é proibido (403)',
            'as' => 'student',
            'body' => ['title' => 'Curso Proibido E2E'],
            'expect' => ['status' => 403],
        ],

        [
            'name' => 'sem auth → 401',
            'body' => ['title' => 'Curso Sem Auth E2E'],
            'expect' => ['status' => 401],
        ],

        [
            'name' => 'title obrigatório → 422',
            'as' => 'admin',
            'body' => ['description' => 'sem título'],
            'expect' => ['status' => 422],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Course::query()
            ->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])
            ->forceDelete();
    },
];
