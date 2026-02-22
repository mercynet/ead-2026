<?php

namespace App\Actions\Learning\Enrollment;

use App\Http\Context\ApiContext;
use App\Models\Enrollment;

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
