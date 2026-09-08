<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class StudentPolicy
{
    public function coursesList(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.courses.list');
    }

    public function coursesView(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.courses.view');
    }

    public function lessonsView(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.view');
    }

    public function enrollmentView(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.enrollments.view');
    }

    public function progressUpdate(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.progress.update');
    }

    private function allows(User $user, ?Tenant $tenant, string $permission): bool
    {
        return $tenant !== null
            && $user->isStudent()
            && $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', $permission);
    }
}
