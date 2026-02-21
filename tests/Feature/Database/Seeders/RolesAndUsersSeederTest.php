<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds one user for each base role and assigns role correctly', function (): void {
    $this->seed(DatabaseSeeder::class);

    $expectedUsersByRole = [
        'developer' => 'developer@example.com',
        'tenant_admin' => 'tenant_admin@example.com',
        'instructor' => 'instructor@example.com',
        'student' => 'student@example.com',
    ];

    foreach ($expectedUsersByRole as $roleName => $email) {
        $user = User::query()->where('email', $email)->first();

        expect($user)->not->toBeNull();
        expect($user?->hasRole($roleName))->toBeTrue();
    }
});

it('seeds catalog permissions and links them to the expected roles', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Permission::query()->where('name', 'learning.catalog.courses.list')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'learning.catalog.courses.show')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'learning.categories.system.manage')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'learning.categories.tenant.create')->exists())->toBeTrue();
    expect(Permission::query()->where('name', 'learning.catalog.courses.attach-categories')->exists())->toBeTrue();

    $studentRole = Role::query()->where('name', 'student')->first();
    $instructorRole = Role::query()->where('name', 'instructor')->first();
    $tenantAdminRole = Role::query()->where('name', 'tenant_admin')->first();
    $developerRole = Role::query()->where('name', 'developer')->first();

    expect($studentRole?->hasPermissionTo('learning.catalog.courses.list'))->toBeTrue();
    expect($studentRole?->hasPermissionTo('learning.catalog.courses.show'))->toBeTrue();
    expect($instructorRole?->hasPermissionTo('learning.catalog.courses.list'))->toBeTrue();
    expect($instructorRole?->hasPermissionTo('learning.catalog.courses.show'))->toBeTrue();
    expect($instructorRole?->hasPermissionTo('learning.catalog.courses.attach-categories'))->toBeTrue();
    expect($tenantAdminRole?->hasPermissionTo('learning.categories.tenant.create'))->toBeTrue();
    expect($developerRole?->hasPermissionTo('learning.categories.system.manage'))->toBeTrue();
    expect($tenantAdminRole?->hasPermissionTo('learning.categories.system.manage'))->toBeFalse();
});
