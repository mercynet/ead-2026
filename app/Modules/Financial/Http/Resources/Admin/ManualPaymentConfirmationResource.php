<?php

namespace App\Modules\Financial\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Financial\Models\Order */
class ManualPaymentConfirmationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
