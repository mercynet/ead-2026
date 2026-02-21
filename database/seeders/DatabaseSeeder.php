<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['domain' => 'seed-tenant.local'],
            [
                'name' => 'Seed Tenant',
                'database' => null,
                'description' => 'Default tenant for seeded users.',
                'is_active' => true,
            ],
        );

        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
        ]);

        $usersByRole = [
            'developer' => [
                'name' => 'Developer User',
                'email' => 'developer@example.com',
                'tenant_id' => null,
            ],
            'tenant_admin' => [
                'name' => 'Tenant Admin User',
                'email' => 'tenant_admin@example.com',
                'tenant_id' => $tenant->id,
            ],
            'instructor' => [
                'name' => 'Instructor User',
                'email' => 'instructor@example.com',
                'tenant_id' => $tenant->id,
            ],
            'student' => [
                'name' => 'Student User',
                'email' => 'student@example.com',
                'tenant_id' => $tenant->id,
            ],
        ];

        foreach ($usersByRole as $roleName => $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'tenant_id' => $userData['tenant_id'],
                    'name' => $userData['name'],
                    'password' => Hash::make('password123'),
                ],
            );

            $user->syncRoles([$roleName]);
        }
    }
}
