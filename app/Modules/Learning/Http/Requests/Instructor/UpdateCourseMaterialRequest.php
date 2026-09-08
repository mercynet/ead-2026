<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Http\Requests\Course\UpdateCourseMaterialRequest as BaseRequest;

class UpdateCourseMaterialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'owner' => ['prohibited'],
        ];
    }
}
