<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Learning\Models\Category;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListCategoriesAction
{
    public function handle(ApiContext $context): CursorPaginator
    {
        $query = Category::query()
            ->orderByDesc('is_system')
            ->orderBy('id');

        if ($context->user->isDeveloper()) {
            return $query->cursorPaginate(15);
        }

        if ($context->tenant === null) {
            return $query->whereRaw('1 = 0')->cursorPaginate(15);
        }

        return $query
            ->where(function ($scopedQuery) use ($context): void {
                $scopedQuery->whereNull('tenant_id')
                    ->orWhere('tenant_id', $context->tenant->id);
            })
            ->cursorPaginate(15);
    }
}
