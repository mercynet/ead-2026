<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

class ListCategoriesAction
{
    public function handle(?Tenant $tenant, User $authenticatedUser): CursorPaginator
    {
        $query = Category::query()
            ->orderByDesc('is_system')
            ->orderBy('id');

        if ($authenticatedUser->isDeveloper()) {
            return $query->cursorPaginate(15);
        }

        if ($tenant === null) {
            return $query->whereRaw('1 = 0')->cursorPaginate(15);
        }

        return $query
            ->where(function ($scopedQuery) use ($tenant): void {
                $scopedQuery->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenant->id);
            })
            ->cursorPaginate(15);
    }
}
