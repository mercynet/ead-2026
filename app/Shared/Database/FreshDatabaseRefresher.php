<?php

namespace App\Shared\Database;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;

class FreshDatabaseRefresher
{
    public function __construct(private readonly Kernel $kernel) {}

    public function refresh(): bool
    {
        return $this->kernel->call('migrate:fresh', [
            '--force' => true,
            '--no-interaction' => true,
        ]) === Command::SUCCESS;
    }
}
