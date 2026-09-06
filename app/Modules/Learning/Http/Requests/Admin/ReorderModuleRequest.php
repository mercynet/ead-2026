<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Module\ReorderModuleRequest as BaseReorderModuleRequest;

class ReorderModuleRequest extends BaseReorderModuleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
        ];
    }
}
