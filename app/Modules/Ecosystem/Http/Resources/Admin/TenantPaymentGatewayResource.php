<?php

namespace App\Modules\Ecosystem\Http\Resources\Admin;

use App\Modules\Ecosystem\Data\TenantPaymentGatewayData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantPaymentGatewayData */
class TenantPaymentGatewayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'plugin' => $this->plugin,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'available' => $this->available,
            'configured' => $this->configured,
            'configuration' => $this->configuration,
            'configuration_schema' => $this->configurationSchema,
        ];
    }
}
