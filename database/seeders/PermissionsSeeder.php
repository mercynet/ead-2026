<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'core.users.list',
            'core.users.show',
            'core.users.update-self',
            'learning.categories.system.manage',
            'learning.categories.tenant.create',
            'learning.categories.tenant.update',
            'learning.categories.tenant.delete',
            'learning.catalog.courses.attach-categories',
            'learning.catalog.courses.list',
            'learning.catalog.courses.show',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
