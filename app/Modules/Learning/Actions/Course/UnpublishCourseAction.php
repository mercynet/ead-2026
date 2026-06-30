<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use Illuminate\Validation\ValidationException;

class UnpublishCourseAction
{
    public function handle(Course $course): Course
    {
        if ($course->status === 'archived') {
            throw ValidationException::withMessages([
                'status' => 'Archived courses cannot be unpublished.',
            ]);
        }

        $course->status = 'draft';
        $course->save();

        return $course->fresh();
    }
}
