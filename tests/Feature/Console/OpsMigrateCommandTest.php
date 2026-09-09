<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/ops-migrations-*') ?: [] as $path) {
        @unlink($path);
    }
});

it('discriminates the incomplete Laravel default path from the full framework discovery', function (): void {
    $migrator = app(Migrator::class);
    $defaultCount = count($migrator->getMigrationFiles([database_path('migrations')]));
    $fullCount = count($migrator->getMigrationFiles([
        ...$migrator->paths(),
        database_path('migrations'),
    ]));
    $manifestPath = tempnam(sys_get_temp_dir(), 'ops-migrations-');

    expect($manifestPath)->toBeString()
        ->and($defaultCount)->toBe(8)
        ->and($fullCount)->toBeGreaterThan($defaultCount);

    $this->artisan('ops:migrate', [
        '--write-manifest' => true,
        '--manifest' => $manifestPath,
    ])->expectsOutput('expected='.$fullCount)
        ->assertExitCode(0);
});

it('aborts before database work when discovered migrations differ from the release manifest', function (): void {
    $migrator = app(Migrator::class);
    $migrations = array_keys($migrator->getMigrationFiles([
        ...$migrator->paths(),
        database_path('migrations'),
    ]));
    array_pop($migrations);
    $manifestPath = tempnam(sys_get_temp_dir(), 'ops-migrations-');

    file_put_contents($manifestPath, json_encode([
        'format' => 1,
        'migration_count' => count($migrations),
        'migrations' => $migrations,
    ], JSON_THROW_ON_ERROR));

    $this->artisan('ops:migrate', [
        '--check-only' => true,
        '--manifest' => $manifestPath,
    ])->expectsOutput('MIGRATION_DISCOVERY_UNSAFE: manifest and discovered migrations differ.')
        ->assertExitCode(1);
});

it('aborts before migration when the schema contains an applied migration outside the release manifest', function (): void {
    $migrator = app(Migrator::class);
    $migrations = array_keys($migrator->getMigrationFiles([
        ...$migrator->paths(),
        database_path('migrations'),
    ]));
    $manifestPath = tempnam(sys_get_temp_dir(), 'ops-migrations-');

    file_put_contents($manifestPath, json_encode([
        'format' => 1,
        'migration_count' => count($migrations),
        'migrations' => $migrations,
    ], JSON_THROW_ON_ERROR));
    DB::table('migrations')->insert(['migration' => 'legacy_not_in_release_manifest', 'batch' => 99]);

    $this->artisan('ops:migrate', [
        '--check-only' => true,
        '--manifest' => $manifestPath,
    ])->expectsOutput('MIGRATION_SCHEMA_UNSAFE: applied migrations are not in the release manifest.')
        ->assertExitCode(1);
});

it('passes the manifest and schema gate when the applied schema matches the release', function (): void {
    $migrator = app(Migrator::class);
    $migrations = array_keys($migrator->getMigrationFiles([
        ...$migrator->paths(),
        database_path('migrations'),
    ]));
    sort($migrations, SORT_STRING);
    $manifestPath = tempnam(sys_get_temp_dir(), 'ops-migrations-');

    file_put_contents($manifestPath, json_encode([
        'format' => 1,
        'migration_count' => count($migrations),
        'migrations' => $migrations,
    ], JSON_THROW_ON_ERROR));

    $this->artisan('ops:migrate', [
        '--check-only' => true,
        '--manifest' => $manifestPath,
    ])->expectsOutput('migration=PASS')
        ->assertExitCode(0);
});
