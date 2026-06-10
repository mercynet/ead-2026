<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;

class DeleteCourseAction
{
    public function handle(Course $course): void
    {
        $course->delete();
    }
}
