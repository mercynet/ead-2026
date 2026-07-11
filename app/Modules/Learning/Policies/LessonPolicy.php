<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class LessonPolicy
{
    public function create(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.create');
    }

    public function view(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.view');
    }

    public function progress(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.progress.update');
    }

    public function update(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.update');
    }

    public function delete(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.delete');
    }

    public function reorder(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.reorder');
    }

    private function allows(User $user, ?Tenant $tenant, string $permission): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', $permission);
    }
}
