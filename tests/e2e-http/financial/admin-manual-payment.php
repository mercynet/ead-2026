<?php

declare(strict_types=1);

use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

return [
    'endpoint' => 'POST /api/v1/admin/orders/{order}/confirm-manual-payment',

    'setup' => function (array $ctx): array {
        $manualCourse = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'E2E Admin Enrollment Course',
            'slug' => 'e2e-admin-enrollment-course-'.strtolower(bin2hex(random_bytes(4))),
            'description' => 'Course for the Admin enrollment E2E flow.',
            'status' => 'published',
            'price_cents' => 0,
            'access_days' => 30,
            'is_featured' => false,
        ]);

        $course = Course::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'title' => 'E2E Manual Payment Course',
            'slug' => 'e2e-manual-payment-course-'.strtolower(bin2hex(random_bytes(4))),
            'description' => 'Course for the manual payment E2E flow.',
            'status' => 'published',
            'price_cents' => 12900,
            'access_days' => 30,
            'is_featured' => false,
        ]);

        $order = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'status' => 'pending',
            'total_cents' => 12900,
            'metadata' => ['e2e' => true],
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'item_snapshot' => ['title' => $course->title],
            'price_cents' => 12900,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'gateway_slug' => 'cash',
            'confirmation_mode' => 'manual',
            'gateway_response' => ['payment_method' => 'cash'],
        ]);

        $foreignOrder = Order::factory()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'user_id' => $ctx['users']['otherAdmin']->id,
            'status' => 'pending',
            'total_cents' => 12900,
            'metadata' => ['e2e' => true],
        ]);

        return compact('manualCourse', 'course', 'order', 'payment', 'foreignOrder');
    },

    'cases' => [
        [
            'name' => 'admin cria matrícula manual na superfície Admin',
            'as' => 'admin',
            'method' => 'POST',
            'path' => '/api/v1/admin/enrollments',
            'body' => fn (array $ctx): array => [
                'course_id' => $ctx['fixtures']['manualCourse']->id,
                'user_id' => $ctx['users']['student']->id,
            ],
            'expect' => ['status' => 201, 'json' => ['data.status' => 'active']],
            'capture' => fn (array $ctx): array => ['manualEnrollmentId' => $ctx['response']->json('data.id')],
            'db' => function (array $ctx): array {
                $enrollment = Enrollment::query()->find($ctx['fixtures']['manualEnrollmentId']);

                return [
                    'matrícula no tenant primário' => [$ctx['tenant']->id, $enrollment?->tenant_id],
                    'matrícula sem instrutor' => [null, $enrollment?->created_by_instructor_id],
                    'espelho financeiro criado' => [true, Order::query()->where('source_key', 'learning:enrollment:'.$enrollment?->id)->exists()],
                ];
            },
        ],
        [
            'name' => 'admin confirma cash manual e cria matrícula via outbox',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['order']->id.'/confirm-manual-payment',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'paid']],
            'db' => function (array $ctx): array {
                return [
                    'order pago' => ['paid', $ctx['fixtures']['order']->fresh()->status],
                    'payment concluído' => ['completed', $ctx['fixtures']['payment']->fresh()->status],
                    'outbox publicado' => [true, OrderPaidOutbox::query()->where('order_id', $ctx['fixtures']['order']->id)->first()?->dispatched_at !== null],
                    'matrícula única' => [1, Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->where('course_id', $ctx['fixtures']['course']->id)->where('user_id', $ctx['users']['student']->id)->count()],
                ];
            },
        ],
        [
            'name' => 'replay da confirmação manual é idempotente',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['order']->id.'/confirm-manual-payment',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'paid']],
            'db' => fn (array $ctx): array => [
                'um único outbox' => [1, OrderPaidOutbox::query()->where('order_id', $ctx['fixtures']['order']->id)->count()],
                'uma única matrícula' => [1, Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->where('course_id', $ctx['fixtures']['course']->id)->where('user_id', $ctx['users']['student']->id)->count()],
            ],
        ],
        [
            'name' => 'admin não confirma pedido de outro tenant',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['foreignOrder']->id.'/confirm-manual-payment',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'instructor é barrado da confirmação manual',
            'as' => 'instructor',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['order']->id.'/confirm-manual-payment',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'sem auth é barrado da confirmação manual',
            'path' => fn (array $ctx): string => '/api/v1/admin/orders/'.$ctx['fixtures']['order']->id.'/confirm-manual-payment',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $courseId = $ctx['fixtures']['course']->id;
        $manualCourseId = $ctx['fixtures']['manualCourse']->id;
        $manualEnrollmentId = $ctx['fixtures']['manualEnrollmentId'] ?? null;
        $manualOrder = $manualEnrollmentId === null
            ? null
            : Order::query()->where('source_key', 'learning:enrollment:'.$manualEnrollmentId)->first();
        $orderIds = array_filter([
            $ctx['fixtures']['order']->id,
            $ctx['fixtures']['foreignOrder']->id,
            $manualOrder?->id,
        ]);

        Enrollment::query()->whereIn('course_id', [$courseId, $manualCourseId])->delete();
        OrderPaidOutbox::query()->whereIn('order_id', $orderIds)->delete();
        Payment::query()->whereIn('order_id', $orderIds)->delete();
        OrderItem::query()->whereIn('order_id', $orderIds)->delete();
        Order::query()->whereIn('id', $orderIds)->delete();
        Course::query()->whereIn('id', [$courseId, $manualCourseId])->delete();
    },
];
