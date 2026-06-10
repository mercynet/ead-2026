<?php

namespace App\Modules\Core\Actions\Users;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdatePasswordAction
{
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is invalid.',
            ]);
        }

        $user->password = $newPassword;
        $user->save();
    }
}
