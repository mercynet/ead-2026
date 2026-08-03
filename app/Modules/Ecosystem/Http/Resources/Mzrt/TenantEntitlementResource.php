<?php

namespace App\Modules\Ecosystem\Http\Resources\Mzrt;

use App\Modules\Ecosystem\Models\PluginActivation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PluginActivation */
class TenantEntitlementResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'capability' => $this->plugin->capability_key,
            'status' => $this->status,
        ];
    }
}
