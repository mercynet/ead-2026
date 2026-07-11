<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;

class DeleteLessonMediaAction
{
    public function handle(LessonMedia $lessonMedia): void
    {
        $lessonMedia->delete();
    }
}
