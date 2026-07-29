<?php

namespace App\Modules\Financial\Http\Resources\Student;

use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Payment|null $payment */
        $payment = $this->payments->first();

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
            'payment' => [
                'status' => $payment?->status,
                'gateway_slug' => $payment?->gateway_slug,
                'confirmation_mode' => $payment?->confirmation_mode,
                'external_id' => $payment?->external_id,
                'redirect_url' => is_array($payment?->metadata) ? ($payment->metadata['redirect_url'] ?? null) : null,
                'client_secret' => is_array($payment?->metadata) ? ($payment->metadata['client_secret'] ?? null) : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
