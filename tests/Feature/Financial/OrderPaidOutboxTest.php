<?php

use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderPaidOutbox;
use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

function orderPaidEvent(Order $order): OrderPaidEvent
{
    return new OrderPaidEvent($order->id, $order->tenant_id, $order->user_id, '2026-07-28T12:00:00+00:00', [['itemable_type' => 'course']]);
}

function outboxService(Dispatcher $events): OrderPaidOutboxService
{
    return new OrderPaidOutboxService(app(DatabaseManager::class), $events);
}

it('records an OrderPaid event once with only its contract payload', function (): void {
    $event = orderPaidEvent(Order::factory()->create());
    $service = outboxService(Mockery::mock(Dispatcher::class));

    $first = $service->record($event);
    $second = $service->record($event);

    expect($second->id)->toBe($first->id)
        ->and(OrderPaidOutbox::query()->count())->toBe(1)
        ->and($first->payload)->toHaveKeys(['orderId', 'tenantId', 'userId', 'paidAt', 'items'])
        ->and($first->payload)->toHaveCount(5);
});

it('publishes a pending row once and marks it dispatched', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(Mockery::type(OrderPaidEvent::class));
    $service = outboxService($events);
    $outbox = $service->record(orderPaidEvent(Order::factory()->create()));

    expect($service->publish($outbox->id))->toBeTrue()
        ->and($service->publish($outbox->id))->toBeFalse()
        ->and($outbox->fresh()->dispatched_at)->not->toBeNull()
        ->and($outbox->fresh()->claim_token)->toBeNull();
});

it('releases failed claims, reclaims stale claims, and drains later rows after failure', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $failed = null;
    $events->shouldReceive('dispatch')->andReturnUsing(function (OrderPaidEvent $event) use (&$failed): void {
        if ($event->orderId === $failed) {
            throw new RuntimeException('dispatch failed');
        }
    });
    $service = outboxService($events);
    $first = $service->record(orderPaidEvent(Order::factory()->create()));
    $second = $service->record(orderPaidEvent(Order::factory()->create()));
    $failed = $first->order_id;

    expect(fn () => $service->publish($first->id))->toThrow(RuntimeException::class);
    expect($first->fresh()->claim_token)->toBeNull()
        ->and($first->fresh()->claimed_at)->toBeNull();

    $first->update(['claim_token' => (string) str()->uuid(), 'claimed_at' => Carbon::now()->subMinutes(6)]);
    $result = $service->drain(2);

    expect($result)->toBe(['published' => 1, 'failed' => 1])
        ->and($first->fresh()->dispatched_at)->toBeNull()
        ->and($second->fresh()->dispatched_at)->not->toBeNull();
});

it('retries immediately after a dispatch failure', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('failed'));
    $events->shouldReceive('dispatch')->once();
    $service = outboxService($events);
    $outbox = $service->record(orderPaidEvent(Order::factory()->create()));

    expect(fn () => $service->publish($outbox->id))->toThrow(RuntimeException::class);
    expect($outbox->fresh()->claim_token)->toBeNull()
        ->and($outbox->fresh()->claimed_at)->toBeNull();
    expect($service->publish($outbox->id))->toBeTrue()
        ->and($outbox->fresh()->dispatched_at)->not->toBeNull();
});

it('drains command within limit and returns its result', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once();
    $service = outboxService($events);
    $first = $service->record(orderPaidEvent(Order::factory()->create()));
    $second = $service->record(orderPaidEvent(Order::factory()->create()));
    app()->instance(OrderPaidOutboxService::class, $service);

    $this->artisan('financial:drain-order-paid-outbox', ['--limit' => 1])
        ->expectsOutput('OrderPaid outbox published: 1; failed: 0')
        ->assertExitCode(0);

    expect($first->fresh()->dispatched_at)->not->toBeNull()
        ->and($second->fresh()->dispatched_at)->toBeNull();
});

it('returns failure from drain command when publishing fails', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('dispatch failed'));
    $service = outboxService($events);
    $outbox = $service->record(orderPaidEvent(Order::factory()->create()));
    app()->instance(OrderPaidOutboxService::class, $service);

    $this->artisan('financial:drain-order-paid-outbox', ['--limit' => 1])
        ->expectsOutput('OrderPaid outbox published: 0; failed: 1')
        ->assertExitCode(1);

    expect($outbox->fresh()->dispatched_at)->toBeNull()
        ->and($outbox->fresh()->claim_token)->toBeNull();
});

it('logs only safe diagnostics when draining a failed outbox message', function (): void {
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('raw secret gateway payload'));
    $service = outboxService($events);
    $outbox = $service->record(orderPaidEvent(Order::factory()->create()));
    $outbox->update(['payload' => [...$outbox->payload, 'items' => [['secret' => 'raw-secret-value']]]]);
    $loggedContext = [];

    Log::shouldReceive('warning')->once()->with(
        'OrderPaid outbox publish failed.',
        Mockery::on(function (array $context) use (&$loggedContext): bool {
            $loggedContext = $context;

            return true;
        }),
    );

    expect($service->drain())->toBe(['published' => 0, 'failed' => 1])
        ->and($loggedContext)->toBe([
            'outbox_id' => $outbox->id,
            'order_id' => $outbox->order_id,
            'exception_class' => RuntimeException::class,
            'attempt_count' => 1,
        ])
        ->and(json_encode($loggedContext))->not->toContain('secret')
        ->not->toContain('raw');
});
