<?php

namespace App\Modules\Financial\Http\Resources\Student;

use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'origin_type' => $this->origin_type,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'items' => $this->items->map(fn ($item): array => [
                'type' => $item->itemable_type,
                'id' => $item->itemable_id,
                'snapshot' => $item->item_snapshot,
                'price_cents' => $item->price_cents,
            ])->values(),
            'payments' => $this->payments->map(function (Payment $payment): array {
                return [
                    'status' => $payment->status,
                    'gateway_slug' => $payment->gateway_slug,
                    'confirmation_mode' => $payment->confirmation_mode,
                    'external_id' => $payment->external_id,
                ];
            })->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
