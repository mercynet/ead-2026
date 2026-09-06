<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;

/**
 * Spec E2E — jornada administrativa de conteúdo do ADM-02.
 *
 * Exercita HTTP real na superfície /api/v1/admin, side effects no banco, isolamento
 * entre tenants e bloqueio da área para Instructor. Não cobre matrícula/consumo.
 */
return [
    'endpoint' => 'POST /api/v1/admin/courses',

    'cases' => [
        [
            'name' => 'admin cria curso sem assumir ownership de instructor',
            'as' => 'admin',
            'body' => [
                'title' => 'ADM-02 Curso E2E',
                'description' => 'Jornada administrativa de conteúdo.',
                'price_cents' => 12500,
            ],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.title' => 'ADM-02 Curso E2E',
                    'data.status' => 'draft',
                    'data.price_cents' => 12500,
                    'data.instructor_id' => null,
                ],
            ],
            'capture' => fn (array $ctx): array => [
                'courseId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['courseId']);

                return [
                    'curso persistido' => [true, $course !== null],
                    'tenant vem do contexto' => [$ctx['tenant']->id, $course?->tenant_id],
                    'Admin não vira instructor' => [null, $course?->instructor_id],
                ];
            },
        ],
        [
            'name' => 'admin lista e atualiza metadados do curso',
            'as' => 'admin',
            'method' => 'GET',
            'path' => '/api/v1/admin/courses',
            'expect' => [
                'status' => 200,
                'json' => ['data.0.title' => 'ADM-02 Curso E2E'],
            ],
        ],
        [
            'name' => 'admin atualiza metadados sem alterar ownership',
            'as' => 'admin',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'],
            'body' => ['title' => 'ADM-02 Curso E2E Atualizado', 'price_cents' => 15000],
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.title' => 'ADM-02 Curso E2E Atualizado',
                    'data.price_cents' => 15000,
                ],
            ],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['courseId']);

                return [
                    'título atualizado' => ['ADM-02 Curso E2E Atualizado', $course?->title],
                    'ownership preservado' => [null, $course?->instructor_id],
                ];
            },
        ],
        [
            'name' => 'admin cria módulo',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['courseId'],
                'title' => 'ADM-02 Módulo E2E',
            ],
            'expect' => ['status' => 201, 'json' => ['data.title' => 'ADM-02 Módulo E2E']],
            'capture' => fn (array $ctx): array => [
                'moduleId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $module = CourseModule::query()->find($ctx['fixtures']['moduleId']);

                return [
                    'módulo persistido' => [true, $module !== null],
                    'módulo pertence ao curso' => [$ctx['fixtures']['courseId'], $module?->course_id],
                ];
            },
        ],
        [
            'name' => 'admin lista módulos',
            'as' => 'admin',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'].'/modules',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['moduleId']]],
        ],
        [
            'name' => 'admin cria aula',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['moduleId'],
                'title' => 'ADM-02 Aula E2E',
            ],
            'expect' => ['status' => 201, 'json' => ['data.title' => 'ADM-02 Aula E2E']],
            'capture' => fn (array $ctx): array => [
                'lessonId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $lesson = Lesson::query()->find($ctx['fixtures']['lessonId']);

                return [
                    'aula persistida' => [true, $lesson !== null],
                    'aula pertence ao módulo' => [$ctx['fixtures']['moduleId'], $lesson?->course_module_id],
                ];
            },
        ],
        [
            'name' => 'admin lê detalhe da aula sem tracking de consumo',
            'as' => 'admin',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'],
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.id' => fn (array $ctx): int => $ctx['fixtures']['lessonId'],
                    'data.status' => 'draft',
                ],
            ],
        ],
        [
            'name' => 'admin publica a aula por transição explícita',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'].'/publish',
            'expect' => [
                'status' => 200,
                'json' => ['data.status' => 'published'],
            ],
            'db' => function (array $ctx): array {
                $lesson = Lesson::query()->find($ctx['fixtures']['lessonId']);

                return [
                    'aula publicada' => ['published', $lesson?->status],
                    'published_at preenchido' => [true, $lesson?->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'admin despublica a aula sem apagar published_at',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'].'/unpublish',
            'expect' => [
                'status' => 200,
                'json' => ['data.status' => 'draft'],
            ],
            'db' => function (array $ctx): array {
                $lesson = Lesson::query()->find($ctx['fixtures']['lessonId']);

                return [
                    'aula voltou a draft' => ['draft', $lesson?->status],
                    'published_at preservado' => [true, $lesson?->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'admin cria material administrativo',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'].'/materials',
            'body' => ['file_path' => fn (array $ctx): string => 'tenants/'.$ctx['tenant']->id.'/materials/adm-02.pdf'],
            'expect' => ['status' => 201, 'json' => ['data.instructor_id' => null]],
            'capture' => fn (array $ctx): array => [
                'materialId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $material = CourseMaterial::query()->find($ctx['fixtures']['materialId']);

                return [
                    'material persistido' => [true, $material !== null],
                    'material pertence ao curso' => [$ctx['fixtures']['courseId'], $material?->course_id],
                    'material sem ownership de instructor' => [null, $material?->instructor_id],
                ];
            },
        ],
        [
            'name' => 'admin cria mídia administrativa',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'].'/media',
            'body' => [
                'media_type' => 'video',
                'provider' => 'embed',
                'url' => 'https://video.example/adm-02',
            ],
            'expect' => ['status' => 201, 'json' => ['data.lesson_id' => fn (array $ctx): int => $ctx['fixtures']['lessonId']]],
            'capture' => fn (array $ctx): array => [
                'mediaId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $media = LessonMedia::query()->find($ctx['fixtures']['mediaId']);

                return [
                    'mídia persistida' => [true, $media !== null],
                    'mídia pertence à aula' => [$ctx['fixtures']['lessonId'], $media?->lesson_id],
                ];
            },
        ],
        [
            'name' => 'instructor não alcança a área Admin',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'outro tenant recebe 404 defensivo',
            'as' => 'otherAdmin',
            'tenant' => 'other',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'admin remove mídia',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'].'/media/'.$ctx['fixtures']['mediaId'],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return ['mídia removida' => [false, LessonMedia::query()->whereKey($ctx['fixtures']['mediaId'])->exists()]];
            },
        ],
        [
            'name' => 'admin remove material',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'].'/materials/'.$ctx['fixtures']['materialId'],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return ['material removido' => [false, CourseMaterial::query()->whereKey($ctx['fixtures']['materialId'])->exists()]];
            },
        ],
        [
            'name' => 'admin remove aula, módulo e curso',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return ['aula removida' => [false, Lesson::query()->whereKey($ctx['fixtures']['lessonId'])->exists()]];
            },
        ],
        [
            'name' => 'admin remove módulo',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/modules/'.$ctx['fixtures']['moduleId'],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return ['módulo removido' => [false, CourseModule::query()->whereKey($ctx['fixtures']['moduleId'])->exists()]];
            },
        ],
        [
            'name' => 'admin remove curso e encerra jornada',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                return ['curso removido' => [false, Course::query()->whereKey($ctx['fixtures']['courseId'])->exists()]];
            },
        ],
    ],

    'cleanup' => function (array $ctx): void {
        LessonMedia::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        Lesson::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        CourseMaterial::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        CourseModule::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        Course::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->forceDelete();
    },
];
