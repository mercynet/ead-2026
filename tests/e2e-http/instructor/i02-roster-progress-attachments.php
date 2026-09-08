<?php

declare(strict_types=1);

use App\Modules\Core\Models\TenantCustomization;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;

return [
    'endpoint' => 'GET /api/v1/instructor/enrollments',

    'setup' => function (array $ctx): array {
        $instructor = $ctx['users']['instructor'];
        $student = $ctx['users']['student'];
        $studentTwo = User::factory()->forTenant($ctx['tenant'])->student()->create(['name' => 'E2E Student Two']);
        $instructorB = User::factory()->forTenant($ctx['tenant'])->instructor()->create(['name' => 'E2E Instructor B']);
        $instructorB->assignRole('instructor');
        $instructorBToken = $instructorB->createToken('e2e-instructor-b')->plainTextToken;

        TenantCustomization::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true]]],
        ]);

        $courseA = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I02 Course A',
            'price_cents' => 0,
            'status' => 'published',
            'is_active' => true,
        ]);
        $moduleA = CourseModule::factory()->for($ctx['tenant'])->for($courseA)->create();
        $lessonA = Lesson::factory()->for($ctx['tenant'])->for($moduleA)->create(['status' => 'published', 'is_active' => true]);
        $lessonA2 = Lesson::factory()->for($ctx['tenant'])->for($moduleA)->create(['status' => 'published', 'is_active' => true]);
        Lesson::factory()->for($ctx['tenant'])->for($moduleA)->create(['status' => 'draft', 'is_active' => true]);

        $courseB = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructorB->id,
            'title' => 'E2E I02 Course B',
            'price_cents' => 0,
        ]);
        $moduleB = CourseModule::factory()->for($ctx['tenant'])->for($courseB)->create();
        $lessonB = Lesson::factory()->for($ctx['tenant'])->for($moduleB)->create();

        $foreignCourse = Course::factory()->for($ctx['otherTenant'])->create(['instructor_id' => $instructor->id]);
        $foreignModule = CourseModule::factory()->for($ctx['otherTenant'])->for($foreignCourse)->create();
        $foreignLesson = Lesson::factory()->for($ctx['otherTenant'])->for($foreignModule)->create();

        $enrollmentA = Enrollment::factory()->active()->for($ctx['tenant'])->for($student, 'user')->for($courseA)->create();
        LessonProgress::factory()->completed()->for($ctx['tenant'])->for($student, 'user')->for($courseA)->for($enrollmentA)->for($lessonA)->create();
        LessonProgress::factory()->for($ctx['tenant'])->for($student, 'user')->for($courseA)->for($enrollmentA)->for($lessonA2)->create(['progress_percentage' => 40]);

        return [
            'studentTwoId' => $studentTwo->id,
            'instructorBId' => $instructorB->id,
            'instructorBToken' => $instructorBToken,
            'courseAId' => $courseA->id,
            'courseBId' => $courseB->id,
            'paidCourseId' => Course::factory()->for($ctx['tenant'])->create([
                'instructor_id' => $instructor->id,
                'price_cents' => 100,
                'status' => 'published',
                'is_active' => true,
            ])->id,
            'lessonAId' => $lessonA->id,
            'lessonBId' => $lessonB->id,
            'foreignLessonId' => $foreignLesson->id,
            'enrollmentAId' => $enrollmentA->id,
            'foreignCourseId' => $foreignCourse->id,
            'foreignMaterialId' => CourseMaterial::factory()->for($ctx['otherTenant'])->for($foreignCourse)->create()->id,
            'foreignMediaId' => LessonMedia::factory()->for($ctx['otherTenant'])->for($foreignLesson)->create()->id,
            'courseAPath' => 'tenants/'.$ctx['tenant']->id.'/materials/e2e-i02.pdf',
        ];
    },

    'cases' => [
        [
            'name' => 'Instructor A vê apenas o roster do Course A',
            'as' => 'instructor',
            'expect' => ['status' => 200, 'json' => ['data.0.course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId']]],
            'db' => fn (array $ctx): array => ['enrollment A existente' => [true, Enrollment::query()->whereKey($ctx['fixtures']['enrollmentAId'])->exists()]],
        ],
        [
            'name' => 'Instructor B não vê Enrollment A',
            'as' => 'instructor',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken']],
            'expect' => ['status' => 200, 'json' => ['data' => []]],
            'db' => fn (array $ctx): array => ['Course B sem matrícula no roster' => [0, Enrollment::query()->where('course_id', $ctx['fixtures']['courseBId'])->count()]],
        ],
        [
            'name' => 'Instructor A consulta progresso agregado e detalhe mínimo',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/enrollments/'.$ctx['fixtures']['enrollmentAId'].'/progress',
            'expect' => ['status' => 200, 'json' => ['data.progress.percentage' => 50, 'data.progress.total_lessons' => 2]],
        ],
        [
            'name' => 'Instructor A cria matrícula manual FREE própria',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentTwoId']],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'active']],
            'capture' => fn (array $ctx): array => ['freeEnrollmentId' => (int) $ctx['response']->json('data.id')],
            'db' => function (array $ctx): array {
                $order = Order::query()->where('source_key', 'learning:enrollment:'.$ctx['fixtures']['freeEnrollmentId'])->first();

                return [
                    'espelho Order paid zero' => [true, $order?->status === 'paid' && $order->total_cents === 0],
                    'item Course único' => [1, $order?->items()->count()],
                    'payment free resolvido' => ['free', $order?->payments()->first()?->gateway_slug],
                ];
            },
        ],
        [
            'name' => 'Replay FREE idempotente não duplica',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentTwoId']],
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['freeEnrollmentId']]],
            'db' => fn (array $ctx): array => [
                'uma matrícula corrente' => [1, Enrollment::query()->where('user_id', $ctx['fixtures']['studentTwoId'])->where('course_id', $ctx['fixtures']['courseAId'])->whereIn('status', ['pending', 'active'])->count()],
                'um Order mirror' => [1, Order::query()->where('tenant_id', $ctx['tenant']->id)->count()],
                'um Payment mirror' => [1, Payment::query()->whereHas('order', fn ($query) => $query->where('tenant_id', $ctx['tenant']->id))->count()],
            ],
        ],
        [
            'name' => 'Course pago retorna 422 sem efeitos',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['paidCourseId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentTwoId']],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => ['sem Order adicional' => [1, Order::query()->where('tenant_id', $ctx['tenant']->id)->count()]],
        ],
        [
            'name' => 'billing_type external é rejeitado',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/enrollments',
            'body' => ['course_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'], 'user_id' => fn (array $ctx): int => $ctx['fixtures']['studentTwoId'], 'billing_type' => 'external'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => ['sem segunda matrícula' => [2, Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->count()]],
        ],
        [
            'name' => 'Instructor A cria metadata de LessonMedia própria',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['lessonAId'].'/media',
            'body' => ['media_type' => 'video', 'provider' => 'embed', 'provider_ref' => 'e2e-i02-video', 'url' => 'https://video.example/e2e-i02'],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['mediaAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'LessonMedia de outro owner retorna 404 defensivo',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['lessonBId'].'/media',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'LessonMedia cross-tenant retorna 404 defensivo',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['foreignLessonId'].'/media',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Instructor A cria CourseMaterial próprio sem expor path',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseAId'].'/materials',
            'body' => ['file_path' => fn (array $ctx): string => $ctx['fixtures']['courseAPath']],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['materialAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'CourseMaterial de outro owner retorna 404 defensivo',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseBId'].'/materials',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Course de outro tenant é bloqueado',
            'as' => 'instructor',
            'tenant' => 'other',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['foreignCourseId'].'/materials',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
        [
            'name' => 'sem autenticação recebe 401',
            'method' => 'GET',
            'path' => '/api/v1/instructor/enrollments',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        foreach (['studentTwoId', 'instructorBId'] as $fixtureKey) {
            $user = User::query()->find($ctx['fixtures'][$fixtureKey] ?? null);

            if ($user instanceof User) {
                $user->tokens()->delete();
                $user->forceDelete();
            }
        }
    },
];
