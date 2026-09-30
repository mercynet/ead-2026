<?php

namespace App\Modules\Financial\Actions\Student;

use App\Modules\Financial\Models\Order;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListStudentOrdersAction
{
    public function handle(ApiContext $context): CursorPaginator
    {
        return Order::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->with(['items', 'payments'])
            ->orderByDesc('id')
            ->cursorPaginate(15);
    }
}
