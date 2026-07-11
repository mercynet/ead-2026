<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;

class GetEnrollmentAction
{
    public function handle(ApiContext $context, int $courseId): ?Enrollment
    {
        return Enrollment::query()
            ->forTenantUserCourse(
                $context->requiredTenant()->id,
                $context->requiredUser()->id,
                $courseId
            )
            ->orderedByCurrentStatusPriority()
            ->with(['course:id,title,slug'])
            ->first();
    }
}
