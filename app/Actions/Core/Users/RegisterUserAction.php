<?php

namespace App\Actions\Core\Users;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RegisterUserAction
{
    public function handle(Tenant $tenant, array $attributes): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => (string) $attributes['name'],
            'email' => (string) $attributes['email'],
            'password' => (string) $attributes['password'],
        ]);

        Role::query()->firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);

        $user->assignRole('student');

        return $user;
    }
}
