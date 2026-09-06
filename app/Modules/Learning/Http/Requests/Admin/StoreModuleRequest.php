<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Module\StoreModuleRequest as BaseStoreModuleRequest;

class StoreModuleRequest extends BaseStoreModuleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'sort_order' => ['prohibited'],
        ];
    }
}
