<?php

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Contrato de exit code do runner E2E
|--------------------------------------------------------------------------
| Falha de cleanup/teardown deixa fixtures órfãs; o runner precisa refletir
| isso no exit code (senão declara falso sucesso deixando resíduo no banco).
*/

it('returns a non-zero exit code when spec cleanup fails (residue left behind)', function (): void {
    // Canário e chamadas HTTP são fingidas: o alvo do teste é a contabilidade de
    // falhas do runner, não o alinhamento real servidor↔DB.
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-throws',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(1);
});

it('returns zero when cleanup succeeds and no case fails', function (): void {
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(0);
});

it('removes only activity rows linked to runner fixtures', function (): void {
    $unrelatedActor = User::factory()->developer()->create();
    $unrelatedActivity = activity('test')
        ->causedBy($unrelatedActor)
        ->performedOn($unrelatedActor)
        ->event('created')
        ->log('Unrelated activity.');
    $activityIdsBefore = Activity::query()->pluck('id')->all();

    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(0);

    expect(Activity::query()->whereKey($unrelatedActivity->id)->exists())->toBeTrue()
        ->and(Activity::query()->pluck('id')->all())->toBe($activityIdsBefore);
});

it('supports per-case methods, explicit tenant header suppression and response capture', function (): void {
    Http::fakeSequence()
        ->push(['data' => ['ok' => true]], 200)
        ->push(['data' => ['tenant' => ['id' => 123], 'token' => 'secret-token']], 201)
        ->push(['data' => ['tenant_id' => 123]], 200);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/mixed-method-capture',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(0);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'http://e2e.test/created');
    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && $request->url() === 'http://e2e.test/later/123'
        && (array_change_key_case($request->headers(), CASE_LOWER)['x-captured-tenant-id'][0] ?? null) === '123');
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && ! array_key_exists('x-tenant-id', array_change_key_case($request->headers(), CASE_LOWER)));
});

it('rejects unsupported per-case HTTP methods', function (): void {
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/invalid-method',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(1);
});

it('rejects an invalid dynamic path without sending its request', function (): void {
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/invalid-path',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(1);

    Http::assertSentCount(1);
});
