<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCategoryAction
{
    public function handle(Tenant $tenant, array $attributes): Category
    {
        $isSystem = (bool) ($attributes['is_system'] ?? false);
        $parentId = (int) ($attributes['parent_id'] ?? 0);
        $name = trim((string) $attributes['name']);
        $normalizedName = (string) Str::of($name)->ascii()->lower()->squish();

        $parentCategory = null;
        if ($parentId > 0) {
            $parentCategory = Category::query()->whereKey($parentId)->first();

            if ($parentCategory === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent category was not found.',
                ]);
            }

            if ($isSystem && ! $parentCategory->is_system) {
                throw ValidationException::withMessages([
                    'parent_id' => 'System category parent must be a system category.',
                ]);
            }

            if (
                ! $isSystem
                && $parentCategory->tenant_id !== null
                && (int) $parentCategory->tenant_id !== (int) $tenant->id
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent category belongs to a different tenant.',
                ]);
            }
        }

        if (! $isSystem && Category::query()
            ->whereNull('tenant_id')
            ->where('normalized_name', $normalizedName)
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This category name is reserved by a global system category.',
            ]);
        }

        $scopedQuery = Category::query()
            ->where('normalized_name', $normalizedName)
            ->where('parent_id', $parentCategory?->id);

        if ($isSystem) {
            $scopedQuery->whereNull('tenant_id');
        } else {
            $scopedQuery->where('tenant_id', $tenant->id);
        }

        if ($scopedQuery->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Category name already exists in this scope.',
            ]);
        }

        return Category::query()->create([
            'tenant_id' => $isSystem ? null : $tenant->id,
            'parent_id' => $parentCategory?->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'normalized_name' => $normalizedName,
            'is_system' => $isSystem,
        ]);
    }
}
