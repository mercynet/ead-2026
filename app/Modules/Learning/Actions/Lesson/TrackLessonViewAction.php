<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Events\LessonViewedEvent;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonView;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Facades\Event;

class TrackLessonViewAction
{
    public function handle(ApiContext $context, Lesson $lesson): LessonView
    {
        $view = LessonView::query()->create([
            'tenant_id' => $context->requiredTenant()->id,
            'user_id' => $context->requiredUser()->id,
            'lesson_id' => $lesson->id,
            'viewed_at' => now(),
        ]);

        Event::dispatch(new LessonViewedEvent(
            $lesson,
            $context->requiredUser(),
            $lesson->courseModule->course,
            $view,
        ));

        return $view;
    }
}
