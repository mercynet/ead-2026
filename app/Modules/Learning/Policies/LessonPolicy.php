<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class LessonPolicy
{
    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && ! $user->isStudent();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function progress(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && ! $user->isStudent();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && ! $user->isStudent();
    }
}
