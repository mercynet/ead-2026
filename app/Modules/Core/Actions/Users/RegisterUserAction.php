<?php

namespace App\Modules\Core\Actions\Users;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
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
