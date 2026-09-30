<?php

namespace App\Modules\Financial\Http\Resources\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentWebhookAcceptedResource extends JsonResource
{
    /** @return array{accepted: bool} */
    public function toArray(Request $request): array
    {
        return [
            'accepted' => true,
        ];
    }
}
