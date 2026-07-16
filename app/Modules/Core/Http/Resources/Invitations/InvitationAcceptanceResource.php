<?php

namespace App\Modules\Core\Http\Resources\Invitations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta do aceite: dados do usuário criado + o tenant onde ele deve
 * autenticar (o cliente segue para `POST /auth/login` com este `X-Tenant-ID`).
 *
 * @mixin \App\Modules\Core\Models\User
 */
class InvitationAcceptanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'role' => $this->getRoleNames()->first(),
        ];
    }
}
