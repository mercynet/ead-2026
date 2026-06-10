<?php

namespace Database\Seeders;

use App\Enums\UserType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deriva as roles de config/permissions.php (invariante 6): cada UserType vira
 * uma role global cujas permissions são exatamente as entradas do config que o
 * listam em `user_types`. Sem listas manuais — o config é a única fonte.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<string, array<string>> $permissionsByUserType */
        $permissionsByUserType = [];

        foreach (config('permissions') as $permissionName => $meta) {
            foreach ($meta['user_types'] as $userType) {
                $permissionsByUserType[$userType][] = $permissionName;
            }
        }

        foreach (UserType::cases() as $userType) {
            $role = Role::query()->firstOrCreate([
                'name' => $userType->value,
                'guard_name' => 'web',
            ]);

            $role->update([
                'scope' => 'global',
                'tenant_id' => null,
            ]);

            $permissions = $permissionsByUserType[$userType->value] ?? [];

            foreach ($permissions as $permissionName) {
                Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
