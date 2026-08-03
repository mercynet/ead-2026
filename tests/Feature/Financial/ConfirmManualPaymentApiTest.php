<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Models\Payment;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

function pendingManualCashOrder(Tenant $tenant): array
{
    $student = User::factory()->student()->forTenant($tenant)->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'status' => 'pending',
        'total_cents' => 12900,
        'metadata' => ['internal_note' => 'never expose'],
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'itemable_type' => User::class,
        'itemable_id' => $student->id,
        'item_snapshot' => ['title' => 'Curso de teste'],
        'price_cents' => 12900,
    ]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'pending',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
        'gateway_response' => [
            'payment_method' => 'cash',
            'payment_flow' => 'manual_confirmation_required',
            'secret' => 'never expose',
        ],
    ]);

    return [$order, $payment, $student];
}

it('confirms a pending manual cash payment through its outbox and enrolls once', function (): void {
    $tenant = makeTenant();
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment, $student] = pendingManualCashOrder($tenant);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso pago',
        'slug' => 'curso-pago',
        'description' => 'Descrição do curso pago',
        'status' => 'published',
        'price_cents' => 12900,
        'access_days' => 30,
        'is_featured' => false,
    ]);
    $order->items()->update([
        'itemable_type' => Course::class,
        'itemable_id' => $course->id,
        'item_snapshot' => ['title' => $course->title],
    ]);

    $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total_cents', 12900)
        ->assertJsonMissing(['never expose', 'gateway_response', 'metadata']);

    expect($order->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->status)->toBe('completed')
        ->and($payment->fresh()->charge_state)->toBe('resolved')
        ->and($payment->fresh()->charge_claim_token)->toBeNull()
        ->and($payment->fresh()->charge_claimed_at)->toBeNull()
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->firstOrFail()->dispatched_at)->not->toBeNull()
        ->and(Enrollment::query()->where('tenant_id', $tenant->id)->where('course_id', $course->id)->where('user_id', $student->id)->count())->toBe(1);

    $orderAudit = Activity::query()->where('subject_type', Order::class)->where('subject_id', $order->id)->where('causer_id', $admin->id)->where('event', 'updated')->firstOrFail();
    $paymentAudit = Activity::query()->where('subject_type', Payment::class)->where('subject_id', $payment->id)->where('causer_id', $admin->id)->where('event', 'updated')->firstOrFail();

    expect($orderAudit->log_name)->toBe('financial')
        ->and($paymentAudit->log_name)->toBe('financial')
        ->and($paymentAudit->properties->toJson())->not->toContain('never expose')
        ->not->toContain('gateway_response');
});

it('is idempotent after a successful manual cash confirmation', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment] = pendingManualCashOrder($tenant);
    $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers)->assertSuccessful();
    $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'paid');

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(Activity::query()->where('subject_type', Order::class)->where('subject_id', $order->id)->where('event', 'updated')->count())->toBe(1)
        ->and(Activity::query()->where('subject_type', Payment::class)->where('subject_id', $payment->id)->where('event', 'updated')->count())->toBe(1)
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('records a missing outbox event for a previously paid valid manual cash order', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment] = pendingManualCashOrder($tenant);
    $order->update(['status' => 'paid']);
    $payment->update(['status' => 'completed']);

    $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'paid');

    expect(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->firstOrFail()->dispatched_at)->not->toBeNull()
        ->and(Activity::query()->where('subject_type', Order::class)->where('subject_id', $order->id)->where('event', 'updated')->count())->toBe(1)
        ->and(Activity::query()->where('subject_type', Payment::class)->where('subject_id', $payment->id)->where('event', 'updated')->count())->toBe(1);
});

it('requires authentication and manual payment confirmation permission', function (): void {
    $tenant = makeTenant();
    [$order] = pendingManualCashOrder($tenant);

    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], tenantHeaders($tenant)), 401, 'unauthenticated');

    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 403, 'area_forbidden');
});

it('hides missing and cross-tenant orders', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$otherOrder] = pendingManualCashOrder(makeTenant());

    assertApiErrorEnvelope($this->postJson('/api/v1/admin/orders/999999/confirm-manual-payment', [], $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$otherOrder->id}/confirm-manual-payment", [], $headers), 404, 'not_found');
});

it('rejects orders without a pending eligible manual cash payment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order] = pendingManualCashOrder($tenant);
    $order->payments()->update(['gateway_slug' => 'card', 'confirmation_mode' => 'automatic']);

    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');
});

