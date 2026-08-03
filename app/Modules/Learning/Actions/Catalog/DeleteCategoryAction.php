<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Learning\Models\Category;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class DeleteCategoryAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function handle(Category $category, bool $force = false, bool $confirm = false): void
    {
        $this->database->transaction(function () use ($category, $force, $confirm): void {
            $lockedCategory = Category::query()
                ->whereKey($category->id)
                ->when(
                    $category->tenant_id === null,
                    fn ($query) => $query->whereNull('tenant_id'),
                    fn ($query) => $query->where('tenant_id', $category->tenant_id),
                )
                ->lockForUpdate()
                ->firstOrFail();

            $pivots = $this->database->table('category_course')
                ->where('category_id', $lockedCategory->id)
                ->lockForUpdate()
                ->get();

            if ($pivots->isNotEmpty() && $lockedCategory->is_system) {
                throw ValidationException::withMessages([
                    'category' => 'System categories with attached courses cannot be deleted.',
                ]);
            }

            if ($pivots->isNotEmpty() && (! $force || ! $confirm)) {
                throw ValidationException::withMessages([
                    'force' => 'Attached courses require force and confirm to delete this category.',
                ]);
            }

            if ($pivots->isNotEmpty()) {
                $lockedCategory->courses()->detach();
            }

            $lockedCategory->delete();
        });
    }
}
