<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Http\Requests\Module\StoreModuleRequest as BaseStoreModuleRequest;

class StoreModuleRequest extends BaseStoreModuleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'owner' => ['prohibited'],
        ];
    }
}
