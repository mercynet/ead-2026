<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
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

        $course->status = 'published';

        if (! $course->published_at) {
            $course->published_at = now();
        }

        $course->save();

        return $course->fresh();
    }
}
