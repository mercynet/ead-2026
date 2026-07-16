<?php

namespace App\Modules\Core\Http\Resources\Invitations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Core\Models\Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * Token opaco em claro — só existe na resposta de criação; nunca persistido.
     */
    public ?string $plainToken = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'token' => $this->plainToken,
            'expires_at' => $this->expires_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
