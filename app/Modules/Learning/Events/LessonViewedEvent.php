<?php

namespace App\Modules\Learning\Events;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonView;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LessonViewedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Lesson $lesson,
        public readonly User $user,
        public readonly Course $course,
        public readonly LessonView $view,
    ) {}
}
