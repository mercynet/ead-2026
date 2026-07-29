<?php

namespace App\Modules\Learning\Services;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Contracts\CourseCheckoutCatalog;
use App\Modules\Learning\Contracts\CourseCheckoutOffering;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Shared\Exceptions\ResourceNotFoundException;

class CourseCheckoutCatalogResolver implements CourseCheckoutCatalog
{
    public function resolve(int $tenantId, int $userId, int $courseId): CourseCheckoutOffering
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            throw ResourceNotFoundException::course($courseId);
        }

        $user = $tenant->users()->whereKey($userId)->first();

        if ($user === null) {
            throw ResourceNotFoundException::course($courseId);
        }

        $course = Course::query()
            ->whereKey($courseId)
            ->where('tenant_id', $tenantId)
            ->whereBelongsTo($tenant, 'tenant')
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();

        if ($course === null) {
            throw ResourceNotFoundException::course($courseId);
        }

        $currentEnrollment = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->whereBelongsTo($tenant, 'tenant')
            ->whereBelongsTo($course, 'course')
            ->whereBelongsTo($user, 'user')
            ->currentStatuses()
            ->orderedByCurrentStatusPriority()
            ->first();

        $latestTerminalEnrollment = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->whereBelongsTo($tenant, 'tenant')
            ->whereBelongsTo($course, 'course')
            ->whereBelongsTo($user, 'user')
            ->whereIn('status', ['cancelled', 'expired'])
            ->latest('id')
            ->first();

        return new CourseCheckoutOffering(
            courseId: $course->id,
            priceCents: $course->price_cents,
            snapshot: [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            purchaseCycleKey: $this->purchaseCycleKey(
                tenantId: $tenantId,
                userId: $userId,
                courseId: $course->id,
                terminalEnrollment: $latestTerminalEnrollment,
            ),
            isEligible: $currentEnrollment === null,
        );
    }

    private function purchaseCycleKey(
        int $tenantId,
        int $userId,
        int $courseId,
        ?Enrollment $terminalEnrollment,
    ): string {
        $cycle = [
            'course',
            (string) $tenantId,
            (string) $userId,
            (string) $courseId,
        ];

        if ($terminalEnrollment !== null) {
            $cycle[] = (string) $terminalEnrollment->id;
            $cycle[] = $terminalEnrollment->status;
        }

        return hash('sha256', implode(':', $cycle));
    }
}
