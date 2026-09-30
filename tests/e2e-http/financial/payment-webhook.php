<?php

declare(strict_types=1);

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;

return [
    'endpoint' => 'POST /api/v1/webhooks/gateways/cash',

    'setup' => function (array $ctx): array {
        $plugin = Plugin::factory()->published()->gateway('cash')->create();
        PluginActivation::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'plugin_id' => $plugin->id,
            'status' => 'active',
        ]);
        $config = TenantPluginConfig::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'plugin_id' => $plugin->id,
            'enabled' => true,
            'config' => [],
        ]);
        $order = Order::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'status' => 'pending',
            'origin_type' => 'direct',
            'order_number' => 'ORD-E2E-WEBHOOK',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'gateway_slug' => 'cash',
            'confirmation_mode' => 'manual',
            'external_id' => 'cash-e2e-payment',
            'tenant_plugin_config_id' => $config->id,
            'gateway_configuration_version' => $config->configuration_version,
            'charge_state' => 'created',
        ]);

        return compact('order', 'payment', 'config', 'plugin');
    },

    'cases' => [
        [
            'name' => 'webhook público rejeita assinatura inválida sem autenticação',
            'tenant' => null,
            'body' => [
                'status' => 'paid',
                'order_number' => fn (array $ctx): string => $ctx['fixtures']['order']->order_number,
                'external_id' => fn (array $ctx): string => $ctx['fixtures']['payment']->external_id,
            ],
            'headers' => [
                'X-Webhook-Signature' => 'sha256='.str_repeat('0', 64),
            ],
            'expect' => [
                'status' => 422,
                'json' => ['errors.0.code' => 'validation_error'],
            ],
            'db' => fn (array $ctx): array => [
                'pedido continua pendente' => ['pending', $ctx['fixtures']['order']->fresh()->status],
            ],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $orderId = $ctx['fixtures']['order']->id;
        Payment::query()->where('order_id', $orderId)->delete();
        Order::query()->whereKey($orderId)->delete();
        TenantPluginConfig::query()->whereKey($ctx['fixtures']['config']->id)->delete();
        PluginActivation::query()->where('plugin_id', $ctx['fixtures']['plugin']->id)->delete();
        Plugin::query()->whereKey($ctx['fixtures']['plugin']->id)->delete();
    },
];
