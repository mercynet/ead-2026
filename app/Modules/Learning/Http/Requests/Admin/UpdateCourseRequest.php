<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Course\UpdateCourseRequest as BaseUpdateCourseRequest;

class UpdateCourseRequest extends BaseUpdateCourseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
        ];
    }
}
