<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Learning\Models\Category;

class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        $category->delete();
    }
}
