<?php

use App\Shared\Database\DestructiveDatabaseGuard;
use Illuminate\Support\Facades\Config;

function disposableDatabaseIdentity(string $environment = 'testing'): void
{
    Config::set('app.env', $environment);
    Config::set('database.default', 'mysql');
    Config::set('database.connections.mysql.database', $environment === 'e2e' ? 'ead2026_e2e' : 'testing');
    Config::set('database.connections.mysql.host', 'mysql');
    Config::set('database.connections.mysql.username', 'sail');
    Config::set('database.destructive_operations.disposable_marker', $environment);
}

afterEach(function (): void {
    Config::set('app.env', 'testing');
    Config::set('database.default', 'mysql');
    Config::set('database.connections.mysql.database', 'testing');
    Config::set('database.connections.mysql.host', 'mysql');
    Config::set('database.connections.mysql.username', 'sail');
    Config::set('database.destructive_operations.disposable_marker', 'testing');
});

it('denies destructive E2E in production before any migration or fixture', function (): void {
    Config::set('app.env', 'production');

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--fresh' => true,
    ])->assertExitCode(1);
});

it('denies a database name outside the environment allowlist', function (): void {
    disposableDatabaseIdentity();
    Config::set('database.connections.mysql.database', 'ead2026');

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--fresh' => true,
    ])->assertExitCode(1);
});

it('denies a database host outside the allowlist', function (): void {
    disposableDatabaseIdentity();
    Config::set('database.connections.mysql.host', 'production-db.internal');

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--fresh' => true,
    ])->assertExitCode(1);
});

it('rejects force-db instead of bypassing the destructive database gate', function (): void {
    disposableDatabaseIdentity();

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--fresh' => true,
        '--force-db' => true,
    ])->assertExitCode(1);
});

it('allows a fully identified disposable E2E database', function (): void {
    disposableDatabaseIdentity('e2e');

    expect(app(DestructiveDatabaseGuard::class)->denialReason())->toBeNull();
});

it('denies QA fresh when the target identity is ambiguous', function (): void {
    disposableDatabaseIdentity();
    Config::set('database.destructive_operations.disposable_marker', '');

    expect(app(DestructiveDatabaseGuard::class)->denialReason('testing'))
        ->toBe('marcador explícito de banco descartável ausente ou incompatível');

    $this->artisan('qa:fresh')->assertExitCode(1);
});

it('does not allow the development seeder in production', function (): void {
    Config::set('app.env', 'production');

    expect(fn (): mixed => app(\Database\Seeders\DatabaseSeeder::class)->run())
        ->toThrow(LogicException::class, 'não pode rodar em produção');
});

it('guards the fresh sink before booting E2E fixtures', function (): void {
    $commandSource = file_get_contents(base_path('app/Console/Commands/E2eRunCommand.php'));
    $refresherSource = file_get_contents(base_path('app/Shared/Database/FreshDatabaseRefresher.php'));

    expect($commandSource)->toBeString()
        ->and($refresherSource)->toBeString();

    $guardPosition = strpos($commandSource, 'denialReason()');
    $refresherPosition = strpos($commandSource, '$this->databaseRefresher->refresh()');
    $freshPosition = strpos($refresherSource, "call('migrate:fresh'");
    $fixturesPosition = strpos($commandSource, '$this->bootFixtures()');

    expect($guardPosition)->toBeInt()
        ->and($refresherPosition)->toBeInt()
        ->and($freshPosition)->toBeInt()
        ->and($fixturesPosition)->toBeInt()
        ->and($guardPosition)->toBeLessThan($refresherPosition)
        ->and($refresherPosition)->toBeLessThan($fixturesPosition);
});

it('aborts fixture execution when the fresh migration result is not successful', function (): void {
    $source = file_get_contents(base_path('app/Console/Commands/E2eRunCommand.php'));

    expect($source)->toBeString();

    $failureCheckPosition = strpos($source, 'if (! $freshSucceeded)');
    $fixturesPosition = strpos($source, '$this->bootFixtures()');

    expect($failureCheckPosition)->toBeInt()
        ->and($fixturesPosition)->toBeInt()
        ->and($failureCheckPosition)->toBeLessThan($fixturesPosition);
});
