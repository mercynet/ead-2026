<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @return array{root: string, manifest: string}
 */
function makeBackupMonitorFixture(bool $signed = true): array
{
    $root = sys_get_temp_dir().'/ead2026-backup-monitor-'.bin2hex(random_bytes(6));
    $backupId = gmdate('Ymd\THis\Z').'-fixture';
    $backup = $root.'/'.$backupId;

    mkdir($backup, 0700, true);
    file_put_contents($backup.'/database.sql.gz', 'database-fixture');
    file_put_contents($backup.'/storage.tar.gz', 'storage-fixture');
    $manifest = implode(PHP_EOL, [
        'status=PASS',
        "backup_id={$backupId}",
        'timestamp_utc='.gmdate('Y-m-d\TH:i:s\Z'),
        'rc_sha=0123456789abcdef0123456789abcdef01234567',
        'migration_count=73',
        'producer=ops03-backup-v2',
        'compose_project=ead2026-production',
        'database=ead2026_production',
        'storage_volume=ead2026-production-storage',
        'db_checksum='.hash_file('sha256', $backup.'/database.sql.gz'),
        'storage_checksum='.hash_file('sha256', $backup.'/storage.tar.gz'),
        'error=',
        '',
    ]);
    $signature = $signed ? hash('sha256', $manifest."manifest_key=monitor-test-key\n") : 'invalid';
    file_put_contents($backup.'/manifest.txt', $manifest."manifest_signature={$signature}\n");

    return ['root' => $root, 'manifest' => $backup.'/manifest.txt'];
}

function removeBackupMonitorFixture(string $root): void
{
    if (! is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }

    rmdir($root);
}

function runBackupMonitorFixture(array $fixture): Process
{
    $process = new Process([
        base_path('scripts/ops/ops04-backup-monitor.sh'),
    ], base_path(), [
        'OPS04_BACKUP_ROOT' => $fixture['root'],
        'OPS04_EXPECTED_RC_SHA' => '0123456789abcdef0123456789abcdef01234567',
        'OPS04_EXPECTED_COMPOSE_PROJECT' => 'ead2026-production',
        'OPS04_EXPECTED_DB_DATABASE' => 'ead2026_production',
        'OPS04_EXPECTED_STORAGE_VOLUME' => 'ead2026-production-storage',
        'OPS04_BACKUP_MANIFEST_KEY' => 'monitor-test-key',
        'OPS04_ALERT_OWNER' => '',
    ]);
    $process->run();

    return $process;
}

it('accepts only a signed backup bound to the expected release identity', function (): void {
    $fixture = makeBackupMonitorFixture();

    try {
        $process = runBackupMonitorFixture($fixture);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('backup_monitor=PASS');
    } finally {
        removeBackupMonitorFixture($fixture['root']);
    }
});

it('rejects a PASS manifest without valid provenance', function (): void {
    $fixture = makeBackupMonitorFixture(false);

    try {
        $process = runBackupMonitorFixture($fixture);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getOutput().$process->getErrorOutput())->toContain('problem_detected');
    } finally {
        removeBackupMonitorFixture($fixture['root']);
    }
});
