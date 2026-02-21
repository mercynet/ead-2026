<?php

namespace App\Actions\Core\Auth;

use App\Models\User;

class MeAction
{
    public function handle(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
