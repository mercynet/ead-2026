<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @return array{root: string, backup: string, log: string}
 */
function makeOps03RestoreSafetyFixture(): array
{
    $root = sys_get_temp_dir().'/ead2026-ops03-restore-'.bin2hex(random_bytes(6));
    $backup = $root.'/backup';
    $bin = $root.'/bin';
    $log = $root.'/docker.log';

    mkdir($backup, 0700, true);
    mkdir($bin, 0700, true);

    file_put_contents($backup.'/database.sql.gz', gzencode('database-fixture'));
    file_put_contents($backup.'/storage.tar.gz', gzencode('storage-fixture'));

    $dbChecksum = hash_file('sha256', $backup.'/database.sql.gz');
    $storageChecksum = hash_file('sha256', $backup.'/storage.tar.gz');

    $manifest = implode(PHP_EOL, [
        'status=PASS',
        'backup_id=fixture-restore-safety',
        'rc_sha=deadbeef',
        'migration_count=73',
        'producer=ops03-backup-v2',
        'compose_project=ead2026-ops03',
        'database=ead2026_ops03',
        'storage_volume=ead2026-ops03-storage',
        "db_checksum={$dbChecksum}",
        "storage_checksum={$storageChecksum}",
        'error=',
        '',
    ]);
    file_put_contents($backup.'/manifest.txt', $manifest.'manifest_signature='.hash('sha256', $manifest."manifest_key=restore-test-key\n").PHP_EOL);

    file_put_contents($bin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

printf '%s\n' "$*" >> "${OPS03_DOCKER_LOG}"

if [[ "$1" == "compose" && "$*" == *" ps -q db"* ]]; then
    printf 'fake-db\n'
    exit 0
fi

if [[ "$1" == "inspect" ]]; then
    printf 'healthy\n'
    exit 0
fi

if [[ "$1" == "volume" && "$2" == "inspect" ]]; then
    volume_name="${@: -1}"
    case "$volume_name" in
        ead2026-ops03-db)
            if [[ "${OPS03_VOLUME_IDENTITY_MODE:-}" == wrong-db ]]; then
                printf 'ead2026-ops03-db|unexpected-project|production_db_data\n'
            else
                printf 'ead2026-ops03-db|ead2026-ops03|production_db_data\n'
            fi
            ;;
        ead2026-ops03-storage) printf 'ead2026-ops03-storage|ead2026-ops03|production_storage\n' ;;
        ead2026-ops03-cache) printf 'ead2026-ops03-cache|ead2026-ops03|production_cache\n' ;;
    esac
    exit 0
fi

if [[ "$1" == "compose" && "$*" == *" exec -T db"* ]]; then
    cat >/dev/null
fi
BASH
    );
    chmod($bin.'/docker', 0700);

    return ['root' => $root, 'backup' => $backup, 'log' => $log];
}

function removeOps03RestoreSafetyFixture(string $root): void
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

it('rejects a non-disposable storage volume before invoking docker', function (): void {
    $fixture = makeOps03RestoreSafetyFixture();

    try {
        $process = new Process([
            base_path('scripts/ops/ops03-restore.sh'),
            $fixture['backup'],
        ], base_path(), [
            'PATH' => $fixture['root'].'/bin:'.(string) getenv('PATH'),
            'OPS03_DOCKER_LOG' => $fixture['log'],
            'OPS03_REHEARSAL' => 'true',
            'COMPOSE_PROJECT_NAME' => 'ead2026-ops03',
            'DB_DATABASE' => 'ead2026_ops03',
            'PRODUCTION_DB_VOLUME' => 'ead2026-ops03-db',
            'PRODUCTION_STORAGE_VOLUME' => 'production_storage',
            'PRODUCTION_CACHE_VOLUME' => 'ead2026-ops03-cache',
            'OPS04_BACKUP_MANIFEST_KEY' => 'restore-test-key',
            'COMPOSE_FILE' => $fixture['root'].'/compose.production.yaml',
        ]);
        $process->run();

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('disposable')
            ->and(is_file($fixture['log']))->toBeFalse();
    } finally {
        removeOps03RestoreSafetyFixture($fixture['root']);
    }
});

it('accepts only the inspected canonical disposable storage volume', function (): void {
    $fixture = makeOps03RestoreSafetyFixture();

    try {
        $process = new Process([
            base_path('scripts/ops/ops03-restore.sh'),
            $fixture['backup'],
        ], base_path(), [
            'PATH' => $fixture['root'].'/bin:'.(string) getenv('PATH'),
            'OPS03_DOCKER_LOG' => $fixture['log'],
            'OPS03_REHEARSAL' => 'true',
            'COMPOSE_PROJECT_NAME' => 'ead2026-ops03',
            'DB_DATABASE' => 'ead2026_ops03',
            'PRODUCTION_DB_VOLUME' => 'ead2026-ops03-db',
            'PRODUCTION_STORAGE_VOLUME' => 'ead2026-ops03-storage',
            'PRODUCTION_CACHE_VOLUME' => 'ead2026-ops03-cache',
            'OPS04_BACKUP_MANIFEST_KEY' => 'restore-test-key',
            'COMPOSE_FILE' => $fixture['root'].'/compose.production.yaml',
        ]);
        $process->run();

        $dockerCalls = file_get_contents($fixture['log']);

        expect($process->getExitCode(), $process->getErrorOutput().$process->getOutput())->toBe(0)
            ->and($dockerCalls)->toContain('volume inspect')
            ->and($dockerCalls)->toContain('src=ead2026-ops03-storage');
    } finally {
        removeOps03RestoreSafetyFixture($fixture['root']);
    }
});

it('rejects a non-disposable database volume before restore mutation', function (): void {
    $fixture = makeOps03RestoreSafetyFixture();

    try {
        $process = new Process([
            base_path('scripts/ops/ops03-restore.sh'),
            $fixture['backup'],
        ], base_path(), [
            'PATH' => $fixture['root'].'/bin:'.(string) getenv('PATH'),
            'OPS03_DOCKER_LOG' => $fixture['log'],
            'OPS03_VOLUME_IDENTITY_MODE' => 'wrong-db',
            'OPS03_REHEARSAL' => 'true',
            'COMPOSE_PROJECT_NAME' => 'ead2026-ops03',
            'DB_DATABASE' => 'ead2026_ops03',
            'PRODUCTION_DB_VOLUME' => 'ead2026-ops03-db',
            'PRODUCTION_STORAGE_VOLUME' => 'ead2026-ops03-storage',
            'PRODUCTION_CACHE_VOLUME' => 'ead2026-ops03-cache',
            'OPS04_BACKUP_MANIFEST_KEY' => 'restore-test-key',
            'COMPOSE_FILE' => $fixture['root'].'/compose.production.yaml',
        ]);
        $process->run();

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('unsafe')
            ->and(file_get_contents($fixture['log']))->toContain('volume inspect')
            ->and(file_get_contents($fixture['log']))->not->toContain(' up ')
            ->and(file_get_contents($fixture['log']))->not->toContain(' exec ')
            ->and(file_get_contents($fixture['log']))->not->toContain(' run ');
    } finally {
        removeOps03RestoreSafetyFixture($fixture['root']);
    }
});
