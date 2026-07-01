<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;

class UpdateLessonAction
{
    public function handle(Lesson $lesson, array $attributes): Lesson
    {
        $lesson->fill([
            'title' => $attributes['title'],
        ]);

        $lesson->save();

        return $lesson->refresh();
    }
}
