<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows tenant admin to list users from own tenant only', function (): void {
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

    Role::query()->firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'web']);

    $admin = User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Tenant Admin',
        'email' => 'admin@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('tenant_admin');

    User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Student A1',
        'email' => 'student-a1@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    User::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Student B1',
        'email' => 'student-b1@tenant-b.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $response = $this->getJson('/api/v1/core/users', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['email' => 'admin@tenant-a.test'])
        ->assertJsonFragment(['email' => 'student-a1@tenant-a.test'])
        ->assertJsonMissing(['email' => 'student-b1@tenant-b.test']);
});

it('forbids non tenant admin from listing users', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $token = $student->createToken('student-token')->plainTextToken;

    $this->getJson('/api/v1/core/users', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('allows tenant admin to view user detail from own tenant', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Role::query()->firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'web']);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Admin',
        'email' => 'admin@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('tenant_admin');

    $targetUser = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Target User',
        'email' => 'target@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/core/users/'.$targetUser->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'target@tenant-a.test');
});

it('returns not found when tenant admin tries to view user from another tenant', function (): void {
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

    Role::query()->firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'web']);

    $admin = User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Tenant Admin',
        'email' => 'admin@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('tenant_admin');

    $otherTenantUser = User::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Other Tenant User',
        'email' => 'other@tenant-b.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/core/users/'.$otherTenantUser->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ])->assertNotFound();
});

it('allows developer to list users from all tenants', function (): void {
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

    Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

    $developer = User::query()->create([
        'tenant_id' => null,
        'name' => 'Developer',
        'email' => 'dev@platform.test',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    User::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'A User',
        'email' => 'a@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    User::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'B User',
        'email' => 'b@tenant-b.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $developer->createToken('developer-token')->plainTextToken;

    $this->getJson('/api/v1/core/users', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ])
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['email' => 'a@tenant-a.test'])
        ->assertJsonFragment(['email' => 'b@tenant-b.test']);
});

it('allows developer to view user from another tenant', function (): void {
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

    Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

    $developer = User::query()->create([
        'tenant_id' => null,
        'name' => 'Developer',
        'email' => 'dev@platform.test',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $targetUser = User::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'B User',
        'email' => 'b@tenant-b.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $developer->createToken('developer-token')->plainTextToken;

    $this->getJson('/api/v1/core/users/'.$targetUser->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'b@tenant-b.test');
});

it('hides developer users from tenant admin list and detail endpoints', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Role::query()->firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Admin',
        'email' => 'admin-hide-dev@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('tenant_admin');

    $developer = User::query()->create([
        'tenant_id' => null,
        'name' => 'Platform Dev',
        'email' => 'hidden-dev@platform.test',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/core/users', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonMissing(['email' => 'hidden-dev@platform.test']);

    $this->getJson('/api/v1/core/users/'.$developer->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertNotFound();
});

it('allows developer to list users without tenant context', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

    $developer = User::query()->create([
        'tenant_id' => null,
        'name' => 'Developer',
        'email' => 'developer-no-tenant-users@platform.test',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant User',
        'email' => 'tenant-user@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    $token = $developer->createToken('developer-no-tenant-users-token')->plainTextToken;

    $this->getJson('/api/v1/core/users', [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonFragment(['email' => 'developer-no-tenant-users@platform.test'])
        ->assertJsonFragment(['email' => 'tenant-user@tenant-a.test']);
});
