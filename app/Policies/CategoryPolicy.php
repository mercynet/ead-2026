<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;

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

    public function create(User $user, ?Tenant $tenant = null, bool $isSystem = false): bool
    {
        if ($isSystem) {
            return $this->manageSystem($user);
        }

        return $this->createTenant($user, $tenant);
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
            && $user->getAllPermissions()->contains('name', 'learning.categories.tenant.create');
    }

    public function manageSystem(User $user): bool
    {
        return $user->isDeveloper()
            && $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
    }

    public function update(User $user, Tenant $tenant, Category $category): bool
    {
        if ($user->isDeveloper()) {
            if ($category->is_system) {
                return $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
            }

            return $user->getAllPermissions()->contains('name', 'learning.categories.tenant.update');
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ($category->is_system || (int) $category->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.categories.tenant.update');
    }

    public function delete(User $user, Tenant $tenant, Category $category): bool
    {
        if ($user->isDeveloper()) {
            if ($category->is_system) {
                return $user->getAllPermissions()->contains('name', 'learning.categories.system.manage');
            }

            return $user->getAllPermissions()->contains('name', 'learning.categories.tenant.delete');
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if ($category->is_system || (int) $category->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.categories.tenant.delete');
    }
}
