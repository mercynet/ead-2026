<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\CourseModule;

class CourseModulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $courseModule === null) {
            return false;
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $courseModule->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.modules.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $courseModule === null) {
            return false;
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $courseModule->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $courseModule === null) {
            return false;
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $courseModule->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.delete');
    }

    public function reorder(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.modules.reorder');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CourseModule $courseModule): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CourseModule $courseModule): bool
    {
        return false;
    }
}
