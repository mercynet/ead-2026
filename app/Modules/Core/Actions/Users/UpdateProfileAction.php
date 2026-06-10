<?php

namespace App\Modules\Core\Actions\Users;

use App\Modules\Core\Models\User;

class UpdateProfileAction
{
    public function handle(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user;
    }
}
