<?php

namespace App\Actions\Core\Auth;

use App\Models\User;

class LogoutAction
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
