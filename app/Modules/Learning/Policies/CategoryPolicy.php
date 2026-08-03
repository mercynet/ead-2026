<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;

class CategoryPolicy
{
    public function list(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.categories.list');
    }

    public function createTenant(User $user, ?Tenant $tenant = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.categories.create');
    }

    public function manageSystem(User $user): bool
    {
        return $user->isDeveloper()
            && $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
    }

    public function update(User $user, ?Tenant $tenant, Category $category): bool
    {
        if ($user->isDeveloper()) {
            if ($category->is_system) {
                return $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
            }

            return $user->getAllPermissions()->contains('name', 'learning.categories.update');
        }

        if ($tenant === null || ! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ($category->is_system || (int) $category->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.categories.update');
    }

    public function delete(User $user, ?Tenant $tenant, Category $category): bool
    {
        if ($user->isDeveloper()) {
            if ($category->is_system) {
                return $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
            }

            return $user->getAllPermissions()->contains('name', 'learning.categories.delete');
        }

        if ($tenant === null || ! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ($category->is_system || (int) $category->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.categories.delete');
    }
}
