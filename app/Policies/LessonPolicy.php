<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class LessonPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function progress(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }
}
