<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Course\StoreCourseMaterialRequest as BaseStoreCourseMaterialRequest;

class StoreCourseMaterialRequest extends BaseStoreCourseMaterialRequest
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
