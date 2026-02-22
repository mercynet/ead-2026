<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateCategoryAction
{
    public function handle(Category $category, Tenant $tenant, array $attributes): Category
    {
        $parentId = isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null;
        $name = isset($attributes['name']) ? trim((string) $attributes['name']) : $category->name;

        if (isset($attributes['name'])) {
            $normalizedName = (string) Str::of($name)->ascii()->lower()->squish();

            if (! $category->is_system && Category::query()
                ->whereNull('tenant_id')
                ->where('normalized_name', $normalizedName)
                ->whereKeyNot($category->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'This category name is reserved by a global system category.',
                ]);
            }

            $scopedQuery = Category::query()
                ->where('normalized_name', $normalizedName)
                ->whereKeyNot($category->id);

            if ($category->parent_id) {
                $scopedQuery->where('parent_id', $category->parent_id);
            } else {
                $scopedQuery->whereNull('parent_id');
            }

            if ($category->is_system) {
                $scopedQuery->whereNull('tenant_id');
            } else {
                $scopedQuery->where('tenant_id', $tenant->id);
            }

            if ($scopedQuery->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'Category name already exists in this scope.',
                ]);
            }

            $category->name = $name;
            $category->slug = Str::slug($name);
            $category->normalized_name = $normalizedName;
        }

        if ($parentId !== null) {
            if ($parentId > 0) {
                $parentCategory = Category::query()->whereKey($parentId)->first();

                if ($parentCategory === null) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Parent category was not found.',
                    ]);
                }

                if ($category->is_system && ! $parentCategory->is_system) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'System category parent must be a system category.',
                    ]);
                }

                if (
                    ! $category->is_system
                    && $parentCategory->tenant_id !== null
                    && (int) $parentCategory->tenant_id !== (int) $tenant->id
                ) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Parent category belongs to a different tenant.',
                    ]);
                }

                $category->parent_id = $parentCategory->id;
            } else {
                $category->parent_id = null;
            }
        }

        $category->save();

        return $category->fresh();
    }
}
