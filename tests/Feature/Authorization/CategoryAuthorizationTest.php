<?php

use App\Enums\UserType;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows tenant admin to update and delete own tenant category', function (): void {
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
        'email' => 'auth-admin@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'parent_id' => null,
        'name' => 'Categoria',
        'slug' => 'categoria',
        'normalized_name' => 'categoria',
        'is_system' => false,
    ]);

    expect(Gate::forUser($admin)->allows('learning.categories.tenant.update-check', [$tenant, $category]))->toBeTrue();
    expect(Gate::forUser($admin)->allows('learning.categories.tenant.delete-check', [$tenant, $category]))->toBeTrue();
});

it('denies tenant admin update and delete for system category', function (): void {
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
        'email' => 'auth-admin-2@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $systemCategory = Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Sistema',
        'slug' => 'sistema',
        'normalized_name' => 'sistema',
        'is_system' => true,
    ]);

    expect(Gate::forUser($admin)->allows('learning.categories.tenant.update-check', [$tenant, $systemCategory]))->toBeFalse();
    expect(Gate::forUser($admin)->allows('learning.categories.tenant.delete-check', [$tenant, $systemCategory]))->toBeFalse();
});

it('allows developer to update and delete system categories', function (): void {
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
        'email' => 'auth-dev@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $systemCategory = Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Plataforma',
        'slug' => 'plataforma',
        'normalized_name' => 'plataforma',
        'is_system' => true,
    ]);

    expect(Gate::forUser($developer)->allows('learning.categories.tenant.update-check', [$tenant, $systemCategory]))->toBeTrue();
    expect(Gate::forUser($developer)->allows('learning.categories.tenant.delete-check', [$tenant, $systemCategory]))->toBeTrue();
});
