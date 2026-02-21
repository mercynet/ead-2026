<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function registerTenantResolutionProbeRoutes(): void
{
    if (! Route::has('test.tenant.required')) {
        Route::middleware('resolve.tenant')
            ->get('/api/_test/tenant-required', function (Request $request) {
                return response()->json([
                    'data' => [
                        'tenant_id' => $request->attributes->get('tenant')?->id,
                    ],
                ]);
            })
            ->name('test.tenant.required');
    }

    if (! Route::has('test.tenant.optional')) {
        Route::middleware('resolve.tenant.optional')
            ->get('/api/_test/tenant-optional', function (Request $request) {
                return response()->json([
                    'data' => [
                        'tenant_id' => $request->attributes->get('tenant')?->id,
                    ],
                ]);
            })
            ->name('test.tenant.optional');
    }
}

it('resolves tenant by id before domain and host', function (): void {
    registerTenantResolutionProbeRoutes();

    $tenantById = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->getJson('/api/_test/tenant-required', [
        'X-Tenant-ID' => (string) $tenantById->id,
        'X-Tenant-Domain' => 'tenant-b.local',
        'HOST' => 'tenant-b.local',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.tenant_id', $tenantById->id);
});

it('resolves tenant by domain when id is not provided', function (): void {
    registerTenantResolutionProbeRoutes();

    $tenantByDomain = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->getJson('/api/_test/tenant-required', [
        'X-Tenant-Domain' => 'tenant-a.local',
        'HOST' => 'tenant-b.local',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.tenant_id', $tenantByDomain->id);
});

it('resolves tenant by host when id and domain are not provided', function (): void {
    registerTenantResolutionProbeRoutes();

    $tenantByHost = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->getJson('http://tenant-a.local/api/_test/tenant-required')
        ->assertSuccessful()
        ->assertJsonPath('data.tenant_id', $tenantByHost->id);
});

it('returns unprocessable entity when required tenant is not resolved', function (): void {
    registerTenantResolutionProbeRoutes();

    $this->getJson('/api/_test/tenant-required')
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'tenant_not_resolved');
});

it('allows missing tenant on optional middleware', function (): void {
    registerTenantResolutionProbeRoutes();

    $this->getJson('/api/_test/tenant-optional')
        ->assertSuccessful()
        ->assertJsonPath('data.tenant_id', null);
});
