<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
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

        if ($this->deniedByInstructorOwnership($user, $courseModule)) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Tenant $tenant = null, ?int $courseId = null): bool
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

        if ($this->deniedByInstructorCourseOwnership($user, $tenant, $courseId)) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.create');
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

        if ($this->deniedByInstructorOwnership($user, $courseModule)) {
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

        if ($this->deniedByInstructorOwnership($user, $courseModule)) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.delete');
    }

    public function reorder(User $user, ?Tenant $tenant = null, ?int $courseId = null): bool
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

        if ($this->deniedByInstructorCourseOwnership($user, $tenant, $courseId)) {
            return false;
        }

        return $user->getAllPermissions()->contains('name', 'learning.modules.reorder');
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

    /**
     * Instructor só muta módulos do próprio curso (rbac.md, matriz `own`).
     */
    private function deniedByInstructorOwnership(User $user, CourseModule $courseModule): bool
    {
        return $user->isInstructor()
            && (int) $courseModule->course?->instructor_id !== (int) $user->id;
    }

    /**
     * Variante para gates sem model carregado (create/reorder): resolve o curso
     * pelo ID já validado tenant-scoped; instructor sem curso resolvível = negado.
     */
    private function deniedByInstructorCourseOwnership(User $user, Tenant $tenant, ?int $courseId): bool
    {
        if (! $user->isInstructor()) {
            return false;
        }

        if ($courseId === null) {
            return true;
        }

        $course = Course::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($courseId)
            ->first();

        return $course === null || (int) $course->instructor_id !== (int) $user->id;
    }
}
