<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Lesson\StoreLessonMediaRequest as BaseStoreLessonMediaRequest;

class StoreLessonMediaRequest extends BaseStoreLessonMediaRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
        ];
    }
}
