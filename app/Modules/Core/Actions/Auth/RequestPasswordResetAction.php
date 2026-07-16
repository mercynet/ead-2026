<?php

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\PasswordReset;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Notifications\PasswordResetNotification;
use Illuminate\Support\Str;

class RequestPasswordResetAction
{
    /**
     * Emite um pedido de redefinição tenant-scoped e notifica o usuário por
     * e-mail com o token opaco (só o hash é persistido). Se o email não existir
     * no tenant, não faz nada — o controller responde igual em ambos os casos
     * (anti-enumeração). Pedidos pendentes anteriores são invalidados.
     */
    public function handle(Tenant $tenant, string $email): void
    {
        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return;
        }

        PasswordReset::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $token = Str::random(64);

        PasswordReset::query()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'token_hash' => PasswordReset::hashToken($token),
            'expires_at' => now()->addMinutes(PasswordReset::EXPIRES_IN_MINUTES),
        ]);

        $user->notify(new PasswordResetNotification($token));
    }
}
