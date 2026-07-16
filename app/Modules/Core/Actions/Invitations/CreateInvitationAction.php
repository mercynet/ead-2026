<?php

namespace App\Modules\Core\Actions\Invitations;

use App\Modules\Core\Models\Invitation;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateInvitationAction
{
    /**
     * Emite um convite tenant-bound. O tenant e o autor vêm do contexto do
     * admin autenticado — nunca do corpo da requisição (sem escalada de tenant).
     * Só o hash do token é persistido; o token em claro é devolvido uma vez.
     *
     * A unicidade do email é checada aqui (após o Gate no controller), nunca no
     * FormRequest — assim um usuário sem permissão recebe 403 antes de qualquer
     * checagem, sem oráculo de enumeração de emails do tenant.
     *
     * @param  array{email: string, role: string}  $attributes
     * @return array{invitation: Invitation, token: string}
     *
     * @throws ValidationException
     */
    public function handle(Tenant $tenant, User $inviter, array $attributes): array
    {
        $email = (string) $attributes['email'];

        $emailTaken = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->exists();

        if ($emailTaken) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um usuário com este email neste tenant.',
            ]);
        }

        $token = Str::random(64);

        $invitation = Invitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => (string) $attributes['role'],
            'token_hash' => Invitation::hashToken($token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(Invitation::EXPIRES_IN_DAYS),
        ]);

        return [
            'invitation' => $invitation,
            'token' => $token,
        ];
    }
}
