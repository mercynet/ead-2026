<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Lesson\StoreLessonRequest as BaseStoreLessonRequest;

class StoreLessonRequest extends BaseStoreLessonRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
            'sort_order' => ['prohibited'],
        ];
    }
}
