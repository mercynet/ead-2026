<?php

namespace App\Actions\Core\Auth;

use App\Models\User;

class MeAction
{
    public function handle(User $user): array
    {
        $roles = $user->roles->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
        ])->values()->toArray();

        $permissions = $user->getAllPermissions()->map(fn ($permission) => [
            'id' => $permission->id,
            'name' => $permission->name,
        ])->values()->toArray();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'headline' => $user->headline,
            'bio' => $user->bio,
            'avatar' => $user->avatar,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
