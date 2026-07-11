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

    public function preview(User $authenticatedUser, ?Tenant $tenant, ?Course $course = null): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null || $course === null) {
            return false;
        }

        if (! $authenticatedUser->belongsToTenant($tenant)) {
            return false;
        }

        if ((int) $course->tenant_id !== (int) $tenant->id) {
            return false;
        }

        if (! $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.view')) {
            return false;
        }

        if ($authenticatedUser->isStudent()) {
            return false;
        }

        if ($authenticatedUser->isInstructor()) {
            return (int) $course->instructor_id === (int) $authenticatedUser->id;
        }

        return $authenticatedUser->isAdmin();
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

    public function create(User $authenticatedUser, ?Tenant $tenant = null): bool
    {
        if ($authenticatedUser->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $authenticatedUser->belongsToTenant($tenant)
            && $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.create');
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

        if ($this->deniedByInstructorOwnership($authenticatedUser, $course)) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.update');
    }

    public function publish(User $authenticatedUser, Tenant $tenant, Course $course): bool
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

        if ($this->deniedByInstructorOwnership($authenticatedUser, $course)) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.publish');
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

        if ($this->deniedByInstructorOwnership($authenticatedUser, $course)) {
            return false;
        }

        return $authenticatedUser->getAllPermissions()->contains('name', 'learning.courses.delete');
    }

    /**
     * Instructor só muta o próprio conteúdo (rbac.md, matriz `own`).
     */
    private function deniedByInstructorOwnership(User $authenticatedUser, Course $course): bool
    {
        return $authenticatedUser->isInstructor()
            && (int) $course->instructor_id !== (int) $authenticatedUser->id;
    }
}
