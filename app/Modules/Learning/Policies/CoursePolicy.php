<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;

class CoursePolicy
{
    public function list(User $authenticatedUser, ?Tenant $tenant = null): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->belongsToTenant($tenant)
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.list');
    }

    public function show(User $authenticatedUser, ?Tenant $tenant, ?Course $course = null): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->belongsToTenant($tenant)
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.view');
    }

    public function modules(User $authenticatedUser, ?Tenant $tenant): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->belongsToTenant($tenant)
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.view');
    }

    public function update(User $authenticatedUser, Tenant $tenant, Course $course): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if (! $authenticatedUser->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $course->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.update');
    }

    public function delete(User $authenticatedUser, Tenant $tenant, Course $course): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if (! $authenticatedUser->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $course->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.delete');
    }
}
