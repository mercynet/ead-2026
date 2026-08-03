<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;

function mzrtTenantEntitlements(Tenant $tenant, array $headers = [], array $query = []): \Illuminate\Testing\TestResponse
{
    return test()->getJson('/api/v1/mzrt/tenants/'.$tenant->id.'/entitlements'.($query === [] ? '' : '?'.http_build_query($query)), $headers);
}

function entitlementActivation(Tenant $tenant, string $capability, string $status = 'active'): PluginActivation
{
    $plugin = Plugin::factory()->create(['capability_key' => $capability]);

    return PluginActivation::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'status' => $status,
        'deactivated_at' => $status === 'inactive' ? now() : null,
    ]);
}

it('lists every target tenant entitlement without tenant context or sensitive fields', function (): void {
    $targetTenant = makeTenant();
    $otherTenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Developer);
    $headers['X-Tenant-ID'] = (string) $otherTenant->id;
    entitlementActivation($targetTenant, 'feature.active');
    entitlementActivation($targetTenant, 'feature.inactive', 'inactive');
    entitlementActivation($otherTenant, 'feature.other');

    $response = mzrtTenantEntitlements($targetTenant, $headers)
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.capability', 'feature.active')
        ->assertJsonPath('data.1.capability', 'feature.inactive')
        ->assertJsonPath('data.1.status', 'inactive')
        ->assertJsonMissing(['tenant_id', 'plugin_id', 'activated_by', 'activated_at', 'deactivated_at', 'config', 'credentials']);

    expect(array_keys($response->json('data.0')))->toBe(['capability', 'status']);
});

it('paginates tenant entitlements with a stable cursor', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Developer);

    foreach (range(1, 16) as $number) {
        entitlementActivation($tenant, 'feature.'.$number);
    }

    $firstPage = mzrtTenantEntitlements($tenant, $headers)
        ->assertSuccessful()
        ->assertJsonCount(15, 'data');
    $next = $firstPage->json('links.next');

    expect($next)->toBeString()->not->toBeEmpty();

    parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
    $secondPage = mzrtTenantEntitlements($tenant, $headers, $query)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    expect(collect($firstPage->json('data'))->pluck('capability')->intersect(collect($secondPage->json('data'))->pluck('capability')))->toBeEmpty();
});

it('requires authentication', function (): void {
    assertApiErrorEnvelope(mzrtTenantEntitlements(makeTenant()), 401, 'unauthenticated');
});

it('blocks nondevelopers in MZRT area before controller', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    assertApiErrorEnvelope(mzrtTenantEntitlements($tenant, $headers), 403, 'area_forbidden');
});

it('denies developers without entitlement permission', function (): void {
    $tenant = makeTenant();
    $developer = User::factory()->developer()->create();
    $headers = ['Authorization' => 'Bearer '.$developer->createToken('without-permission')->plainTextToken];

    assertApiErrorEnvelope(mzrtTenantEntitlements($tenant, $headers), 403, 'access_denied');
});

it('returns canonical not found for an unknown tenant', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);

    assertApiErrorEnvelope(test()->getJson('/api/v1/mzrt/tenants/999999/entitlements', $headers), 404, 'not_found');
});
