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
        ->and($compose)->toContain("production_storage:/var/www/html/storage")
        ->and($compose)->toContain("production_db_data:/var/lib/mysql")
        ->and($compose)->toContain("private:\n        driver: bridge\n        internal: true")
        ->and($compose)->toContain("profiles: ['worker']")
        ->and($compose)->toContain("command: ['php', 'artisan', 'schedule:work']");
});

it('keeps image, proxy and database contracts production-oriented', function (): void {
    $dockerfile = productionContractFile('Dockerfile.production');
    $caddyfile = productionContractFile('docker/caddy/Caddyfile');
    $mysqlInit = productionContractFile('docker/mysql/init-users.sh');
    $dockerignore = productionContractFile('.dockerignore');

    expect($dockerfile)->toContain('--no-dev')
        ->and($dockerfile)->toContain('"php-fpm", "-F"')
        ->and($dockerfile)->not->toContain('artisan serve')
        ->and($caddyfile)->toContain('redir https://{host}{uri} permanent')
        ->and($caddyfile)->toContain('tls {$CADDY_TLS_DIRECTIVE}')
        ->and($caddyfile)->toContain('Strict-Transport-Security')
        ->and($mysqlInit)->toContain('GRANT SELECT, INSERT, UPDATE, DELETE')
        ->and($mysqlInit)->toContain('GRANT ALL PRIVILEGES ON')
        ->and($dockerignore)->toContain('.env*')
        ->and($dockerignore)->toContain('vendor');
});

it('defaults media and local storage to private disks', function (): void {
    $filesystems = productionContractFile('config/filesystems.php');
    $mediaLibrary = productionContractFile('config/media-library.php');

    expect($filesystems)->toContain("'visibility' => 'private'")
        ->and($filesystems)->toContain("'private' => 0600")
        ->and($mediaLibrary)->toContain("env('MEDIA_DISK', 'local')");
});
