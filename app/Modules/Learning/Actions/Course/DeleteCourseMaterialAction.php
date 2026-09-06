<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;

class DeleteCourseMaterialAction
{
    public function handle(CourseMaterial $courseMaterial): void
    {
        $courseMaterial->delete();
    }
}
