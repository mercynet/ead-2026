<?php

declare(strict_types=1);

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonMediaProgress;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\Storage;

/**
 * E2E HTTP integrado do paid pilot assistido: Instructor authoring, Admin publish/cash,
 * Student consumption/progress e Instructor roster, com tenants/personas cruzados.
 */
return [
    'endpoint' => 'POST /api/v1/instructor/courses',

    'setup' => function (array $ctx): array {
        $instructorB = User::factory()->instructor()->forTenant($ctx['tenant'])->create([
            'name' => 'E2E S02 Instructor B',
        ]);
        $instructorB->assignRole('instructor');
        $instructorBToken = $instructorB->createToken('e2e-s02-instructor-b')->plainTextToken;

        $studentB = User::factory()->student()->forTenant($ctx['tenant'])->create([
            'name' => 'E2E S02 Student B',
        ]);
        $studentB->assignRole('student');
        $studentBToken = $studentB->createToken('e2e-s02-student-b')->plainTextToken;

        $foreignStudent = User::factory()->student()->forTenant($ctx['otherTenant'])->create([
            'name' => 'E2E S02 Foreign Student',
        ]);
        $foreignStudent->assignRole('student');
        $foreignStudentToken = $foreignStudent->createToken('e2e-s02-foreign-student')->plainTextToken;

        $negativeFixtures = [];
        foreach (['pending', 'expired', 'cancelled'] as $status) {
            $course = Course::factory()->for($ctx['tenant'])->create([
                'title' => 'E2E S02 '.ucfirst($status).' Course',
                'price_cents' => 1500,
                'status' => 'published',
                'is_active' => true,
            ]);
            $module = CourseModule::factory()->for($ctx['tenant'])->for($course)->create();
            $lesson = Lesson::factory()->for($ctx['tenant'])->for($module)->create([
                'status' => 'published',
                'is_active' => true,
                'is_free' => false,
            ]);
            Enrollment::factory()->for($ctx['tenant'])->for($course)->for($ctx['users']['student'], 'user')->create([
                'status' => $status,
                'access_expires_at' => $status === 'expired' ? now()->subMinute() : null,
            ]);
            $negativeFixtures[$status.'LessonId'] = $lesson->id;
        }

        return [
            'instructorB' => $instructorB,
            'instructorBToken' => $instructorBToken,
            'studentB' => $studentB,
            'studentBToken' => $studentBToken,
            'foreignStudent' => $foreignStudent,
            'foreignStudentToken' => $foreignStudentToken,
            ...$negativeFixtures,
        ];
    },

    'cases' => [
        [
            'name' => 'MZRT confirma tenant provisionado e ativo',
            'method' => 'GET',
            'tenant' => null,
            'path' => fn (array $ctx): string => '/api/v1/mzrt/tenants/'.$ctx['tenant']->id.'/entitlements',
            'headers' => [
                'Authorization' => fn (array $ctx): string => 'Bearer '.$ctx['tokens']['developer'],
            ],
            'expect' => ['status' => 200],
            'db' => fn (array $ctx): array => [
                'tenant permanece ativo' => [true, Tenant::query()->find($ctx['tenant']->id)?->is_active],
            ],
        ],
        [
            'name' => 'Instructor cria Course próprio para o tenant',
            'as' => 'instructor',
            'body' => [
                'title' => 'E2E S02 Paid Pilot Course',
                'description' => 'Curso integrado sem Assessment ou certificado prometido.',
                'short_description' => 'Jornada comercial integrada.',
                'price_cents' => 1500,
                'access_days' => 30,
                'certificate_enabled' => false,
            ],
            'expect' => [
                'status' => 201,
                'json' => ['data.status' => 'draft', 'data.instructor_id' => fn (array $ctx): int => $ctx['users']['instructor']->id],
            ],
            'capture' => function (array $ctx): array {
                $courseId = (int) $ctx['response']->json('data.id');
                $course = Course::query()->findOrFail($courseId);
                $order = Order::factory()->create([
                    'tenant_id' => $ctx['tenant']->id,
                    'user_id' => $ctx['users']['student']->id,
                    'status' => 'pending',
                    'total_cents' => $course->price_cents,
                    'metadata' => ['e2e' => 'student-s02-commercial'],
                ]);
                OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'itemable_type' => Course::class,
                    'itemable_id' => $course->id,
                    'item_snapshot' => ['title' => $course->title],
                    'price_cents' => $course->price_cents,
                ]);
                $payment = Payment::factory()->create([
                    'order_id' => $order->id,
                    'status' => 'pending',
                    'gateway_slug' => 'cash',
                    'confirmation_mode' => 'manual',
                ]);

                return ['courseId' => $courseId, 'orderId' => $order->id, 'paymentId' => $payment->id];
            },
            'db' => fn (array $ctx): array => [
                'course pertence ao tenant' => [$ctx['tenant']->id, Course::query()->find($ctx['fixtures']['courseId'])?->tenant_id],
                'course nasce draft' => ['draft', Course::query()->find($ctx['fixtures']['courseId'])?->status],
            ],
        ],
        [
            'name' => 'Instructor cria Module e Lesson em cadeia',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/modules',
            'body' => [
                'course_id' => fn (array $ctx): int => $ctx['fixtures']['courseId'],
                'title' => 'E2E S02 Module',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['moduleId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor cria Lesson e conteúdo multimídia',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/lessons',
            'body' => [
                'course_module_id' => fn (array $ctx): int => $ctx['fixtures']['moduleId'],
                'title' => 'E2E S02 Lesson',
                'is_free' => false,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['lessonId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor adiciona mídia e material',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/lessons/'.$ctx['fixtures']['lessonId'].'/media',
            'body' => [
                'media_type' => 'video',
                'provider' => 'embed',
                'provider_ref' => 'e2e-s02-player',
                'url' => 'https://video.example/e2e-s02-player',
                'duration_seconds' => 100,
                'progress_strategy' => 'full_duration',
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['mediaId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor registra material configurado',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/courses/'.$ctx['fixtures']['courseId'].'/materials',
            'body' => ['file_path' => fn (array $ctx): string => 'tenants/'.$ctx['tenant']->id.'/materials/e2e-s02.pdf'],
            'expect' => ['status' => 201],
            'capture' => function (array $ctx): array {
                $materialId = (int) $ctx['response']->json('data.id');
                $material = CourseMaterial::query()->findOrFail($materialId);
                Storage::disk(config('filesystems.default'))->put($material->file_path, 'e2e s02 material');

                return ['materialId' => $materialId];
            },
        ],
        [
            'name' => 'Admin administra o Course do Instructor',
            'as' => 'admin',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['courseId']]],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['courseId']);

                return [
                    'ownership preservado no banco' => [$ctx['users']['instructor']->id, $course?->instructor_id],
                    'ownership não exposto no Resource Admin' => [null, $ctx['response']->json('data.instructor_id')],
                ];
            },
        ],
        [
            'name' => 'Admin publica Lesson e Course',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/lessons/'.$ctx['fixtures']['lessonId'].'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
            'db' => fn (array $ctx): array => [
                'lesson publicada' => ['published', Lesson::query()->find($ctx['fixtures']['lessonId'])?->status],
            ],
        ],
        [
            'name' => 'Admin fecha publicação do Course',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/courses/'.$ctx['fixtures']['courseId'].'/publish',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'published']],
            'db' => function (array $ctx): array {
                $course = Course::query()->find($ctx['fixtures']['courseId']);

                return ['course publicado e ativo' => [true, $course?->is_active === true]];
            },
        ],
        [
            'name' => 'Admin confirma pagamento cash manual e ativa Enrollment via outbox',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['orderId'].'/confirm-manual-payment',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'paid']],
            'capture' => function (array $ctx): array {
                $enrollment = Enrollment::query()
                    ->where('tenant_id', $ctx['tenant']->id)
                    ->where('user_id', $ctx['users']['student']->id)
                    ->where('course_id', $ctx['fixtures']['courseId'])
                    ->sole();

                return ['enrollmentId' => $enrollment->id];
            },
            'db' => function (array $ctx): array {
                $order = Order::query()->find($ctx['fixtures']['orderId']);
                $payment = Payment::query()->find($ctx['fixtures']['paymentId']);
                $enrollment = Enrollment::query()->find($ctx['fixtures']['enrollmentId']);

                return [
                    'order paid' => ['paid', $order?->status],
                    'cash payment resolved' => ['completed', $payment?->status],
                    'outbox dispatched' => [true, OrderPaidOutbox::query()->where('order_id', $order?->id)->first()?->dispatched_at !== null],
                    'Enrollment active' => ['active', $enrollment?->status],
                    'Enrollment própria' => [$ctx['users']['student']->id, $enrollment?->user_id],
                ];
            },
        ],
        [
            'name' => 'Student My Courses retorna somente Course próprio ativo',
            'as' => 'student',
            'method' => 'GET',
            'path' => '/api/v1/student/courses',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['courseId']]],
        ],
        [
            'name' => 'Student navega Course, Module e Lesson',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 200, 'json' => ['data.enrollment.status' => 'active']],
        ],
        [
            'name' => 'Student lê árvore paginada sem draft/inactive',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'].'/modules/'.$ctx['fixtures']['moduleId'].'/lessons',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['lessonId']]],
        ],
        [
            'name' => 'Student consome conteúdo e mídia sem path interno',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lessonId'],
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.media.0.id' => fn (array $ctx): int => $ctx['fixtures']['mediaId'],
                    'data.media.0.url' => 'https://video.example/e2e-s02-player',
                ],
            ],
            'db' => fn (array $ctx): array => [
                'sem provider_ref na resposta' => [null, $ctx['response']->json('data.media.0.provider_ref')],
                'sem storage_path na resposta' => [null, $ctx['response']->json('data.media.0.storage_path')],
            ],
        ],
        [
            'name' => 'Student lista e baixa material com URL temporária',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'].'/materials',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['materialId']]],
        ],
        [
            'name' => 'Student download não expõe storage path',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'].'/materials/'.$ctx['fixtures']['materialId'].'/downloads',
            'expect' => ['status' => 201, 'json' => ['data.course_material_id' => fn (array $ctx): int => $ctx['fixtures']['materialId']]],
            'db' => fn (array $ctx): array => [
                'download persistido para Student' => [1, \App\Modules\Learning\Models\MaterialDownload::query()->where('user_id', $ctx['users']['student']->id)->where('course_material_id', $ctx['fixtures']['materialId'])->count()],
                'storage path ausente' => [null, $ctx['response']->json('data.file_path')],
            ],
        ],
        [
            'name' => 'Student grava progresso parcial próprio',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lessonId'].'/progress',
            'body' => ['time_spent_seconds' => 40, 'current_time_seconds' => 40, 'total_time_seconds' => 100, 'progress_percentage' => 40, 'is_completed' => false],
            'expect' => ['status' => 201, 'json' => ['data.progress_percentage' => 40]],
            'db' => fn (array $ctx): array => [
                'progress own persistido' => [1, LessonProgress::query()->where('tenant_id', $ctx['tenant']->id)->where('user_id', $ctx['users']['student']->id)->where('lesson_id', $ctx['fixtures']['lessonId'])->count()],
            ],
        ],
        [
            'name' => 'Student conclui Lesson e aggregate Course chega a 100%',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lessonId'].'/progress',
            'body' => ['time_spent_seconds' => 100, 'current_time_seconds' => 100, 'total_time_seconds' => 100, 'progress_percentage' => 100, 'is_completed' => true],
            'expect' => ['status' => 200, 'json' => ['data.is_completed' => true]],
            'db' => function (array $ctx): array {
                $enrollment = Enrollment::query()->find($ctx['fixtures']['enrollmentId']);

                return [
                    'Lesson completed' => [true, LessonProgress::query()->where('lesson_id', $ctx['fixtures']['lessonId'])->where('user_id', $ctx['users']['student']->id)->first()?->isCompleted()],
                    'Course aggregate 100' => [100, $enrollment?->progress_percentage],
                    'Course completed_at' => [true, $enrollment?->completed_at !== null],
                ];
            },
        ],
        [
            'name' => 'Replay/rewatch não regride conclusão nem duplica progress row',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lessonId'].'/progress',
            'body' => ['time_spent_seconds' => 10, 'current_time_seconds' => 10, 'total_time_seconds' => 100, 'progress_percentage' => 10, 'is_completed' => false],
            'expect' => ['status' => 200, 'json' => ['data.progress_percentage' => 100, 'data.is_completed' => true]],
            'db' => fn (array $ctx): array => [
                'uma única linha de progress' => [1, LessonProgress::query()->where('enrollment_id', $ctx['fixtures']['enrollmentId'])->where('lesson_id', $ctx['fixtures']['lessonId'])->count()],
                'aggregate permanece 100' => [100, Enrollment::query()->find($ctx['fixtures']['enrollmentId'])?->progress_percentage],
            ],
        ],
        [
            'name' => 'Instructor consulta roster e progresso do próprio Course',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/enrollments/'.$ctx['fixtures']['enrollmentId'].'/progress',
            'expect' => ['status' => 200, 'json' => ['data.progress.percentage' => 100]],
            'db' => fn (array $ctx): array => [
                'Student aparece no roster' => [$ctx['users']['student']->id, $ctx['response']->json('data.user.id')],
                'email não aparece' => [null, $ctx['response']->json('data.user.email')],
                'Lesson completed no roster' => [true, $ctx['response']->json('data.progress.lessons.0.is_completed')],
            ],
        ],
        [
            'name' => 'Student B não lê nem altera progresso de Student A',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['studentBToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Instructor B não acessa Course/progresso do Instructor A',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/enrollments/'.$ctx['fixtures']['enrollmentId'].'/progress',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Student de tenant B não acessa Course do tenant A',
            'tenant' => 'other',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['foreignStudentToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['courseId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Pending, expired e cancelled não consomem',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['pendingLessonId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Expired não consome',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['expiredLessonId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Cancelled não consome',
            'as' => 'student',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['cancelledLessonId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Área Student exige autenticação',
            'method' => 'GET',
            'path' => '/api/v1/student/courses',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
        [
            'name' => 'Área Student rejeita Admin por area guard',
            'as' => 'admin',
            'method' => 'GET',
            'path' => '/api/v1/student/courses',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $tenantIds = [$ctx['tenant']->id, $ctx['otherTenant']->id];
        $courseIds = array_filter([
            $ctx['fixtures']['courseId'] ?? null,
            ...array_map(fn (string $status): mixed => Course::query()->where('title', 'E2E S02 '.ucfirst($status).' Course')->value('id'), ['pending', 'expired', 'cancelled']),
        ]);
        $orderIds = array_filter([$ctx['fixtures']['orderId'] ?? null]);

        LessonMediaProgress::query()->whereIn('tenant_id', $tenantIds)->delete();
        LessonProgress::query()->whereIn('tenant_id', $tenantIds)->delete();
        Enrollment::query()->whereIn('tenant_id', $tenantIds)->delete();
        OrderPaidOutbox::query()->whereIn('order_id', $orderIds)->delete();
        Payment::query()->whereIn('order_id', $orderIds)->delete();
        OrderItem::query()->whereIn('order_id', $orderIds)->delete();
        Order::query()->whereIn('id', $orderIds)->delete();
        CourseMaterial::query()->whereIn('course_id', $courseIds)->delete();
        LessonMedia::query()->whereIn('tenant_id', $tenantIds)->whereIn('lesson_id', Lesson::query()->whereIn('course_module_id', CourseModule::query()->whereIn('course_id', $courseIds)->pluck('id'))->pluck('id'))->delete();
        Lesson::query()->whereIn('course_module_id', CourseModule::query()->whereIn('course_id', $courseIds)->pluck('id'))->delete();
        CourseModule::query()->whereIn('course_id', $courseIds)->delete();
        Course::query()->whereIn('id', $courseIds)->forceDelete();
        Storage::disk(config('filesystems.default'))->delete('tenants/'.$ctx['tenant']->id.'/materials/e2e-s02.pdf');

        foreach (['instructorB', 'studentB', 'foreignStudent'] as $fixture) {
            $user = $ctx['fixtures'][$fixture] ?? null;
            if ($user instanceof User) {
                $user->tokens()->delete();
                $user->forceDelete();
            }
        }
    },
];
