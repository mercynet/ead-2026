<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the OrderPaid outbox drainer every minute without overlap', function (): void {
    $event = collect(app(Schedule::class)->events())->first(
        fn (Event $event): bool => str_contains($event->command, 'financial:drain-order-paid-outbox'),
    );

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
