<?php

namespace App\Events\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LessonCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Lesson $lesson,
        public readonly User $user,
        public readonly Course $course,
    ) {}
}
