<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Enums\LessonMediaProgressStrategy;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Shared\Http\ApiContext;

class StoreLessonMediaAction
{
    public function handle(ApiContext $context, Lesson $lesson, array $attributes): LessonMedia
    {
        $attributes['tenant_id'] = $context->requiredTenant()->id;
        $attributes['lesson_id'] = $lesson->id;
        $attributes['progress_strategy'] ??= LessonMediaProgressStrategy::EightyPercent;
        $attributes['sort_order'] ??= (int) LessonMedia::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('lesson_id', $lesson->id)
            ->max('sort_order') + 1;
        $attributes['is_active'] ??= true;

        return LessonMedia::query()->create($attributes);
    }
}
