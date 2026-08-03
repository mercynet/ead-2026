<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function list(User $authenticatedUser, ?Tenant $tenant = null): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->isTenantAdmin()
            && $authenticatedUser->belongsToTenant($tenant);
    }

    public function show(User $authenticatedUser, ?Tenant $tenant, User $targetUser): Response|bool
    {
        if (! $authenticatedUser->isDeveloper() && $targetUser->isDeveloper()) {
            return Response::denyAsNotFound();
        }

        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
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

    public function update(User $authenticatedUser, ?Tenant $tenant, User $targetUser): Response|bool
    {
        return $this->manage($authenticatedUser, $tenant, $targetUser, 'core.users.update');
    }

    public function delete(User $authenticatedUser, ?Tenant $tenant, User $targetUser): Response|bool
    {
        return $this->manage($authenticatedUser, $tenant, $targetUser, 'core.users.delete');
    }

    /**
     * Admin administra instructor e student do próprio tenant. Outro admin e
     * developer ficam fora: admin editando admin é escalada lateral, e mutação de
     * `user_type` — o teto de permissions — é exclusiva de developer
     * (`subspecs/users.md`).
     */
    private function manage(User $authenticatedUser, ?Tenant $tenant, User $targetUser, string $permission): Response|bool
    {
        if ($targetUser->isDeveloper()) {
            return Response::denyAsNotFound();
        }

        if ($authenticatedUser->is($targetUser)) {
            return false;
        }

        if ($tenant === null || ! $authenticatedUser->belongsToTenant($tenant)) {
            return false;
        }

        if (! $targetUser->belongsToTenant($tenant)) {
            return Response::denyAsNotFound();
        }

        if (! $authenticatedUser->isTenantAdmin()) {
            return false;
        }

        if (! $targetUser->isInstructor() && ! $targetUser->isStudent()) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', $permission);
    }

    public function updateSelf(User $authenticatedUser, ?Tenant $tenant, User $targetUser): bool
    {
        if (! $authenticatedUser->is($targetUser)) {
            return false;
        }

        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->belongsToTenant($tenant);
    }
}
