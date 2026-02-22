<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'cpf' => $this->cpf,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
