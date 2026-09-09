<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @return array{root: string, source: string, destination: string, adapter: string, verifier: string}
 */
function makeRemoteBackupFixture(bool $validReadback = true): array
{
    $root = sys_get_temp_dir().'/ead2026-remote-backup-'.bin2hex(random_bytes(6));
    $source = $root.'/source';
    $destination = $root.'/remote';
    $adapter = $root.'/upload.sh';
    $verifier = $root.'/verify.sh';

    mkdir($source, 0700, true);
    mkdir($destination, 0700, true);
    file_put_contents($source.'/database.sql.gz', 'database-fixture');
    file_put_contents($source.'/storage.tar.gz', 'storage-fixture');

    file_put_contents($adapter, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source_dir="$1"
destination="$2"
receipt="$3"
retention_days="$4"
mkdir -p "$destination"
cp "$source_dir/database.sql.gz" "$destination/database.sql.gz"
cp "$source_dir/storage.tar.gz" "$destination/storage.tar.gz"
db_checksum="$(sha256sum "$source_dir/database.sql.gz" | awk '{print $1}')"
storage_checksum="$(sha256sum "$source_dir/storage.tar.gz" | awk '{print $1}')"
combined_checksum="$(printf '%s  %s\n%s  %s\n' "$db_checksum" database.sql.gz "$storage_checksum" storage.tar.gz | sha256sum | awk '{print $1}')"
printf 'status=PASS\nchecksum=%s\nretention_days=%s\n' "$combined_checksum" "$retention_days" > "$receipt"
BASH
    );
    chmod($adapter, 0700);

    if ($validReadback) {
        $verificationBody = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
destination="$1"
receipt="$2"
retention_days="$3"
db_checksum="$(sha256sum "$destination/database.sql.gz" | awk '{print $1}')"
storage_checksum="$(sha256sum "$destination/storage.tar.gz" | awk '{print $1}')"
printf 'status=PASS\ndb_checksum=%s\nstorage_checksum=%s\nretention_days=%s\nartifact_version=remote-1\n' "$db_checksum" "$storage_checksum" "$retention_days" > "$receipt"
BASH;
    } else {
        $verificationBody = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'status=PASS\ndb_checksum=%064d\nstorage_checksum=%064d\nretention_days=%s\nartifact_version=remote-1\n' 0 0 "$3" > "$2"
BASH;
    }
    file_put_contents($verifier, $verificationBody);
    chmod($verifier, 0700);

    return compact('root', 'source', 'destination', 'adapter', 'verifier');
}

function removeRemoteBackupFixture(string $root): void
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

function runRemoteBackupFixture(array $fixture): Process
{
    $process = new Process([
        base_path('scripts/ops/ops04-remote-backup.sh'),
        $fixture['source'],
    ], base_path(), [
        'OPS04_REMOTE_BACKUP_DESTINATION' => $fixture['destination'],
        'OPS04_REMOTE_BACKUP_CREDENTIAL' => 'synthetic-credential',
        'OPS04_REMOTE_BACKUP_ADAPTER' => $fixture['adapter'],
        'OPS04_REMOTE_BACKUP_VERIFY_ADAPTER' => $fixture['verifier'],
    ]);
    $process->run();

    return $process;
}

it('requires an independent remote readback before reporting PASS', function (): void {
    $fixture = makeRemoteBackupFixture();

    try {
        $process = runRemoteBackupFixture($fixture);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('remote_backup=PASS');
    } finally {
        removeRemoteBackupFixture($fixture['root']);
    }
});

it('rejects an upload receipt when independent readback disagrees', function (): void {
    $fixture = makeRemoteBackupFixture(false);

    try {
        $process = runRemoteBackupFixture($fixture);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('remote_readback_mismatch');
    } finally {
        removeRemoteBackupFixture($fixture['root']);
    }
});
