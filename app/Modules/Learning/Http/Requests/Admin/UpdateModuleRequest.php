<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Module\UpdateModuleRequest as BaseUpdateModuleRequest;

class UpdateModuleRequest extends BaseUpdateModuleRequest
{
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Novo título do módulo.',
                'example' => 'Módulo atualizado — Fundamentos',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'sort_order' => ['prohibited'],
        ];
    }
}
