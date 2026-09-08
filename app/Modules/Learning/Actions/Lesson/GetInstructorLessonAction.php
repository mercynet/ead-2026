<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;

class GetInstructorLessonAction
{
    public function handle(ApiContext $context, int $lessonId): Lesson
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($lessonId)
            ->whereHas('courseModule.course', fn ($query) => $query->where('instructor_id', $context->requiredUser()->id))
            ->with(['courseModule.course:id,title,instructor_id'])
            ->findOrFail($lessonId);
    }
}
