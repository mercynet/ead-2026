<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Lesson\ReorderLessonRequest as BaseReorderLessonRequest;

class ReorderLessonRequest extends BaseReorderLessonRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
        ];
    }
}
