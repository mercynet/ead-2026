<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
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
        'external_id',
        'gateway_response',
        'metadata',
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
        'external_id' => 'pay_123',
        'gateway_response' => ['ok' => true],
        'metadata' => ['method' => 'internal'],
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($order->payments)->toHaveCount(1)
        ->and($item->itemable->is($itemable))->toBeTrue()
        ->and($payment->order->is($order))->toBeTrue();
});
