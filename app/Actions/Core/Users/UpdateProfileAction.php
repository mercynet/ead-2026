<?php

namespace App\Actions\Core\Users;

use App\Models\User;

class UpdateProfileAction
{
    public function handle(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user;
    }
}
