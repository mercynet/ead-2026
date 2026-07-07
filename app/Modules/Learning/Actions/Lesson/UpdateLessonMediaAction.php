<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;

class UpdateLessonMediaAction
{
    public function handle(LessonMedia $lessonMedia, array $attributes): LessonMedia
    {
        $lessonMedia->fill($attributes);
        $lessonMedia->save();

        return $lessonMedia->refresh();
    }
}
