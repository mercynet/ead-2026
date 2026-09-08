<?php

declare(strict_types=1);

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

return [
    'endpoint' => 'POST /api/v1/admin/courses',

    'cases' => [
        [
            'name' => 'Admin cria Course comercial base em draft',
            'as' => 'admin',
            'method' => 'POST',
            'body' => [
                'title' => 'E2E Commercial Base Course',
                'description' => 'Curso sem Assessment ou certificado prometido.',
                'price_cents' => 0,
                'access_days' => 0,
            ],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'draft']],
            'capture' => fn (array $ctx): array => [
                'baseCourseId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => fn (array $ctx): array => [
                'Course base sem certificate' => [
                    false,
                    Course::query()->find($ctx['fixtures']['baseCourseId'])?->certificate_enabled,
                ],
                'Course base sem Assessment requerido' => [
                    false,
                    Course::query()->find($ctx['fixtures']['baseCourseId'])?->certificate_requires_quiz,
                ],
            ],
        ],
        [
            'name' => 'Admin cria Module e Lesson para o Course base',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['baseCourseId'],
                'title' => 'E2E Commercial Base Module',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'baseModuleId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin cria Lesson base e publica a Lesson',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['baseModuleId'],
                'title' => 'E2E Commercial Base Lesson',
                'is_free' => true,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'baseLessonId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin publica Lesson base por transição dedicada',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['baseLessonId'].'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
        ],
        [
            'name' => 'Admin publica Course base vendável',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['baseCourseId'].'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['baseCourseId']);

                return [
                    'Course base publicado' => ['published', $course?->status],
                    'Course base published_at preenchido' => [true, $course?->published_at !== null],
                ];
            },
        ],
        [
            'name' => 'Admin matricula Student no Course base',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/enrollments',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['baseCourseId'],
                'user_id' => fn (array $ctx): int => $ctx['users']['student']->id,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'baseEnrollmentId' => (int) $ctx['response']->json('data.id'),
            ],
            'db' => fn (array $ctx): array => [
                'Enrollment base ativa' => [
                    'active',
                    Enrollment::query()->find($ctx['fixtures']['baseEnrollmentId'])?->status,
                ],
            ],
        ],
        [
            'name' => 'Student consome Course base sem anunciar Assessment/certificate',
            'method' => 'GET',
            'path' => '/api/v1/student/courses',
            'as' => 'student',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.id' => fn (array $ctx): int => $ctx['fixtures']['baseCourseId'],
                    'data.0.assessment' => null,
                    'data.0.certificate' => null,
                ],
            ],
        ],
        [
            'name' => 'Student acessa Lesson do Course base',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['baseLessonId'],
            'as' => 'student',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'PATCH não adiciona Assessment a Course já publicado',
            'as' => 'admin',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['baseCourseId'],
            'body' => ['certificate_requires_quiz' => true],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['baseCourseId']);

                return [
                    'Course continua publicado' => ['published', $course?->status],
                    'Assessment continua desligado' => [false, $course?->certificate_requires_quiz],
                ];
            },
        ],
        [
            'name' => 'Admin cria Course que exige Assessment',
            'as' => 'admin',
            'method' => 'POST',
            'body' => [
                'title' => 'E2E Assessment Required Course',
                'certificate_requires_quiz' => true,
            ],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'draft']],
            'capture' => fn (array $ctx): array => [
                'assessmentCourseId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin prepara estrutura do Course com Assessment requerido',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['assessmentCourseId'],
                'title' => 'E2E Assessment Required Module',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'assessmentModuleId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin publica Lesson do Course com Assessment requerido',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['assessmentModuleId'],
                'title' => 'E2E Assessment Required Lesson',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'assessmentLessonId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Course Assessment-required falha fechado no publish',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['assessmentLessonId'].'/publish',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'Assessment-required Course permanece draft sem side effect',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['assessmentCourseId'].'/publish',
            'expect' => [
                'status' => 422,
                'json' => [
                    'errors.0.code' => 'validation_error',
                    'errors.0.message' => 'Course is not commercially ready: Student Assessment is not available for a required completion condition.',
                ],
            ],
            'db' => fn (array $ctx): array => [
                'Assessment-required Course permanece draft' => [
                    'draft',
                    Course::query()->find($ctx['fixtures']['assessmentCourseId'])?->status,
                ],
                'Assessment-required Course sem published_at' => [
                    null,
                    Course::query()->find($ctx['fixtures']['assessmentCourseId'])?->published_at,
                ],
            ],
        ],
        [
            'name' => 'Admin cria Course que promete certificado',
            'as' => 'admin',
            'method' => 'POST',
            'body' => [
                'title' => 'E2E Certificate Required Course',
                'certificate_enabled' => true,
            ],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'draft']],
            'capture' => fn (array $ctx): array => [
                'certificateCourseId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin prepara estrutura do Course com certificado',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['certificateCourseId'],
                'title' => 'E2E Certificate Required Module',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'certificateModuleId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Admin publica Lesson do Course com certificado',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['certificateModuleId'],
                'title' => 'E2E Certificate Required Lesson',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'certificateLessonId' => (int) $ctx['response']->json('data.id'),
            ],
        ],
        [
            'name' => 'Course certificado falha fechado no publish',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['certificateLessonId'].'/publish',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'Certificate-required Course permanece draft sem side effect',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['certificateCourseId'].'/publish',
            'expect' => [
                'status' => 422,
                'json' => [
                    'errors.0.code' => 'validation_error',
                    'errors.0.message' => 'Course is not commercially ready: certificates are not available in the current release.',
                ],
            ],
            'db' => fn (array $ctx): array => [
                'Certificate-required Course permanece draft' => [
                    'draft',
                    Course::query()->find($ctx['fixtures']['certificateCourseId'])?->status,
                ],
                'Certificate-required Course sem published_at' => [
                    null,
                    Course::query()->find($ctx['fixtures']['certificateCourseId'])?->published_at,
                ],
            ],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $tenantIds = [$ctx['tenant']->id, $ctx['otherTenant']->id];

        Enrollment::query()->whereIn('tenant_id', $tenantIds)->delete();
        Course::query()->whereIn('tenant_id', $tenantIds)->forceDelete();

        if (Course::query()->whereIn('tenant_id', $tenantIds)->withTrashed()->exists()) {
            throw new RuntimeException('Commercial capability gate E2E cleanup left Course fixtures.');
        }
    },
];
