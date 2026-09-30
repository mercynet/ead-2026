<?php

namespace App\Modules\Financial\Actions\Student;

use App\Modules\Financial\Models\Order;
use App\Shared\Http\ApiContext;

class ShowStudentOrderAction
{
    public function handle(ApiContext $context, int $orderId): Order
    {
        return Order::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->whereKey($orderId)
            ->with(['items', 'payments'])
            ->firstOrFail();
    }
}
