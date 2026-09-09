<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @return array{root: string, backup: string, log: string}
 */
function makeOps03DeploySafetyFixture(): array
{
    $root = sys_get_temp_dir().'/ead2026-ops03-deploy-'.bin2hex(random_bytes(6));
    $backup = $root.'/backup';
    $bin = $root.'/bin';
    $log = $root.'/docker.log';

    mkdir($backup, 0700, true);
    mkdir($bin, 0700, true);
    file_put_contents($backup.'/database.sql.gz', gzencode('database-fixture'));
    file_put_contents($backup.'/storage.tar.gz', gzencode('storage-fixture'));
    $manifest = implode(PHP_EOL, [
        'status=PASS',
        'backup_id=fixture-deploy-safety',
        'rc_sha=deadbeef',
        'migration_count=73',
        'producer=ops03-backup-v2',
        'compose_project=ead2026-ops03',
        'database=ead2026_ops03',
        'storage_volume=ead2026-ops03-storage',
        'db_checksum='.hash_file('sha256', $backup.'/database.sql.gz'),
        'storage_checksum='.hash_file('sha256', $backup.'/storage.tar.gz'),
        'error=',
        '',
    ]);
    file_put_contents($backup.'/manifest.txt', $manifest.'manifest_signature='.hash('sha256', $manifest."manifest_key=deploy-test-key\n").PHP_EOL);
    file_put_contents($bin.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "${OPS03_DOCKER_LOG}"
if [[ "$1" == "volume" && "$2" == "inspect" ]]; then
    volume_name="${@: -1}"
    case "$volume_name" in
        ead2026-ops03-db) printf 'ead2026-ops03-db|ead2026-ops03|production_db_data\n' ;;
        ead2026-ops03-storage) printf 'ead2026-ops03-storage|ead2026-ops03|production_storage\n' ;;
        ead2026-ops03-cache) printf 'ead2026-ops03-cache|ead2026-ops03|production_cache\n' ;;
    esac
fi
BASH
    );
    chmod($bin.'/docker', 0700);

    return ['root' => $root, 'backup' => $backup, 'log' => $log];
}

function removeOps03DeploySafetyFixture(string $root): void
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

/**
 * @param  array<string, string>  $environment
 */
function runOps03Deploy(array $environment, string $root): Process
{
    $process = new Process([
        base_path('scripts/ops/ops03-deploy.sh'),
    ], base_path(), [
        'PATH' => $root.'/bin:'.(string) getenv('PATH'),
        ...$environment,
    ]);
    $process->run();

    return $process;
}

it('fails before mutation when the migration env file is absent', function (): void {
    $fixture = makeOps03DeploySafetyFixture();

    try {
        $process = runOps03Deploy([
            'OPS03_DOCKER_LOG' => $fixture['log'],
            'OPS03_REHEARSAL' => 'true',
            'COMPOSE_PROJECT_NAME' => 'ead2026-ops03',
            'APP_BUILD_SHA' => 'deadbeef',
            'OPS03_BACKUP_DIR' => $fixture['backup'],
            'OPS03_ENV_FILE' => $fixture['root'].'/missing.env',
        ], $fixture['root']);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('OPS03_ENV_FILE')
            ->and(is_file($fixture['log']))->toBeFalse();
    } finally {
        removeOps03DeploySafetyFixture($fixture['root']);
    }
});

it('does not place the migration password in the docker command argv', function (): void {
    $fixture = makeOps03DeploySafetyFixture();
    $envFile = $fixture['root'].'/ops03.env';

    file_put_contents($envFile, implode(PHP_EOL, [
        'APP_ENV=rehearsal',
        'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'APP_BUILD_SHA=deadbeef',
        'DB_DATABASE=ead2026_ops03',
        'DB_RUNTIME_USERNAME=runtime_user',
        'DB_RUNTIME_PASSWORD=runtime-secret',
        'DB_MIGRATION_USERNAME=migration_user',
        'DB_MIGRATION_PASSWORD=secret-not-for-argv',
        'PRODUCTION_DB_VOLUME=ead2026-ops03-db',
        'PRODUCTION_STORAGE_VOLUME=ead2026-ops03-storage',
        'PRODUCTION_CACHE_VOLUME=ead2026-ops03-cache',
        'OPS04_BACKUP_MANIFEST_KEY=deploy-test-key',
        '',
    ]));

    try {
        $process = runOps03Deploy([
            'OPS03_DOCKER_LOG' => $fixture['log'],
            'OPS03_REHEARSAL' => 'true',
            'COMPOSE_PROJECT_NAME' => 'ead2026-ops03',
            'APP_BUILD_SHA' => 'deadbeef',
            'OPS03_BACKUP_DIR' => $fixture['backup'],
            'OPS03_ENV_FILE' => $envFile,
        ], $fixture['root']);
        $dockerCalls = file_get_contents($fixture['log']);

        expect($process->getExitCode())->toBe(0)
            ->and($dockerCalls)->not->toContain('secret-not-for-argv')
            ->and($dockerCalls)->toContain('--env-from-file');
    } finally {
        removeOps03DeploySafetyFixture($fixture['root']);
    }
});
