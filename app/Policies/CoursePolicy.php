<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;

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
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.catalog.courses.list');
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
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.catalog.courses.show');
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
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.course.modules');
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
