<?php

namespace App\Modules\Financial\Events;

class OrderPaidEvent
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly int $orderId,
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly string $paidAt,
        public readonly array $items = [],
    ) {}
}
