<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class QuizAttemptPolicy
{
    public function create(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return $user->getAllPermissions()->contains('name', 'assessment.attempts.view');
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'assessment.attempts.view');
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
