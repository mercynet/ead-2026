<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;

class GetEnrollmentAction
{
    public function handle(ApiContext $context, int $courseId): ?Enrollment
    {
        return Enrollment::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $courseId)
            ->with(['course:id,title,slug'])
            ->first();
    }
}
