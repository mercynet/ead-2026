<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Validation\ValidationException;

class PublishCourseAction
{
    public function handle(Course $course): Course
    {
        if ($course->status === 'archived') {
            throw ValidationException::withMessages([
                'status' => 'Archived courses cannot be published.',
            ]);
        }

        if (! (bool) $course->getAttribute('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'Inactive courses cannot be published.',
            ]);
        }

        $hasModule = CourseModule::query()
            ->where('tenant_id', $course->tenant_id)
            ->where('course_id', $course->id)
            ->exists();

        if (! $hasModule) {
            throw ValidationException::withMessages([
                'modules' => 'Courses must contain at least one module before publishing.',
            ]);
        }

        $hasPublishedActiveLesson = Lesson::query()
            ->where('tenant_id', $course->tenant_id)
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('courseModule', function ($query) use ($course): void {
                $query
                    ->where('tenant_id', $course->tenant_id)
                    ->where('course_id', $course->id);
            })
            ->exists();

        if (! $hasPublishedActiveLesson) {
            throw ValidationException::withMessages([
                'lessons' => 'Courses must contain at least one published and active lesson before publishing.',
            ]);
        }

        $course->status = 'published';

        if (! $course->published_at) {
            $course->published_at = now();
        }

        $course->save();

        return $course->fresh();
    }
}
