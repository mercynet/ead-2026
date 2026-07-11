<?php

namespace App\Modules\Learning\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;

class LessonPolicy
{
    public function create(User $user, ?Tenant $tenant = null, ?int $courseModuleId = null): bool
    {
        if (! $this->allows($user, $tenant, 'learning.lessons.create')) {
            return false;
        }

        return $user->isDeveloper()
            || ! $this->deniedByInstructorModuleOwnership($user, $tenant, $courseModuleId);
    }

    public function view(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.lessons.view');
    }

    public function progress(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'learning.progress.update');
    }

    public function update(User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool
    {
        return $this->allowsForOwnedLesson($user, $tenant, $lesson, 'learning.lessons.update');
    }

    public function delete(User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool
    {
        return $this->allowsForOwnedLesson($user, $tenant, $lesson, 'learning.lessons.delete');
    }

    public function reorder(User $user, ?Tenant $tenant = null, ?int $courseModuleId = null): bool
    {
        if (! $this->allows($user, $tenant, 'learning.lessons.reorder')) {
            return false;
        }

        return $user->isDeveloper()
            || ! $this->deniedByInstructorModuleOwnership($user, $tenant, $courseModuleId);
    }

    public function storeMedia(User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool
    {
        return $this->allowsForOwnedLesson($user, $tenant, $lesson, 'learning.lessons.create');
    }

    public function updateMedia(User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool
    {
        return $this->allowsForOwnedLesson($user, $tenant, $lesson, 'learning.lessons.update');
    }

    public function deleteMedia(User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool
    {
        return $this->allowsForOwnedLesson($user, $tenant, $lesson, 'learning.lessons.delete');
    }

    private function allows(User $user, ?Tenant $tenant, string $permission): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        return $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', $permission);
    }

    private function allowsForOwnedLesson(User $user, ?Tenant $tenant, ?Lesson $lesson, string $permission): bool
    {
        if (! $this->allows($user, $tenant, $permission)) {
            return false;
        }

        return $user->isDeveloper()
            || ! $this->deniedByInstructorOwnership($user, $lesson);
    }

    /**
     * Instructor só muta aulas do próprio curso (rbac.md, matriz `own`).
     */
    private function deniedByInstructorOwnership(User $user, ?Lesson $lesson): bool
    {
        if (! $user->isInstructor()) {
            return false;
        }

        return $lesson === null
            || (int) $lesson->courseModule?->course?->instructor_id !== (int) $user->id;
    }

    /**
     * Variante para gates sem model carregado (create/reorder): resolve o módulo
     * pelo ID já validado tenant-scoped; instructor sem módulo resolvível = negado.
     */
    private function deniedByInstructorModuleOwnership(User $user, ?Tenant $tenant, ?int $courseModuleId): bool
    {
        if (! $user->isInstructor()) {
            return false;
        }

        if ($tenant === null || $courseModuleId === null) {
            return true;
        }

        $courseModule = CourseModule::query()
            ->with('course')
            ->where('tenant_id', $tenant->id)
            ->whereKey($courseModuleId)
            ->first();

        return $courseModule === null
            || (int) $courseModule->course?->instructor_id !== (int) $user->id;
    }
}
