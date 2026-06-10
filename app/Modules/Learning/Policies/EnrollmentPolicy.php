<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class EnrollmentPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }
}
