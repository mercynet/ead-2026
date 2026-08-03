<?php

namespace App\Modules\Core\Http\Resources\Tenants;

use App\Modules\Core\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->is_active ? 'active' : 'suspended',
        ];
    }
}