it('rejects forged gateway response markers without authoritative manual cash fields', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment] = pendingManualCashOrder($tenant);
    $payment->update(['gateway_slug' => 'card', 'confirmation_mode' => 'automatic']);

    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');

    expect($order->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->status)->toBe('pending');
});

it('rejects ambiguous pending authoritative manual cash payments', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment] = pendingManualCashOrder($tenant);
    $duplicate = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'pending',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
    ]);
    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');

    expect($order->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->status)->toBe('pending')
        ->and($duplicate->fresh()->status)->toBe('pending')
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('rejects mixed-status authoritative manual cash payments for a pending order', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $pendingPayment] = pendingManualCashOrder($tenant);
    $completedPayment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'completed',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
    ]);
    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');

    expect($order->fresh()->status)->toBe('pending')
        ->and($pendingPayment->fresh()->status)->toBe('pending')
        ->and($completedPayment->fresh()->status)->toBe('completed')
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('rejects mixed-status authoritative manual cash payments for a paid order', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $completedPayment] = pendingManualCashOrder($tenant);
    $order->update(['status' => 'paid']);
    $completedPayment->update(['status' => 'completed']);
    $pendingPayment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'pending',
        'gateway_slug' => 'cash',
        'confirmation_mode' => 'manual',
    ]);
    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');

    expect($order->fresh()->status)->toBe('paid')
        ->and($completedPayment->fresh()->status)->toBe('completed')
        ->and($pendingPayment->fresh()->status)->toBe('pending')
        ->and(OrderPaidOutbox::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('keeps paid payment and pending outbox when publish fails, then recovers enrollment on drain', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order, $payment, $student] = pendingManualCashOrder($tenant);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso recuperado',
        'slug' => 'curso-recuperado',
        'description' => 'Descrição do curso recuperado',
        'status' => 'published',
        'price_cents' => 12900,
        'access_days' => 30,
        'is_featured' => false,
    ]);
    $order->items()->update(['itemable_type' => Course::class, 'itemable_id' => $course->id, 'item_snapshot' => ['title' => $course->title]]);
    /** @var Dispatcher&\Mockery\MockInterface $failingDispatcher */
    $failingDispatcher = Mockery::mock(Dispatcher::class);
    $failingDispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Listener failed with raw secret.'));
    app()->instance(OrderPaidOutboxService::class, new OrderPaidOutboxService(app(DatabaseManager::class), $failingDispatcher));
    $loggedContext = [];
    Log::shouldReceive('warning')->once()->with('OrderPaid outbox publish failed.', Mockery::on(function (array $context) use (&$loggedContext): bool {
        $loggedContext = $context;

        return true;
    }));

    $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers)
        ->assertSuccessful()
        ->assertJsonMissing(['never expose', 'raw secret', 'gateway_response', 'metadata']);

    $outbox = OrderPaidOutbox::query()->where('order_id', $order->id)->firstOrFail();

    expect($order->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->status)->toBe('completed')
        ->and($outbox->dispatched_at)->toBeNull()
        ->and($outbox->attempt_count)->toBe(1)
        ->and($outbox->last_error_class)->toBe(RuntimeException::class)
        ->and($loggedContext)->toBe(['order_id' => $order->id, 'outbox_id' => $outbox->id, 'exception_class' => RuntimeException::class])
        ->and(json_encode($loggedContext))->not->toContain('secret');

    $recovery = new OrderPaidOutboxService(app(DatabaseManager::class), app(Dispatcher::class));

    expect($recovery->drain())->toBe(['published' => 1, 'failed' => 0])
        ->and($outbox->fresh()->dispatched_at)->not->toBeNull()
        ->and(Enrollment::query()->where('tenant_id', $tenant->id)->where('course_id', $course->id)->where('user_id', $student->id)->count())->toBe(1);
});

it('returns not found for a nonnumeric order route id', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    assertApiErrorEnvelope($this->postJson('/api/v1/admin/orders/not-a-number/confirm-manual-payment', [], $headers), 404, 'not_found');
});

it('rejects non-pending orders and paid orders without a completed eligible cash payment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    [$order] = pendingManualCashOrder($tenant);
    $order->update(['status' => 'cancelled']);

    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');

    [$paidOrder] = pendingManualCashOrder($tenant);
    $paidOrder->update(['status' => 'paid']);

    assertApiErrorEnvelope($this->postJson("/api/v1/admin/orders/{$paidOrder->id}/confirm-manual-payment", [], $headers), 422, 'validation_error');
});
