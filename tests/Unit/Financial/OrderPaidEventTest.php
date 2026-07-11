<?php

use App\Modules\Financial\Events\OrderPaidEvent;

it('carries a primitive payload for order paid events', function (): void {
    $event = new OrderPaidEvent(
        orderId: 10,
        tenantId: 20,
        userId: 30,
        paidAt: '2026-07-08T12:00:00Z',
        items: [
            [
                'itemable_type' => 'App\\Modules\\Core\\Models\\User',
                'itemable_id' => 1,
                'price_cents' => 2500,
            ],
        ],
    );

    expect($event->orderId)->toBe(10)
        ->and($event->tenantId)->toBe(20)
        ->and($event->userId)->toBe(30)
        ->and($event->paidAt)->toBe('2026-07-08T12:00:00Z')
        ->and($event->items)->toBeArray();
});
