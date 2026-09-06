<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;

class GetAdminLessonAction
{
    public function handle(ApiContext $context, int $lessonId): Lesson
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($lessonId)
            ->with([
                'courseModule.course',
                'media' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->firstOrFail();
    }
}
