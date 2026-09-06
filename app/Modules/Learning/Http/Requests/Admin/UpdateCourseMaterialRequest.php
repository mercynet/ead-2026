<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Course\UpdateCourseMaterialRequest as BaseUpdateCourseMaterialRequest;

class UpdateCourseMaterialRequest extends BaseUpdateCourseMaterialRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
        ];
    }
}
