<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;

class UpdateCourseMaterialAction
{
    public function handle(CourseMaterial $courseMaterial, array $attributes): CourseMaterial
    {
        $courseMaterial->fill(['file_path' => $attributes['file_path']]);
        $courseMaterial->save();

        return $courseMaterial->refresh();
    }
}
