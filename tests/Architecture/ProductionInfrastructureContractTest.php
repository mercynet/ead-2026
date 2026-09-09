<?php

/**
 * Static contract for the provider-neutral paid-pilot topology.
 *
 * Runtime/staging checks still have to be performed against a real Docker
 * host; these assertions prevent the versioned baseline from drifting back
 * to the development Sail topology.
 */
function productionContractFile(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse("Unable to read {$path}");

    return (string) $contents;
}

/**
 * @return array<string, string>
 */
function productionEnvExample(): array
{
    $lines = file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect($lines)->not->toBeFalse();

    $values = [];
    foreach ($lines as $line) {
        if (! str_contains($line, '=') || str_starts_with(trim($line), '#')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\"'");
    }

    return $values;
}

it('keeps the production env contract explicit and non-development', function (): void {
    $env = productionEnvExample();

    expect($env['APP_ENV'] ?? null)->toBe('production')
        ->and($env['APP_DEBUG'] ?? null)->toBe('false')
        ->and($env['APP_URL'] ?? '')->toStartWith('https://')
        ->and($env['FILESYSTEM_DISK'] ?? null)->toBeIn(['local', 's3'])
        ->and($env['MEDIA_DISK'] ?? null)->toBeIn(['local', 's3'])
        ->and($env['SESSION_SECURE_COOKIE'] ?? null)->toBe('true')
        ->and($env['QUEUE_CONNECTION'] ?? null)->toBe('database')
        ->and($env['MAIL_MAILER'] ?? null)->not->toBe('log')
        ->and($env['CORS_ALLOWED_ORIGINS'] ?? '')->not->toContain('*');
});

it('keeps placeholders and operational boundaries visible without storing secrets', function (): void {
    $envContents = productionContractFile('.env.production.example');
    $env = productionEnvExample();

    expect($envContents)->not->toMatch('/(?:sk-[A-Za-z0-9]|AKIA[0-9A-Z]{16}|BEGIN [A-Z ]+ PRIVATE KEY)/')
        ->and($env['DB_RUNTIME_USERNAME'] ?? null)->not->toBe('root')
        ->and($env['DB_RUNTIME_USERNAME'] ?? null)->not->toBe($env['DB_MIGRATION_USERNAME'] ?? null)
        ->and($env['DB_RUNTIME_USERNAME'] ?? null)->not->toBe($env['DB_BOOTSTRAP_USERNAME'] ?? null)
        ->and($env['APP_KEY'] ?? '')->toContain('REPLACE_WITH_')
        ->and($env['DB_RUNTIME_PASSWORD'] ?? '')->toContain('REPLACE_WITH_')
        ->and($env['DB_MIGRATION_PASSWORD'] ?? '')->toContain('REPLACE_WITH_');
});

it('keeps the production compose isolated from development-only services and mounts', function (): void {
    $compose = productionContractFile('compose.production.yaml');

    expect($compose)->not->toContain('.:/var/www/html')
        ->and($compose)->not->toContain('php artisan serve')
        ->and($compose)->not->toContain('mailpit')
        ->and($compose)->toContain('production_storage:/var/www/html/storage')
        ->and($compose)->toContain('production_db_data:/var/lib/mysql')
        ->and($compose)->toContain("private:\n        driver: bridge\n        internal: true")
        ->and($compose)->toContain("profiles: ['worker']")
        ->and($compose)->toContain("command: ['php', 'artisan', 'schedule:work']");
});

it('keeps image, proxy and database contracts production-oriented', function (): void {
    $dockerfile = productionContractFile('Dockerfile.production');
    $compose = productionContractFile('compose.production.yaml');
    $caddyfile = productionContractFile('docker/caddy/Caddyfile');
    $mysqlInit = productionContractFile('docker/mysql/init-users.sh');
    $dockerignore = productionContractFile('.dockerignore');

    expect($dockerfile)->toContain('--no-dev')
        ->and($dockerfile)->toContain('"php-fpm", "-F"')
        ->and($dockerfile)->toContain('org.opencontainers.image.revision')
        ->and($dockerfile)->toContain('org.opencontainers.image.migrations.manifest.sha256')
        ->and($dockerfile)->not->toContain('artisan serve')
        ->and($caddyfile)->toContain('redir https://{host}{uri} permanent')
        ->and($caddyfile)->toContain('tls {$CADDY_TLS_DIRECTIVE}')
        ->and($caddyfile)->toContain('Strict-Transport-Security')
        ->and($mysqlInit)->toContain('GRANT SELECT, INSERT, UPDATE, DELETE')
        ->and($mysqlInit)->toContain('GRANT ALL PRIVILEGES ON')
        ->and($dockerignore)->toContain('.env*')
        ->and($dockerignore)->toContain('vendor');

    expect($compose)->toContain('MIGRATION_MANIFEST_SHA: ${MIGRATION_MANIFEST_SHA:?MIGRATION_MANIFEST_SHA must identify the release manifest}');
});

