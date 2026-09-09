<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Storage;

function writeReadinessManifest(): string
{
    $migrator = app(Migrator::class);
    $migrations = array_keys($migrator->getMigrationFiles([
        ...$migrator->paths(),
        database_path('migrations'),
    ]));
    sort($migrations, SORT_STRING);

    $path = tempnam(sys_get_temp_dir(), 'ops04-manifest-');
    expect($path)->toBeString();
    file_put_contents($path, json_encode([
        'format' => 1,
        'migration_count' => count($migrations),
        'migrations' => $migrations,
    ], JSON_THROW_ON_ERROR));

    config(['ops.migration_manifest' => $path]);

    return $path;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/ops04-manifest-*') ?: [] as $path) {
        @unlink($path);
    }
});

it('reports all essential dependencies and preserves a safe request id', function (): void {
    writeReadinessManifest();
    Storage::fake('local');
    $filesBefore = Storage::disk('local')->allFiles();

    $response = $this->getJson('/readiness', [
        'X-Request-ID' => 'synthetic-readiness-001',
    ]);

    $response->assertSuccessful()
        ->assertHeader('X-Request-ID', 'synthetic-readiness-001')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('checks.app.status', 'pass')
        ->assertJsonPath('checks.db.status', 'pass')
        ->assertJsonPath('checks.storage.status', 'pass')
        ->assertJsonPath('checks.migration_manifest.status', 'pass')
        ->assertJsonPath('checks.outbox.status', 'pass')
        ->assertJsonPath('checks.queue.status', 'not_required')
        ->assertJsonPath('checks.storage.mutating', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Set-Cookie'))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe($filesBefore);
});

it('fails readiness when the migration manifest is missing', function (): void {
    config(['ops.migration_manifest' => sys_get_temp_dir().'/ops04-manifest-does-not-exist.json']);

    $response = $this->getJson('/readiness');

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'not_ready')
        ->assertJsonPath('checks.migration_manifest.status', 'fail');
    expect($response->headers->get('X-Request-ID'))->toMatch('/^[A-Za-z0-9._-]{1,64}$/');
});

it('fails readiness when storage cannot be opened', function (): void {
    writeReadinessManifest();
    config(['filesystems.default' => 'ops04-missing-disk']);

    $response = $this->getJson('/readiness');

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'not_ready')
        ->assertJsonPath('checks.storage.status', 'fail');
});

it('fails readiness when the database is unavailable', function (): void {
    writeReadinessManifest();
    $database = config('database.connections.mysql');
    config(['database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 1]);
    app('db')->purge('mysql');

    try {
        $response = $this->getJson('/readiness');

        $response->assertServiceUnavailable()
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.db.status', 'fail');
    } finally {
        config(['database.connections.mysql' => $database]);
        app('db')->purge('mysql');
    }
});

it('rejects an unsafe incoming request id and generates a safe one', function (): void {
    writeReadinessManifest();
    Storage::fake('local');

    $response = $this->getJson('/readiness', [
        'X-Request-ID' => "bad\nrequest",
    ]);

    expect($response->headers->get('X-Request-ID'))->toMatch('/^[A-Za-z0-9._-]{1,64}$/');
});
