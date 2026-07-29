<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('loads the financial ledger schema with money in cents', function (): void {
    expect(Schema::hasTable('orders'))->toBeTrue();
    expect(Schema::hasTable('order_items'))->toBeTrue();
    expect(Schema::hasTable('payments'))->toBeTrue();

    expect(Schema::hasColumns('orders', [
        'tenant_id',
        'user_id',
        'order_number',
        'status',
        'origin_type',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'source_key',
        'idempotency_key',
        'metadata',
    ]))->toBeTrue();

    expect(Schema::hasColumns('order_items', [
        'order_id',
        'itemable_type',
        'itemable_id',
        'item_snapshot',
        'price_cents',
    ]))->toBeTrue();

    expect(Schema::hasColumns('payments', [
        'order_id',
        'status',
        'gateway_slug',
        'confirmation_mode',
        'external_id',
        'gateway_response',
        'metadata',
        'tenant_plugin_config_id',
        'gateway_configuration_version',
        'psp_idempotency_key',
        'charge_state',
        'charge_claim_token',
        'charge_claimed_at',
    ]))->toBeTrue();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->student()->create();
    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'order_number' => 'ORD-1001',
        'status' => 'paid',
        'origin_type' => 'enrollment',
        'subtotal_cents' => 1000,
        'tax_cents' => 200,
        'total_cents' => 1200,
        'source_key' => 'course-1',
        'metadata' => ['kind' => 'ledger'],
    ]);

    expect($order->subtotal_cents)->toBeInt()->and($order->total_cents)->toBeInt();
});

it('enforces payment PSP and gateway external idempotency uniqueness', function (): void {
    $order = Order::factory()->create();
    $payment = Payment::factory()->for($order)->create([
        'gateway_slug' => 'stripe',
        'external_id' => 'ch_unique',
        'psp_idempotency_key' => 'psp_unique',
        'tenant_plugin_config_id' => 123,
        'gateway_configuration_version' => 'config-v1',
        'charge_state' => 'created',
    ]);

    expect($payment->tenant_plugin_config_id)->toBe(123)
        ->and($payment->gateway_configuration_version)->toBe('config-v1')
        ->and($payment->charge_state)->toBe('created');

    expect(fn () => Payment::factory()->create(['psp_idempotency_key' => 'psp_unique']))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => Payment::factory()->create(['gateway_slug' => 'stripe', 'external_id' => 'ch_unique']))
        ->toThrow(UniqueConstraintViolationException::class);

    Payment::factory()->create(['gateway_slug' => 'stripe', 'external_id' => null]);
    Payment::factory()->create(['gateway_slug' => 'stripe', 'external_id' => null]);
});

it('defaults newly created payments to the created charge state after historical backfill', function (): void {
    $payment = Payment::factory()->create()->fresh();

    expect($payment->charge_state)->toBe('created');
});

it('wires financial order relations and morph itemables', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->student()->create();
    $itemable = User::factory()->forTenant($tenant)->instructor()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'total_cents' => 2500,
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'itemable_type' => $itemable->getMorphClass(),
        'itemable_id' => $itemable->id,
        'item_snapshot' => ['name' => 'Course access'],
        'price_cents' => 2500,
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'status' => 'paid',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
        'external_id' => 'pay_123',
        'gateway_response' => ['ok' => true],
        'metadata' => ['method' => 'internal'],
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($order->payments)->toHaveCount(1)
        ->and($item->itemable->is($itemable))->toBeTrue()
        ->and($payment->order->is($order))->toBeTrue()
        ->and($payment->gateway_slug)->toBe('cash')
        ->and($payment->confirmation_mode)->toBe('manual');
});
