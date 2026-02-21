<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('registers a user within the resolved tenant', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson(
        '/api/v1/core/users',
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ],
        [
            'X-Tenant-ID' => (string) $tenant->id,
        ],
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com');

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->tenant_id)->toBe($tenant->id);
    expect($user?->hasRole('student'))->toBeTrue();
});

it('rejects registration without tenant context', function (): void {
    $this->postJson('/api/v1/core/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable();
});

it('updates own profile in the same tenant', function (): void {
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

    $this->patchJson('/api/v1/core/users/me', [
        'name' => 'John Updated',
        'headline' => 'Instructor',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'John Updated')
        ->assertJsonPath('data.headline', 'Instructor');
});

it('forbids profile update for tenant mismatch', function (): void {
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

    $user = User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $this->patchJson('/api/v1/core/users/me', [
        'name' => 'John Updated',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantB->id,
    ])->assertForbidden();
});

it('updates password with valid current password', function (): void {
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

    $this->patchJson('/api/v1/core/users/me/password', [
        'current_password' => 'password123',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects password update with invalid current password', function (): void {
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

    $this->patchJson('/api/v1/core/users/me/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnprocessable();
});
