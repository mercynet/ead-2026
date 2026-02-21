<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function list(User $authenticatedUser, Tenant $tenant): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        return $authenticatedUser->isTenantAdmin()
            && $authenticatedUser->belongsToTenant($tenant);
    }

    public function show(User $authenticatedUser, Tenant $tenant, User $targetUser): Response|bool
    {
        if (! $authenticatedUser->isDeveloper() && $targetUser->isDeveloper()) {
            return Response::denyAsNotFound();
        }

        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if (! $authenticatedUser->belongsToTenant($tenant)) {
            return false;
        }

        if ($authenticatedUser->isTenantAdmin()) {
            if (! $targetUser->belongsToTenant($tenant)) {
                return Response::denyAsNotFound();
            }

            return true;
        }

        if ($authenticatedUser->isInstructor() || $authenticatedUser->isStudent()) {
            return $authenticatedUser->is($targetUser);
        }

        return false;
    }

    public function updateSelf(User $authenticatedUser, Tenant $tenant, User $targetUser): bool
    {
        if (! $authenticatedUser->is($targetUser)) {
            return false;
        }

        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        return $authenticatedUser->belongsToTenant($tenant);
    }
}
