<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

function updateTenantStatus(Tenant $tenant, array $payload, array $headers): \Illuminate\Testing\TestResponse
{
    return test()->patchJson("/api/v1/mzrt/tenants/{$tenant->id}/status", $payload, $headers);
}

it('suspends a tenant as developer and exposes only status', function (): void {
    [$developer, $headers] = actingAsUserType(UserType::Developer);
    $tenant = makeTenant();

    $response = updateTenantStatus($tenant, ['status' => 'suspended'], $headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonMissingPath('data.is_active');

    expect($tenant->refresh()->is_active)->toBeFalse();

    $activity = Activity::query()
        ->where('subject_type', Tenant::class)
        ->where('subject_id', $tenant->id)
        ->where('causer_id', $developer->id)
        ->where('event', 'updated')
        ->firstOrFail();

    expect($activity->properties->toJson())->toContain('is_active');
});

it('is idempotent when tenant already has requested status', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $tenant = makeTenant(['is_active' => false]);

    updateTenantStatus($tenant, ['status' => 'suspended'], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'suspended');

    expect($tenant->refresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('subject_type', Tenant::class)->where('subject_id', $tenant->id)->where('event', 'updated')->count())->toBe(0);
});

it('denies non-developers', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    assertApiErrorEnvelope(updateTenantStatus($tenant, ['status' => 'suspended'], $headers), 403);
});

it('denies admins before route binding can reveal tenant existence', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    assertApiErrorEnvelope(updateTenantStatus($tenant, ['status' => 'suspended'], $headers), 403, 'area_forbidden');
    assertApiErrorEnvelope(
        test()->patchJson('/api/v1/mzrt/tenants/999999/status', ['status' => 'suspended'], $headers),
        403,
        'area_forbidden',
    );
});

it('denies developers without tenant status permission', function (): void {
    $developer = User::factory()->developer()->create();
    $headers = ['Authorization' => 'Bearer '.$developer->createToken('without-role')->plainTextToken];
    $tenant = makeTenant();

    assertApiErrorEnvelope(updateTenantStatus($tenant, ['status' => 'suspended'], $headers), 403, 'access_denied');
});

it('validates status', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $tenant = makeTenant();

    assertApiErrorEnvelope(updateTenantStatus($tenant, ['status' => 'paused'], $headers), 422, 'validation_error');
});

it('returns not found envelope for unknown tenant', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);

    assertApiErrorEnvelope(
        test()->patchJson('/api/v1/mzrt/tenants/999999/status', ['status' => 'suspended'], $headers),
        404,
        'not_found',
    );
});

it('blocks login and target tenant token after suspension without affecting another tenant', function (): void {
    [, $developerHeaders] = actingAsUserType(UserType::Developer);
    $suspendedTenant = makeTenant();
    $activeTenant = makeTenant();
    $suspendedUser = User::factory()->forTenant($suspendedTenant)->create([
        'email' => 'suspended@example.test',
        'password' => Hash::make('password123'),
    ]);
    $activeUser = User::factory()->forTenant($activeTenant)->create();
    $suspendedToken = $suspendedUser->createToken('suspended-tenant')->plainTextToken;
    $activeToken = $activeUser->createToken('active-tenant')->plainTextToken;

    updateTenantStatus($suspendedTenant, ['status' => 'suspended'], $developerHeaders)->assertSuccessful();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/core/auth/login', [
        'email' => 'suspended@example.test',
        'password' => 'password123',
    ], tenantHeaders($suspendedTenant))->assertUnauthorized();

    $suspendedResponse = $this->getJson('/api/v1/core/auth/me', tenantHeaders($suspendedTenant, $suspendedToken));
    assertApiErrorEnvelope($suspendedResponse, 422, 'tenant_not_resolved');
    app('auth')->forgetGuards();

    $activeResponse = $this->getJson('/api/v1/core/auth/me', tenantHeaders($activeTenant, $activeToken));
    $activeResponse
        ->assertSuccessful()
        ->assertJsonPath('data.id', $activeUser->id);
});

it('restores login and tenant-bound token access when reactivated', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $tenant = makeTenant(['is_active' => false]);
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'reactivated@example.test',
        'password' => Hash::make('password123'),
    ]);
    $token = $user->createToken('reactivated-tenant')->plainTextToken;

    updateTenantStatus($tenant, ['status' => 'active'], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active');
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/core/auth/login', [
        'email' => 'reactivated@example.test',
        'password' => 'password123',
    ], tenantHeaders($tenant))->assertSuccessful();

    $this->getJson('/api/v1/core/auth/me', tenantHeaders($tenant, $token))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});
