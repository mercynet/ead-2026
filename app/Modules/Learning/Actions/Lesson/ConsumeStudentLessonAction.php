<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;

class ConsumeStudentLessonAction
{
    public function __construct(
        private readonly GetStudentLessonAction $getStudentLessonAction,
        private readonly TrackLessonViewAction $trackLessonViewAction,
    ) {}

    public function handle(ApiContext $context, int $lessonId): Lesson
    {
        $lesson = $this->getStudentLessonAction->handle($context, $lessonId);

        if ($lesson->getAttribute('student_preview') !== true) {
            $this->trackLessonViewAction->handle($context, $lesson);
        }

        return $lesson;
    }
}
