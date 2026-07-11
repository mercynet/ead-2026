<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class QuizAttemptPolicy
{
    public function create(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return $user->getAllPermissions()->contains('name', 'assessment.attempts.create');
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'assessment.attempts.create');
    }

    public function view(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'assessment.attempts.view');
    }

    public function answer(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'assessment.attempts.answer');
    }

    public function finish(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'assessment.attempts.finish');
    }
}
