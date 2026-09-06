<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;

class PublishLessonAction
{
    public function handle(Lesson $lesson): Lesson
    {
        $lesson->setAttribute('status', 'published');

        if ($lesson->getAttribute('published_at') === null) {
            $lesson->setAttribute('published_at', now());
        }
        $lesson->save();

        return $lesson->fresh(['courseModule.course', 'media']);
    }
}
