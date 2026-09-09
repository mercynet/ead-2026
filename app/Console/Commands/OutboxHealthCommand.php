<?php

namespace App\Console\Commands;

use App\Modules\Financial\Models\OrderPaidOutbox;
use Illuminate\Console\Command;

class OutboxHealthCommand extends Command
{
    protected $signature = 'financial:outbox-health {--max-age=300 : Maximum age of a pending message in seconds}';

    protected $description = 'Checks the pending and failed OrderPaid outbox backlog.';

    public function handle(): int
    {
        $pending = OrderPaidOutbox::query()->whereNull('dispatched_at')->count();
        $failed = OrderPaidOutbox::query()
            ->whereNull('dispatched_at')
            ->whereNotNull('last_failed_at')
            ->count();
        $stale = OrderPaidOutbox::query()
            ->whereNull('dispatched_at')
            ->where('created_at', '<', now()->subSeconds(max(1, (int) $this->option('max-age'))))
            ->count();

        $this->line("pending={$pending}");
        $this->line("failed={$failed}");
        $this->line("stale={$stale}");

        if ($failed > 0 || $stale > 0) {
            $this->error('outbox=FAIL');

            return self::FAILURE;
        }

        $this->info('outbox=PASS');

        return self::SUCCESS;
    }
}
