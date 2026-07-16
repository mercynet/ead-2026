<?php

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\PasswordReset;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\PasswordResetInvalidException;
use Illuminate\Support\Facades\DB;

class ResetPasswordAction
{
    /**
     * Consome um token de redefinição (tenant/email vêm do próprio token) e
     * troca a senha do usuário. Resolvido/validado sob lock na transação: token
     * inexistente, adulterado, expirado ou já usado falha genericamente e uso é
     * único. Revoga todas as sessões Sanctum do usuário (a troca de senha
     * invalida acessos anteriores).
     */
    public function handle(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password): void {
            $reset = PasswordReset::query()
                ->where('token_hash', PasswordReset::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($reset === null || ! $reset->isPending()) {
                throw PasswordResetInvalidException::make();
            }

            $user = User::query()
                ->where('tenant_id', $reset->tenant_id)
                ->where('email', $reset->email)
                ->first();

            if ($user === null) {
                throw PasswordResetInvalidException::make();
            }

            $user->update(['password' => $password]);
            $user->tokens()->delete();

            $reset->update(['used_at' => now()]);
        });
    }
}
