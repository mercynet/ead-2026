<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Http\Requests\Lesson\StoreLessonMediaRequest as BaseRequest;

class StoreLessonMediaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
            'course_module_id' => ['prohibited'],
            'owner' => ['prohibited'],
        ];
    }
}
