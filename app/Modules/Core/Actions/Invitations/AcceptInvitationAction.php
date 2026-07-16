<?php

namespace App\Modules\Core\Actions\Invitations;

use App\Modules\Core\Models\Invitation;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\InvitationInvalidException;
use Illuminate\Support\Facades\DB;

class AcceptInvitationAction
{
    /**
     * Aceita um convite: resolve pelo hash do token, valida (existe, não expirou,
     * não foi usado) e cria o usuário com tenant/email/papel FIXOS do convite.
     * Token inexistente, adulterado, expirado ou já usado falha genericamente.
     * Uso único: o convite é marcado como aceito na mesma transação.
     *
     * @param  array{token: string, name: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $invitation = Invitation::query()
                ->where('token_hash', Invitation::hashToken((string) $attributes['token']))
                ->lockForUpdate()
                ->first();

            // Resolvido e validado sob lock: aceites concorrentes do mesmo token
            // serializam, garantindo uso único (o 2º vê o convite já aceito).
            if ($invitation === null || ! $invitation->isPending()) {
                throw InvitationInvalidException::make();
            }

            // O email pode ter sido ocupado entre a emissão e o aceite; falha
            // genérica evita colisão com o unique do banco (500) sem vazar detalhe.
            $emailTaken = User::query()
                ->where('tenant_id', $invitation->tenant_id)
                ->where('email', $invitation->email)
                ->exists();

            if ($emailTaken) {
                throw InvitationInvalidException::make();
            }

            $user = User::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'user_type' => $invitation->userType(),
                'name' => (string) $attributes['name'],
                'email' => $invitation->email,
                'password' => (string) $attributes['password'],
            ]);

            $user->assignRole($invitation->role);

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);

            return $user;
        });
    }
}
