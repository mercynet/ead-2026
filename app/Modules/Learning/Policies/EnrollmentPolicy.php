<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Enrollment;

class EnrollmentPolicy
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
            && $user->getAllPermissions()->contains('name', 'learning.enrollments.list');
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.enrollments.create');
    }

    public function view(User $user, ?Tenant $tenant = null, ?Enrollment $enrollment = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        if (! $user->belongsToTenant($tenant)) {
            return false;
        }

        if (! $user->getAllPermissions()->contains('name', 'learning.enrollments.view')) {
            return false;
        }

        if ($enrollment === null) {
            return true;
        }

        if ((int) $enrollment->tenant_id !== (int) $tenant->id) {
            return false;
        }

        if ($user->isStudent()) {
            return (int) $enrollment->user_id === (int) $user->id;
        }

        return $user->isAdmin() || $user->isInstructor();
    }

    public function update(User $user, ?Tenant $tenant = null, ?Enrollment $enrollment = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $enrollment === null) {
            return false;
        }

        if ((int) $enrollment->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.enrollments.update');
    }

    public function delete(User $user, ?Tenant $tenant = null, ?Enrollment $enrollment = null): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $enrollment === null) {
            return false;
        }

        if ((int) $enrollment->tenant_id !== (int) $tenant->id) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', 'learning.enrollments.delete');
    }
}
