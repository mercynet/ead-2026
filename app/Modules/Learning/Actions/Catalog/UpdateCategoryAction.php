<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Support\CategoryHierarchy;
use App\Modules\Learning\Support\CategoryNameNormalizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateCategoryAction
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
    public function handle(Category $category, ?Tenant $tenant, array $attributes): Category
    {
        if (! $category->is_system && $tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => 'Tenant categories require a tenant context.',
            ]);
        }

        $parentId = isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null;
        $name = isset($attributes['name']) ? trim((string) $attributes['name']) : $category->name;

        return $this->database->transaction(function () use ($category, $tenant, $attributes, $parentId, $name): Category {
            if (isset($attributes['name'])) {
                $normalizedName = $this->normalizer->normalize($name);

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

                if ($category->is_system) {
                    $scopedQuery->whereNull('tenant_id');
                } else {
                    $scopedQuery->where('tenant_id', $tenant?->id);
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

            if (array_key_exists('parent_id', $attributes)) {
                if ($parentId > 0) {
                    $parentCategory = Category::query()->whereKey($parentId)->first();

                    if ($parentCategory === null) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'Parent category was not found.',
                        ]);
                    }

                    if ($category->is_system && (! $parentCategory->is_system || $parentCategory->tenant_id !== null)) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'System category parent must be a system category.',
                        ]);
                    }

                    if (
                        ! $category->is_system
                        && ($parentCategory->is_system || (int) $parentCategory->tenant_id !== (int) $tenant?->id)
                    ) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'Parent category belongs to a different tenant.',
                        ]);
                    }

                    $this->hierarchy->move($category, $parentCategory->id);
                } else {
                    $this->hierarchy->move($category, null);
                }
            }

            $category->save();

            if (! array_key_exists('parent_id', $attributes)) {
                $category->save();
            }

            return $category->fresh();
        });
    }
}
