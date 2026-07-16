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

        $this->revokeOtherSessions($user);
    }

    /**
     * Revoga todas as sessões Sanctum do usuário exceto a atual (a que fez a
     * troca) — política canônica da troca autenticada de senha. O reset por
     * token (não autenticado) revoga todas.
     */
    private function revokeOtherSessions(User $user): void
    {
        $currentTokenId = $user->currentAccessToken()->getKey();

        $user->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();
    }
}
