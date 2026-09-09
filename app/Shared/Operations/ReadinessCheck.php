<?php

namespace App\Shared\Operations;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class ReadinessCheck
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly FilesystemManager $filesystem,
        private readonly Kernel $kernel,
    ) {}

    /**
     * @return array{status: string, timestamp: string, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'app' => $this->check(fn (): array => ['status' => 'pass']),
            'db' => $this->check(fn (): array => $this->checkDatabase()),
            'storage' => $this->check(fn (): array => $this->checkStorage()),
            'migration_manifest' => $this->check(fn (): array => $this->checkMigrations()),
            'outbox' => $this->check(fn (): array => $this->checkOutbox()),
            'queue' => $this->check(fn (): array => $this->checkQueue()),
        ];

        $failed = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'fail');

        return [
            'status' => $failed ? 'not_ready' : 'ready',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function check(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $exception) {
            Log::warning('readiness.check_failed', [
                'exception_class' => $exception::class,
            ]);

            return [
                'status' => 'fail',
                'reason' => 'dependency_unavailable',
            ];
        }
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        $this->database->connection()->getPdo();

        return ['status' => 'pass'];
    }

    /** @return array<string, mixed> */
    private function checkStorage(): array
    {
        $diskName = (string) config('filesystems.default');
        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $driver = is_array($diskConfig) ? (string) ($diskConfig['driver'] ?? '') : '';

        if ($driver === 'local') {
            $root = (string) ($diskConfig['root'] ?? '');

            if ($root === '' || ! is_dir($root) || ! is_readable($root) || ! is_writable($root)) {
                throw new \RuntimeException('local storage root unavailable');
            }
        } elseif ($driver === 's3') {
            if ((string) ($diskConfig['bucket'] ?? '') === '') {
                throw new \RuntimeException('object storage bucket is missing');
            }

            $this->filesystem->disk($diskName)->directoryExists('');
        } else {
            throw new \RuntimeException('unsupported readiness storage driver');
        }

        return ['status' => 'pass', 'disk' => $diskName, 'mutating' => false];
    }

    /** @return array<string, mixed> */
    private function checkMigrations(): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->kernel->call('ops:migrate', [
            '--check-only' => true,
            '--manifest' => (string) config('ops.migration_manifest'),
        ], $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException('migration manifest mismatch');
        }

        return [
            'status' => 'pass',
            'manifest' => (string) config('ops.migration_manifest'),
        ];
    }

    /** @return array<string, mixed> */
    private function checkOutbox(): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->kernel->call('financial:outbox-health', [
            '--max-age' => (int) config('ops.outbox_max_age_seconds'),
        ], $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException('outbox health failed');
        }

        return ['status' => 'pass'];
    }

    /** @return array<string, mixed> */
    private function checkQueue(): array
    {
        if (! (bool) config('ops.queue_required')) {
            return ['status' => 'not_required'];
        }

        if (! $this->database->connection()->getSchemaBuilder()->hasTable('jobs')) {
            throw new \RuntimeException('queue table is missing');
        }

        return ['status' => 'pass'];
    }
}
