<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

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
