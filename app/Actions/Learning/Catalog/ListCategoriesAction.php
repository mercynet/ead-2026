<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Pagination\CursorPaginator;

class ListCategoriesAction
{
    public function handle(Tenant $tenant): CursorPaginator
    {
        return Category::query()
            ->where(function ($query) use ($tenant): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenant->id);
            })
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
