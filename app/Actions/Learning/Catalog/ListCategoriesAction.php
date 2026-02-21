<?php

namespace App\Actions\Learning\Catalog;

use App\Http\Context\ApiContext;
use App\Models\Category;
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
