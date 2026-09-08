<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Http\Requests\Lesson\UpdateLessonRequest as BaseUpdateLessonRequest;

class UpdateLessonRequest extends BaseUpdateLessonRequest
{
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Novo título da aula própria.',
                'example' => 'Aula atualizada — Introdução',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'course_module_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'published_at' => ['prohibited'],
            'owner' => ['prohibited'],
        ];
    }
}
