<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    $this->postJson('/api/v1/admin/categories', [
        'name' => 'Desenvolvimento de Software',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnprocessable();
});

it('allows developer to create system category from the mzrt area', function (): void {
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

    $this->postJson('/api/v1/mzrt/categories', [
        'name' => 'Data Science',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_system', true)
        ->assertJsonPath('data.tenant_id', null);
});

it('rejects is_system on the admin category surface', function (): void {
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

    $this->postJson('/api/v1/admin/categories', [
        'name' => 'System Forbidden',
        'is_system' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect(Category::query()->where('name', 'System Forbidden')->exists())->toBeFalse();
});

it('forbids tenant admin from the mzrt category area', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);
    $admin->assignRole('admin');

    $this->postJson('/api/v1/mzrt/categories', [
        'name' => 'Tentativa Admin',
    ], [
        'Authorization' => 'Bearer '.$admin->createToken('admin-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');

    expect(Category::query()->where('name', 'Tentativa Admin')->exists())->toBeFalse();
});

it('forbids developer from the admin category area', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::factory()->create(['tenant_id' => null, 'user_type' => UserType::Developer]);
    $developer->assignRole('developer');

    $this->postJson('/api/v1/admin/categories', [
        'name' => 'Tentativa Developer',
    ], [
        'Authorization' => 'Bearer '.$developer->createToken('developer-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');

    expect(Category::query()->where('name', 'Tentativa Developer')->exists())->toBeFalse();
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

    $this->postJson('/api/v1/admin/categories', [
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

it('updates a tenant category', function (): void {
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
        'email' => 'admin-update@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'parent_id' => null,
        'name' => 'Categoria Original',
        'slug' => 'categoria-original',
        'normalized_name' => 'categoria original',
        'is_system' => false,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->putJson('/api/v1/admin/categories/'.$category->id, [
        'name' => 'Categoria Atualizada',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Categoria Atualizada')
        ->assertJsonPath('data.slug', 'categoria-atualizada');
});

it('deletes a tenant category', function (): void {
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
        'email' => 'admin-delete@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'parent_id' => null,
        'name' => 'Categoria para Deletar',
        'slug' => 'categoria-para-deletar',
        'normalized_name' => 'categoria para deletar',
        'is_system' => false,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->deleteJson('/api/v1/admin/categories/'.$category->id, [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.message', 'Category deleted successfully.')
        ->assertJsonMissingPath('message');

    expect(Category::query()->find($category->id))->toBeNull();
});

it('blocks developer from deleting a system category attached to courses', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::factory()->create(['tenant_id' => null, 'user_type' => UserType::Developer]);
    $developer->assignRole('developer');
    $category = Category::factory()->create(['tenant_id' => null, 'is_system' => true]);
    $course = Course::factory()->for($tenant)->create();
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

    $this->deleteJson('/api/v1/mzrt/categories/'.$category->id, [], [
        'Authorization' => 'Bearer '.$developer->createToken('developer-token')->plainTextToken,
    ])->assertUnprocessable()->assertJsonPath('errors.0.code', 'validation_error');

    expect(Category::query()->find($category->id))->not->toBeNull();
    expect(DB::table('category_course')->where('category_id', $category->id)->count())->toBe(1);
});

it('blocks tenant category deletion with attached courses without force and confirm', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);
    $admin->assignRole('admin');
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'is_system' => false]);
    $course = Course::factory()->for($tenant)->create();
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

    $this->deleteJson('/api/v1/admin/categories/'.$category->id, [], [
        'Authorization' => 'Bearer '.$admin->createToken('admin-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnprocessable()->assertJsonPath('errors.0.code', 'validation_error');

    expect(Category::query()->find($category->id))->not->toBeNull();
    expect(DB::table('category_course')->where('category_id', $category->id)->count())->toBe(1);
});

it('requires both force and confirm to delete a tenant category attached to courses', function (array $payload): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);
    $admin->assignRole('admin');
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'is_system' => false]);
    $course = Course::factory()->for($tenant)->create();
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

    $this->deleteJson('/api/v1/admin/categories/'.$category->id, $payload, [
        'Authorization' => 'Bearer '.$admin->createToken('admin-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnprocessable()->assertJsonPath('errors.0.code', 'validation_error');

    expect(Category::query()->find($category->id))->not->toBeNull();
    expect(DB::table('category_course')->where('category_id', $category->id)->count())->toBe(1);
})->with([
    'force without confirm' => [['force' => true]],
    'confirm without force' => [['confirm' => true]],
]);

it('detaches courses and soft deletes a tenant category with force and confirmation', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);
    $admin->assignRole('admin');
    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'is_system' => false]);
    $course = Course::factory()->for($tenant)->create();
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

    $this->deleteJson('/api/v1/admin/categories/'.$category->id, [
        'force' => true,
        'confirm' => true,
    ], [
        'Authorization' => 'Bearer '.$admin->createToken('admin-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.message', 'Category deleted successfully.')
        ->assertJsonMissingPath('message');

    expect(Category::withTrashed()->find($category->id)?->deleted_at)->not->toBeNull();
    expect(DB::table('category_course')->where('category_id', $category->id)->count())->toBe(0);
    expect($course->categories()->withTrashed()->count())->toBe(0);
});

it('forbids updating system category by tenant admin', function (): void {
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
        'email' => 'admin-sys@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $systemCategory = Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Sistema Categoria',
        'slug' => 'sistema-categoria',
        'normalized_name' => 'sistema categoria',
        'is_system' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->putJson('/api/v1/admin/categories/'.$systemCategory->id, [
        'name' => 'Tentativa de Atualizar',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'access_denied');

    expect($systemCategory->fresh()->name)->toBe('Sistema Categoria');
});

it('allows developer to update system category from the mzrt area', function (): void {
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'dev-update-sys@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $systemCategory = Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Sistema Original',
        'slug' => 'sistema-original',
        'normalized_name' => 'sistema original',
        'is_system' => true,
    ]);

    $token = $developer->createToken('dev-token')->plainTextToken;

    $this->putJson('/api/v1/mzrt/categories/'.$systemCategory->id, [
        'name' => 'Sistema Atualizado',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Sistema Atualizado');
});

it('hides tenant categories from the mzrt area', function (): void {
    $tenant = Tenant::factory()->create();
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::factory()->create(['tenant_id' => null, 'user_type' => UserType::Developer]);
    $developer->assignRole('developer');
    $tenantCategory = Category::factory()->for($tenant)->create(['is_system' => false]);

    $this->putJson('/api/v1/mzrt/categories/'.$tenantCategory->id, [
        'name' => 'Tentativa Mzrt',
    ], [
        'Authorization' => 'Bearer '.$developer->createToken('developer-token')->plainTextToken,
    ])
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');

    expect($tenantCategory->fresh()->name)->not->toBe('Tentativa Mzrt');
});
