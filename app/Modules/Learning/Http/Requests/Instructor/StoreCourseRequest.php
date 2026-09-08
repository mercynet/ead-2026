<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Http\Requests\Course\StoreCourseRequest as BaseStoreCourseRequest;

class StoreCourseRequest extends BaseStoreCourseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'owner' => ['prohibited'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'is_published' => ['prohibited'],
        ];
    }
}
