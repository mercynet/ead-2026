<?php

namespace App\Console\Commands;

use App\Modules\Financial\Services\Outbox\OrderPaidOutboxService;
use Illuminate\Console\Command;

class DrainOrderPaidOutboxCommand extends Command
{
    protected $signature = 'financial:drain-order-paid-outbox {--limit=100}';

    protected $description = 'Publishes pending financial OrderPaid outbox messages.';

    public function handle(OrderPaidOutboxService $outbox): int
    {
        $result = $outbox->drain(max(1, (int) $this->option('limit')));
        $this->info("OrderPaid outbox published: {$result['published']}; failed: {$result['failed']}");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
