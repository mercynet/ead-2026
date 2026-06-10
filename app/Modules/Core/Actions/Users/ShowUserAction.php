<?php

namespace App\Modules\Core\Actions\Users;

use App\Modules\Core\Models\User;

class ShowUserAction
{
    public function handle(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'headline' => $user->headline,
            'bio' => $user->bio,
            'avatar' => $user->avatar,
            'cpf' => $user->cpf,
            'linkedin_url' => $user->linkedin_url,
            'twitter_url' => $user->twitter_url,
        ];
    }
}
