<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsByRole = [
            'developer' => [
                'core.users.list',
                'core.users.show',
                'core.users.update-self',
                'learning.categories.list',
                'learning.categories.system.manage',
                'learning.categories.tenant.create',
                'learning.categories.tenant.update',
                'learning.categories.tenant.delete',
                'learning.catalog.courses.attach-categories',
                'learning.catalog.courses.list',
                'learning.catalog.courses.show',
            ],
            'tenant_admin' => [
                'core.users.list',
                'core.users.show',
                'learning.categories.list',
                'learning.categories.tenant.create',
                'learning.categories.tenant.update',
                'learning.categories.tenant.delete',
                'learning.catalog.courses.attach-categories',
                'learning.catalog.courses.list',
                'learning.catalog.courses.show',
            ],
            'instructor' => [
                'core.users.show',
                'learning.categories.list',
                'learning.catalog.courses.attach-categories',
                'learning.catalog.courses.list',
                'learning.catalog.courses.show',
            ],
            'student' => [
                'core.users.show',
                'learning.categories.list',
                'learning.catalog.courses.list',
                'learning.catalog.courses.show',
            ],
        ];

        foreach ($permissionsByRole as $roleName => $allowedPermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($allowedPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
