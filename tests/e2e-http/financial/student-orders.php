<?php

declare(strict_types=1);

use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;

return [
    'endpoint' => 'GET /api/v1/student/orders',

    'setup' => function (array $ctx): array {
        $order = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'status' => 'paid',
            'origin_type' => 'direct',
            'subtotal_cents' => 12900,
            'tax_cents' => 0,
            'total_cents' => 12900,
            'metadata' => ['e2e' => true],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'itemable_type' => 'course',
            'itemable_id' => 999,
            'item_snapshot' => ['title' => 'E2E Student Order Course'],
            'price_cents' => 12900,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'completed',
            'gateway_slug' => 'cash',
            'confirmation_mode' => 'manual',
            'charge_state' => 'resolved',
        ]);

        $otherStudent = User::factory()->student()->forTenant($ctx['tenant'])->create([
            'name' => 'E2E Other Student',
        ]);
        $otherOrder = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $otherStudent->id,
            'status' => 'paid',
            'origin_type' => 'direct',
            'total_cents' => 5000,
        ]);

        $foreignOrder = Order::factory()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'user_id' => $ctx['users']['otherAdmin']->id,
            'status' => 'paid',
            'origin_type' => 'direct',
            'total_cents' => 7000,
        ]);

        return compact('order', 'payment', 'otherStudent', 'otherOrder', 'foreignOrder');
    },

    'cases' => [
        [
            'name' => 'student lista somente os próprios pedidos',
            'as' => 'student',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.id' => fn (array $ctx): int => $ctx['fixtures']['order']->id,
                    'data.0.total_cents' => 12900,
                    'data.0.payments.0.status' => 'completed',
                ],
            ],
            'db' => fn (array $ctx): array => [
                'pedido permanece do aluno' => [$ctx['users']['student']->id, $ctx['fixtures']['order']->fresh()->user_id],
            ],
        ],
        [
            'name' => 'student vê o detalhe do próprio pedido',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/orders/'.$ctx['fixtures']['order']->id,
            'expect' => [
                'status' => 200,
                'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['order']->id],
            ],
        ],
        [
            'name' => 'student não vê pedido de outro aluno do tenant',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/orders/'.$ctx['fixtures']['otherOrder']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'student não vê pedido de outro tenant',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/orders/'.$ctx['fixtures']['foreignOrder']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'sem autenticação não acessa pedidos',
            'path' => fn (array $ctx): string => '/api/v1/student/orders/'.$ctx['fixtures']['order']->id,
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
        [
            'name' => 'instructor não alcança superfície Student',
            'as' => 'instructor',
            'path' => fn (array $ctx): string => '/api/v1/student/orders',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $orderIds = [
            $ctx['fixtures']['order']->id,
            $ctx['fixtures']['otherOrder']->id,
            $ctx['fixtures']['foreignOrder']->id,
        ];

        Payment::query()->whereIn('order_id', $orderIds)->delete();
        OrderItem::query()->whereIn('order_id', $orderIds)->delete();
        Order::query()->whereIn('id', $orderIds)->delete();
        $ctx['fixtures']['otherStudent']->delete();
    },
];