it('defaults media and local storage to private disks', function (): void {
    $filesystems = productionContractFile('config/filesystems.php');
    $mediaLibrary = productionContractFile('config/media-library.php');

    expect($filesystems)->toContain("'visibility' => 'private'")
        ->and($filesystems)->toContain("'private' => 0600")
        ->and($mediaLibrary)->toContain("env('MEDIA_DISK', 'local')");
});

it('keeps OPS-04 readiness, telemetry and operational probes versioned', function (): void {
    $bootstrap = productionContractFile('bootstrap/app.php');
    $routes = productionContractFile('routes/web.php');
    $env = productionContractFile('.env.production.example');
    $e2eCompose = productionContractFile('compose.e2e.yaml');
    $e2eEnv = productionContractFile('.env.e2e.example');
    $scripts = array_map(
        fn (string $path): string => productionContractFile($path),
        [
            'scripts/ops/ops04-alert.sh',
            'scripts/ops/ops04-alert-canary.sh',
            'scripts/ops/ops04-readiness.sh',
            'scripts/ops/ops04-backup-monitor.sh',
            'scripts/ops/ops04-error-scan.sh',
            'scripts/ops/ops04-synthetic.sh',
            'scripts/ops/ops04-deploy-observe.sh',
            'scripts/ops/ops04-remote-backup.sh',
            'scripts/ops/ops04-domain-tls.sh',
            'scripts/ops/validate-production-env.sh',
        ],
    );

    expect($bootstrap)->toContain('RequestTelemetry::class')
        ->and($bootstrap)->toContain("health: '/up'")
        ->and($bootstrap)->toContain('$exceptions->respond')
        ->and($routes)->toContain("Route::get('/readiness'")
        ->and($env)->toContain('OPS04_SCHEDULER_REQUIRED=true')
        ->and($env)->toContain('OPS04_ALERT_OWNER=REPLACE_WITH_HUMAN_OWNER')
        ->and($env)->toContain('OPS04_REMOTE_BACKUP_DESTINATION=REPLACE_WITH_REMOTE_DESTINATION')
        ->and($env)->toContain('OPS04_REMOTE_BACKUP_CREDENTIAL=REPLACE_WITH_REMOTE_BACKUP_CREDENTIAL')
        ->and($env)->toContain('MIGRATION_MANIFEST_SHA=REPLACE_WITH_RELEASE_MIGRATION_MANIFEST_SHA256')
        ->and($env)->toContain('OPS04_EXPECTED_RC_SHA=REPLACE_WITH_RELEASE_GIT_SHA')
        ->and($env)->toContain('OPS04_EXPECTED_COMPOSE_PROJECT=ead2026-production')
        ->and($env)->toContain('OPS04_BACKUP_MANIFEST_KEY=REPLACE_WITH_BACKUP_MANIFEST_SIGNING_KEY')
        ->and($env)->toContain('OPS04_REMOTE_BACKUP_VERIFY_ADAPTER=REPLACE_WITH_REMOTE_BACKUP_VERIFY_ADAPTER')
        ->and($env)->toContain('OPS04_SYNTHETIC_COMPOSE_FILES=compose.yaml:compose.e2e.yaml')
        ->and($env)->toContain('OPS04_SYNTHETIC_ENV_FILE=/etc/ead2026/e2e.env')
        ->and($env)->toContain('OPS04_SYNTHETIC_APP_SERVICE=laravel.test');

    expect($e2eCompose)->toContain('.env.e2e')
        ->and($e2eCompose)->toContain('APP_KEY: ${APP_KEY:?APP_KEY must be provided for E2E}')
        ->and($e2eCompose)->toContain('DB_DISPOSABLE: ${DB_DISPOSABLE:-e2e}')
        ->and($e2eEnv)->toContain('APP_ENV=e2e')
        ->and($e2eEnv)->toContain('DB_DISPOSABLE=e2e')
        ->and($e2eEnv)->toContain('APP_KEY=base64:REPLACE_WITH_E2E_ONLY_KEY')
        ->and($scripts[1])->toContain('alert_delivery_canary')
        ->and($scripts[3])->toContain('db_checksum')
        ->and($scripts[3])->toContain('storage_checksum')
        ->and($scripts[2])->toContain('readiness_payload')
        ->and($scripts[2])->toContain('migration_manifest')
        ->and($scripts[5])->toContain('readiness_payload')
        ->and($scripts[5])->toContain('"status":"ready"')
        ->and($scripts[5])->toContain('Resultado: [1-9][0-9]* passou, 0 falhou')
        ->and($scripts[9])->toContain('alert webhook must use https');

    expect($scripts[5])->toContain('env=e2e')
        ->and($scripts[5])->toContain('disposable=e2e')
        ->and($scripts[7])->toContain('REMOTE_BACKUP_EXTERNAL_BLOCKER')
        ->and($scripts[8])->toContain('certificate_hostname_or_chain_invalid');

    foreach ($scripts as $script) {
        expect($script)->toStartWith('#!/usr/bin/env bash')
            ->and($script)->toContain('set -euo pipefail');
    }
});
