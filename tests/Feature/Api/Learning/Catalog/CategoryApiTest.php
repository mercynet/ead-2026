<?php

use App\Enums\UserType;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists system categories and current tenant categories only', function (): void {
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

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $adminA = User::query()->create([
        'tenant_id' => $tenantA->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin A',
        'email' => 'admina@test.local',
        'password' => Hash::make('password123'),
    ]);
    $adminA->assignRole('admin');

    Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Desenvolvimento de Software',
        'slug' => 'desenvolvimento-de-software',
        'normalized_name' => 'desenvolvimento de software',
        'is_system' => true,
    ]);
    Category::query()->create([
        'tenant_id' => $tenantA->id,
        'parent_id' => null,
        'name' => 'Categoria Tenant A',
        'slug' => 'categoria-tenant-a',
        'normalized_name' => 'categoria tenant a',
        'is_system' => false,
    ]);
    Category::query()->create([
        'tenant_id' => $tenantB->id,
        'parent_id' => null,
        'name' => 'Categoria Tenant B',
        'slug' => 'categoria-tenant-b',
        'normalized_name' => 'categoria tenant b',
        'is_system' => false,
    ]);

    $token = $adminA->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/learning/catalog/categories', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ])
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'desenvolvimento-de-software'])
        ->assertJsonFragment(['slug' => 'categoria-tenant-a'])
        ->assertJsonMissing(['slug' => 'categoria-tenant-b']);
});

it('prevents tenant from creating a category that duplicates a system category', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Desenvolvimento de Software',
        'slug' => 'desenvolvimento-de-software',
        'normalized_name' => 'desenvolvimento de software',
        'is_system' => true,
    ]);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');
    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/learning/catalog/categories', [
        'name' => 'Desenvolvimento de Software',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnprocessable();
});

it('allows developer to create system category', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'dev@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');
    $token = $developer->createToken('dev-token')->plainTextToken;

    $this->postJson('/api/v1/learning/catalog/categories', [
        'name' => 'Data Science',
        'is_system' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_system', true)
        ->assertJsonPath('data.tenant_id', null);
});

it('forbids tenant admin from creating system category', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin',
        'email' => 'admin2@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');
    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/learning/catalog/categories', [
        'name' => 'System Forbidden',
        'is_system' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('allows same tenant category name in different tenants when not system reserved', function (): void {
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

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    Category::query()->create([
        'tenant_id' => $tenantA->id,
        'parent_id' => null,
        'name' => 'Desenvolvimento de Programas',
        'slug' => 'desenvolvimento-de-programas',
        'normalized_name' => 'desenvolvimento de programas',
        'is_system' => false,
    ]);

    $adminB = User::query()->create([
        'tenant_id' => $tenantB->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin B',
        'email' => 'admin4@test.local',
        'password' => Hash::make('password123'),
    ]);
    $adminB->assignRole('admin');
    $adminBToken = $adminB->createToken('admin-b-token')->plainTextToken;

    $this->postJson('/api/v1/learning/catalog/categories', [
        'name' => 'Desenvolvimento de Programas',
    ], [
        'Authorization' => 'Bearer '.$adminBToken,
        'X-Tenant-ID' => (string) $tenantB->id,
    ])->assertCreated();
});

it('allows developer to list all categories without tenant context', function (): void {
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

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'developer-no-tenant@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');
    $token = $developer->createToken('developer-token')->plainTextToken;

    Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Categoria Sistema',
        'slug' => 'categoria-sistema',
        'normalized_name' => 'categoria sistema',
        'is_system' => true,
    ]);

    Category::query()->create([
        'tenant_id' => $tenantA->id,
        'parent_id' => null,
        'name' => 'Categoria A',
        'slug' => 'categoria-a',
        'normalized_name' => 'categoria a',
        'is_system' => false,
    ]);

    Category::query()->create([
        'tenant_id' => $tenantB->id,
        'parent_id' => null,
        'name' => 'Categoria B',
        'slug' => 'categoria-b',
        'normalized_name' => 'categoria b',
        'is_system' => false,
    ]);

    $this->getJson('/api/v1/learning/catalog/categories', [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'categoria-sistema'])
        ->assertJsonFragment(['slug' => 'categoria-a'])
        ->assertJsonFragment(['slug' => 'categoria-b']);
});

it('requires tenant for non developer when tenant context is missing', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Admin,
        'name' => 'Tenant Admin',
        'email' => 'tenant-admin-no-context@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');
    $token = $admin->createToken('tenant-admin-token')->plainTextToken;

    $this->getJson('/api/v1/learning/catalog/categories', [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'tenant_not_resolved');
});
