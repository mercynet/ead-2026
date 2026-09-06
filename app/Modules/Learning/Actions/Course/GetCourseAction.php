<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;

class GetCourseAction
{
    /**
     * @param  list<string>  $with
     */
    public function handle(ApiContext $context, int $courseId, array $with = []): Course
    {
        $query = Course::query()
            ->where('tenant_id', $context->tenant?->id)
            ->with($with);

        return $query->findOrFail($courseId);
    }
}
