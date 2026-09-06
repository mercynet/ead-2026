<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Support\CategoryHierarchy;
use App\Modules\Learning\Support\CategoryNameNormalizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCategoryAction
{
    public function __construct(
        private readonly CategoryNameNormalizer $normalizer,
        private readonly CategoryHierarchy $hierarchy,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * `$tenant` é obrigatório para categoria de tenant e ignorado para categoria de
     * sistema — a área Mzrt escreve sem contexto de tenant.
     */
    public function handle(?Tenant $tenant, array $attributes): Category
    {
        $isSystem = (bool) ($attributes['is_system'] ?? false);

        if (! $isSystem && $tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => 'Tenant categories require a tenant context.',
            ]);
        }
        $parentId = (int) ($attributes['parent_id'] ?? 0);
        $name = trim((string) $attributes['name']);
        $normalizedName = $this->normalizer->normalize($name);

        $parentCategory = null;
        if ($parentId > 0) {
            $parentCategory = Category::query()->whereKey($parentId)->first();

            if ($parentCategory === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent category was not found.',
                ]);
            }

            if ($isSystem && (! $parentCategory->is_system || $parentCategory->tenant_id !== null)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'System category parent must be a system category.',
                ]);
            }

            if (
                ! $isSystem
                && ($parentCategory->is_system || (int) $parentCategory->tenant_id !== (int) $tenant->id)
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
            ->where('normalized_name', $normalizedName);

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

        return $this->database->transaction(function () use ($tenant, $parentCategory, $name, $normalizedName, $isSystem): Category {
            $category = Category::query()->create([
                'tenant_id' => $isSystem ? null : $tenant->id,
                'parent_id' => $parentCategory?->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'normalized_name' => $normalizedName,
                'is_system' => $isSystem,
                'depth' => 0,
            ]);

            $this->hierarchy->sync($category);

            return $category;
        });
    }
}
