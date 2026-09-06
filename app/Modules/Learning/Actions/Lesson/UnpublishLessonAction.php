<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;

class UnpublishLessonAction
{
    public function handle(Lesson $lesson): Lesson
    {
        $lesson->setAttribute('status', 'draft');
        $lesson->save();

        return $lesson->fresh(['courseModule.course', 'media']);
    }
}
