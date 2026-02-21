<?php

namespace App\Http\Resources\Core\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->roles->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
        ])->values()->toArray();

        $permissions = $this->getAllPermissions()->map(fn ($permission) => [
            'id' => $permission->id,
            'name' => $permission->name,
        ])->values()->toArray();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
