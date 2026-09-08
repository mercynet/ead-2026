<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;
use App\Shared\Http\ApiContext;

class GetStudentLessonAction
{
    public function __construct(
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
        private readonly ResolveLessonMediaUrlAction $resolveLessonMediaUrlAction,
    ) {}

    public function handle(ApiContext $context, int $lessonId, bool $allowPreview = true): Lesson
    {
        $access = $this->resolveStudentAccessAction->requireLesson($context, $lessonId, $allowPreview);
        /** @var Lesson $lesson */
        $lesson = $access['lesson'];
        $enrollment = $access['enrollment'];

        $lesson->setAttribute('student_preview', $access['preview']);
        $lesson->setRelation('studentEnrollment', $enrollment);
        $lesson->setRelation('studentProgress', $enrollment === null ? null : LessonProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $lesson->courseModule->course->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->first());

        foreach ($lesson->media as $media) {
            /** @var LessonMedia $media */
            $resolved = $this->resolveLessonMediaUrlAction->handle($media);
            $media->setAttribute('student_url', $resolved['url']);
            $media->setAttribute('student_url_expires_at', $resolved['expires_at']);
            $media->setAttribute('student_url_kind', $resolved['kind']);
        }

        return $lesson;
    }
}
