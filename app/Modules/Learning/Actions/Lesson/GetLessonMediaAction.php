<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;
use App\Shared\Http\ApiContext;

class GetLessonMediaAction
{
    public function handle(ApiContext $context, int $lessonId, int $mediaId): LessonMedia
    {
        return LessonMedia::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('lesson_id', $lessonId)
            ->whereKey($mediaId)
            ->firstOrFail();
    }
}
