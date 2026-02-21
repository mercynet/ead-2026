<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('logs in with valid tenant context and credentials', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $user = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(
        '/api/v1/core/auth/login',
        [
            'email' => 'john@example.com',
            'password' => 'password123',
        ],
        [
            'X-Tenant-ID' => (string) $tenant->id,
        ],
    );

    $response
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
            ],
            'meta',
        ]);

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->exists())->toBeTrue();
});

it('rejects login when tenant context is missing', function (): void {
    $response = $this->postJson('/api/v1/core/auth/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable();
});

it('rejects login when user does not belong to tenant', function (): void {
    $tenantA = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $tenantB = Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(
        '/api/v1/core/auth/login',
        [
            'email' => 'john@example.com',
            'password' => 'password123',
        ],
        [
            'X-Tenant-ID' => (string) $tenantB->id,
        ],
    );

    $response->assertUnauthorized();
});

it('returns authenticated user on me endpoint', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $user = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $this->getJson('/api/v1/core/auth/me', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id);
});

it('logs out and revokes current token', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $user = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $issuedToken = $user->createToken('test-token');
    $accessToken = $issuedToken->accessToken;

    $this->postJson('/api/v1/core/auth/logout', [], [
        'Authorization' => 'Bearer '.$issuedToken->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    expect(PersonalAccessToken::query()->find($accessToken->id))->toBeNull();
});
