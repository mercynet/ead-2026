<?php

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\User;

class LogoutAction
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
