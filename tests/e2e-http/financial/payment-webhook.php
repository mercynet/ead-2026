<?php

declare(strict_types=1);

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

/** @return array<string, string> */
function e2ePaymentWebhookPayload(array $ctx, string $status, string $orderKey = 'order'): array
{
    return [
        'status' => $status,
        'order_number' => $ctx['fixtures'][$orderKey]->order_number,
        'external_id' => $ctx['fixtures'][$orderKey === 'order' ? 'payment' : 'failedPayment']->external_id,
    ];
}

return [
    'endpoint' => 'POST /api/v1/webhooks/gateways/e2e-webhook',

    'setup' => function (array $ctx): array {
        $plugin = Plugin::factory()->published()->gateway('e2e-webhook')->create();
        PluginActivation::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'plugin_id' => $plugin->id,
            'status' => 'active',
        ]);
        $secret = 'e2e-webhook-secret';
        $config = TenantPluginConfig::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'plugin_id' => $plugin->id,
            'enabled' => true,
            'config' => ['webhook_secret' => $secret],
        ]);
        $order = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'status' => 'pending',
            'origin_type' => 'direct',
            'order_number' => 'ORD-E2E-WEBHOOK-PAID',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'gateway_slug' => 'e2e-webhook',
            'confirmation_mode' => 'automatic',
            'external_id' => 'e2e-paid-payment',
            'tenant_plugin_config_id' => $config->id,
            'gateway_configuration_version' => $config->configuration_version,
            'charge_state' => 'created',
        ]);
        $course = Course::factory()->create(['tenant_id' => $ctx['tenant']->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'item_snapshot' => ['title' => $course->title],
            'price_cents' => 12900,
        ]);
        $failedOrder = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'status' => 'pending',
            'origin_type' => 'direct',
            'order_number' => 'ORD-E2E-WEBHOOK-FAILED',
        ]);
        $failedPayment = Payment::factory()->create([
            'order_id' => $failedOrder->id,
            'status' => 'pending',
            'gateway_slug' => 'e2e-webhook',
            'confirmation_mode' => 'automatic',
            'external_id' => 'e2e-failed-payment',
            'tenant_plugin_config_id' => $config->id,
            'gateway_configuration_version' => $config->configuration_version,
            'charge_state' => 'created',
        ]);

        return compact('order', 'payment', 'failedOrder', 'failedPayment', 'config', 'plugin', 'secret', 'course');
    },

    'cases' => [
        [
            'name' => 'webhook assinado é público e completa o pagamento',
            'tenant' => null,
            'body' => fn (array $ctx): array => e2ePaymentWebhookPayload($ctx, 'paid'),
            'headers' => [
                'X-Webhook-Signature' => fn (array $ctx): string => 'sha256='.hash_hmac(
                    'sha256',
                    json_encode(e2ePaymentWebhookPayload($ctx, 'paid'), JSON_THROW_ON_ERROR),
                    $ctx['fixtures']['secret'],
                ),
            ],
            'expect' => [
                'status' => 202,
                'json' => ['data.accepted' => true],
            ],
            'db' => fn (array $ctx): array => [
                'pedido pago' => ['paid', $ctx['fixtures']['order']->fresh()->status],
                'pagamento concluído' => ['completed', $ctx['fixtures']['payment']->fresh()->status],
                'outbox publicado' => [true, OrderPaidOutbox::query()->where('order_id', $ctx['fixtures']['order']->id)->first()?->dispatched_at !== null],
                'matrícula ativa' => ['active', Enrollment::query()->where('tenant_id', $ctx['tenant']->id)->where('user_id', $ctx['users']['student']->id)->where('course_id', $ctx['fixtures']['course']->id)->value('status')],
            ],
        ],
        [
            'name' => 'replay do webhook pago não duplica outbox',
            'tenant' => null,
            'body' => fn (array $ctx): array => e2ePaymentWebhookPayload($ctx, 'paid'),
            'headers' => [
                'X-Webhook-Signature' => fn (array $ctx): string => 'sha256='.hash_hmac(
                    'sha256',
                    json_encode(e2ePaymentWebhookPayload($ctx, 'paid'), JSON_THROW_ON_ERROR),
                    $ctx['fixtures']['secret'],
                ),
            ],
            'expect' => ['status' => 202],
            'db' => fn (array $ctx): array => [
                'um outbox' => [1, OrderPaidOutbox::query()->where('order_id', $ctx['fixtures']['order']->id)->count()],
            ],
        ],
        [
            'name' => 'webhook falho registra pagamento sem outbox pago',
            'tenant' => null,
            'body' => fn (array $ctx): array => e2ePaymentWebhookPayload($ctx, 'failed', 'failedOrder'),
            'headers' => [
                'X-Webhook-Signature' => fn (array $ctx): string => 'sha256='.hash_hmac(
                    'sha256',
                    json_encode(e2ePaymentWebhookPayload($ctx, 'failed', 'failedOrder'), JSON_THROW_ON_ERROR),
                    $ctx['fixtures']['secret'],
                ),
            ],
            'expect' => ['status' => 202],
            'db' => fn (array $ctx): array => [
                'pedido falho' => ['failed', $ctx['fixtures']['failedOrder']->fresh()->status],
                'sem outbox adicional' => [1, OrderPaidOutbox::query()->count()],
            ],
        ],
        [
            'name' => 'assinatura inválida não muta o pagamento',
            'tenant' => null,
            'body' => fn (array $ctx): array => e2ePaymentWebhookPayload($ctx, 'paid'),
            'headers' => ['X-Webhook-Signature' => 'sha256='.str_repeat('0', 64)],
            'expect' => [
                'status' => 422,
                'json' => ['errors.0.code' => 'validation_error'],
            ],
            'db' => fn (array $ctx): array => [
                'pagamento permanece concluído' => ['completed', $ctx['fixtures']['payment']->fresh()->status],
            ],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $orderIds = [$ctx['fixtures']['order']->id, $ctx['fixtures']['failedOrder']->id];
        Payment::query()->whereIn('order_id', $orderIds)->delete();
        OrderPaidOutbox::query()->whereIn('order_id', $orderIds)->delete();
        Order::query()->whereIn('id', $orderIds)->delete();
        Enrollment::query()->where('course_id', $ctx['fixtures']['course']->id)->delete();
        Course::query()->whereKey($ctx['fixtures']['course']->id)->delete();
        TenantPluginConfig::query()->whereKey($ctx['fixtures']['config']->id)->delete();
        PluginActivation::query()->where('plugin_id', $ctx['fixtures']['plugin']->id)->delete();
        Plugin::query()->whereKey($ctx['fixtures']['plugin']->id)->delete();
    },
];
