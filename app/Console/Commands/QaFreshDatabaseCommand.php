<?php

namespace App\Console\Commands;

use App\Shared\Database\DestructiveDatabaseGuard;
use Illuminate\Console\Command;

class QaFreshDatabaseCommand extends Command
{
    protected $signature = 'qa:fresh';

    protected $description = 'Recria o banco de QA somente quando a identidade descartável de testing estiver comprovada';

    public function handle(DestructiveDatabaseGuard $guard): int
    {
        $reason = $guard->denialReason('testing');

        if ($reason !== null) {
            $this->error('qa:fresh recusado: '.$reason.'.');

            return self::FAILURE;
        }

        $status = $this->callSilent('migrate:fresh', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($status !== self::SUCCESS) {
            $this->error('qa:fresh falhou; o banco de QA não foi considerado preparado.');

            return self::FAILURE;
        }

        $this->info('qa:fresh concluído no banco de testing allowlisted.');

        return self::SUCCESS;
    }
}
