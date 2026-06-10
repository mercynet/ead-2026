<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Learning\Models\Category;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;

class GetCategoryAction
{
    /**
     * Categorias visíveis ao tenant: as de sistema (tenant_id null) e as próprias.
     */
    public function handle(ApiContext $context, int $categoryId): Category
    {
        return Category::query()
            ->where(function (Builder $query) use ($context): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $context->tenant?->id);
            })
            ->findOrFail($categoryId);
    }
}
