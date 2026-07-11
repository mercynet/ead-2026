<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;

class DeleteLessonAction
{
    public function handle(Lesson $lesson): void
    {
        $lesson->delete();
    }
}
