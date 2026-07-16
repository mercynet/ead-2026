<?php

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\PasswordReset;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestPasswordResetAction
{
    /**
     * Emite um pedido de redefinição tenant-scoped e notifica o usuário por
     * e-mail com o token opaco (só o hash é persistido). Se o email não existir
     * no tenant, não faz nada — o controller responde igual em ambos os casos
     * (anti-enumeração). Pedidos pendentes anteriores são invalidados.
     *
     * A invalidação + emissão correm sob lock do usuário numa transação: dois
     * pedidos concorrentes serializam, garantindo a invariante "um único token
     * válido por vez" (o 2º invalida o token que o 1º acabou de criar). A
     * notificação sai APÓS o commit para que o hash já esteja persistido quando
     * o job de e-mail (ShouldQueue) rodar.
     */
    public function handle(Tenant $tenant, string $email): void
    {
        /** @var array{user: User, token: string}|null $issued */
        $issued = DB::transaction(function () use ($tenant, $email): ?array {
            $user = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return null;
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

            return ['user' => $user, 'token' => $token];
        });

        if ($issued === null) {
            return;
        }

        $issued['user']->notify(new PasswordResetNotification($issued['token']));
    }
}
