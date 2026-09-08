<?php

declare(strict_types=1);

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;

/**
 * Spec E2E — superfície canônica Instructor Learning própria.
 *
 * Roda com: php artisan e2e:run instructor/learning-own-surface --base=http://localhost
 * O runner fornece Instructor A, tenant primário, tenant secundário e tokens efêmeros.
 */
return [
    'endpoint' => 'POST /api/v1/instructor/courses',

    'setup' => function (array $ctx): array {
        /** @var User $instructorB */
        $instructorB = User::factory()
            ->instructor()
            ->forTenant($ctx['tenant'])
            ->create(['name' => 'E2E Instructor B']);
        $instructorB->assignRole('instructor');
        $instructorBToken = $instructorB->createToken('e2e-instructor-b')->plainTextToken;

        $courseB = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructorB->id,
            'title' => 'Curso B E2E',
        ]);
        $moduleB = CourseModule::factory()->for($ctx['tenant'])->for($courseB)->create([
            'title' => 'Módulo B E2E',
        ]);
        $lessonB = Lesson::factory()->for($ctx['tenant'])->for($moduleB)->create([
            'title' => 'Aula B E2E',
        ]);

        $nullOwnerCourse = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => null,
            'title' => 'Curso Tenant E2E',
        ]);
        $foreignCourse = Course::factory()->for($ctx['otherTenant'])->create([
            'instructor_id' => $ctx['users']['instructor']->id,
            'title' => 'Curso Outro Tenant E2E',
        ]);

        return [
            'instructorB' => $instructorB,
            'instructorBToken' => $instructorBToken,
            'courseBId' => $courseB->id,
            'moduleBId' => $moduleB->id,
            'lessonBId' => $lessonB->id,
            'nullOwnerCourseId' => $nullOwnerCourse->id,
            'foreignCourseId' => $foreignCourse->id,
        ];
    },

    'cases' => [
        [
            'name' => 'Instructor A cria Course próprio draft',
            'as' => 'instructor',
            'body' => [
                'title' => 'Curso A E2E',
                'price_cents' => 4900,
            ],
            'expect' => [
                'status' => 201,
                'json' => [
                    'data.title' => 'Curso A E2E',
                    'data.status' => 'draft',
                ],
            ],
            'capture' => fn (array $ctx): array => [
                'courseAId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['courseAId']);

                return [
                    'Course A persistido' => [true, $course !== null],
                    'tenant derivado do contexto' => [$ctx['tenant']->id, $course?->tenant_id],
                    'instructor derivado do ator' => [$ctx['users']['instructor']->id, $course?->instructor_id],
                    'status derivado draft' => ['draft', $course?->status],
                ];
            },
        ],
        [
            'name' => 'Instructor A cria Module próprio',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'],
                'title' => 'Módulo A E2E',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'moduleAId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $module = CourseModule::query()->find($ctx['fixtures']['moduleAId']);

                return [
                    'Module A persistido' => [true, $module !== null],
                    'parent Course A' => [$ctx['fixtures']['courseAId'], $module?->course_id],
                    'tenant derivado' => [$ctx['tenant']->id, $module?->tenant_id],
                ];
            },
        ],
        [
            'name' => 'Instructor A cria Lesson própria',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['moduleAId'],
                'title' => 'Aula A E2E',
            ],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'draft']],
            'capture' => fn (array $ctx): array => [
                'lessonAId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => function (array $ctx): array {
                $lesson = Lesson::query()->find($ctx['fixtures']['lessonAId']);

                return [
                    'Lesson A persistida' => [true, $lesson !== null],
                    'parent Module A' => [$ctx['fixtures']['moduleAId'], $lesson?->course_module_id],
                    'status derivado draft' => ['draft', $lesson?->status],
                ];
            },
        ],
        [
            'name' => 'Instructor A lista e mostra somente o próprio Course',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/courses',
            'expect' => [
                'status' => 200,
                'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['courseAId']],
            ],
        ],
        [
            'name' => 'Instructor A mostra o próprio draft',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseAId'],
            'expect' => ['status' => 200, 'json' => ['data.status' => 'draft']],
        ],
        [
            'name' => 'Instructor B consegue acessar apenas seu próprio Course',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseBId'],
            'headers' => fn (array $ctx): array => [
                'Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken'],
            ],
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['courseBId']]],
        ],
        [
            'name' => 'Instructor A não alcança Course B do mesmo tenant',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseBId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Instructor A não alcança Module B nem Lesson B',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/modules/'.$ctx['fixtures']['moduleBId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Lesson B de outro owner retorna 404 defensivo',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['lessonBId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Course sem owner não aparece para Instructor',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['nullOwnerCourseId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Course de outro tenant é bloqueado pela tenant.access',
            'as' => 'instructor',
            'tenant' => 'other',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['foreignCourseId'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
        [
            'name' => 'Instructor não publica por operação inexistente nesta superfície',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseAId'].'/publish',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'sem autenticação recebe 401',
            'method' => 'GET',
            'path' => '/api/v1/instructor/courses',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Lesson::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        CourseModule::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->delete();
        Course::query()->whereIn('tenant_id', [$ctx['tenant']->id, $ctx['otherTenant']->id])->forceDelete();

        /** @var User|null $instructorB */
        $instructorB = $ctx['fixtures']['instructorB'] ?? null;
        if ($instructorB instanceof User) {
            $instructorB->tokens()->delete();
            $instructorB->forceDelete();
        }
    },
];
