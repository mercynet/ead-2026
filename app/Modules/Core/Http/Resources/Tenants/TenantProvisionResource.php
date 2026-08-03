<?php

namespace App\Modules\Core\Http\Resources\Tenants;

use App\Modules\Core\Data\Tenants\ProvisionTenantResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProvisionTenantResult */
class TenantProvisionResource extends JsonResource
{
    /**
     * @return array<string, array<string, int|string|null>>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant' => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'domain' => $this->tenant->domain,
                'database' => $this->tenant->database,
                'description' => $this->tenant->description,
                'status' => $this->tenant->is_active ? 'active' : 'suspended',
            ],
            'admin' => [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'user_type' => $this->admin->user_type->value,
            ],
        ];
    }
}
