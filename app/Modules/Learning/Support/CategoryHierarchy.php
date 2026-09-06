<?php

namespace App\Modules\Learning\Support;

use App\Modules\Learning\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CategoryHierarchy
{
    public function sync(Category $category): void
    {
        $path = $this->pathFor($category);
        $category->forceFill([
            'path' => $path,
            'depth' => max(0, substr_count($path, '/') - 1),
        ])->saveQuietly();
    }

    public function move(Category $category, ?int $parentId): void
    {
        if ($parentId === $category->id || $this->isDescendant($category, $parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be moved below itself or one of its descendants.',
            ]);
        }

        $oldPath = $category->path;
        $category->parent_id = $parentId;
        $this->sync($category);

        if ($oldPath === null) {
            return;
        }

        $newPath = (string) $category->path;
        $newDepth = (int) $category->depth;

        $descendants = Category::query()
            ->when(
                $category->tenant_id === null,
                fn (Builder $query): Builder => $query->whereNull('tenant_id'),
                fn (Builder $query): Builder => $query->where('tenant_id', $category->tenant_id),
            )
            ->where('path', 'like', $oldPath.'/%')
            ->get();

        $descendants->each(function (Category $descendant) use ($oldPath, $newPath, $newDepth): void {
            $suffix = substr((string) $descendant->path, strlen($oldPath));
            $descendant->forceFill([
                'path' => $newPath.$suffix,
                'depth' => $newDepth + substr_count($suffix, '/'),
            ])->saveQuietly();
        });
    }

    private function pathFor(Category $category): string
    {
        $ids = [$category->id];
        $parentId = $category->parent_id;
        $visited = [$category->id => true];

        while ($parentId !== null) {
            if (isset($visited[$parentId])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category hierarchy cannot contain a cycle.',
                ]);
            }

            $visited[$parentId] = true;
            $parentQuery = Category::query();

            if ($category->tenant_id === null) {
                $parentQuery->whereNull('tenant_id');
            } else {
                $parentQuery->where('tenant_id', $category->tenant_id);
            }

            $parent = $parentQuery->find($parentId);

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent category was not found.',
                ]);
            }

            array_unshift($ids, $parent->id);
            $parentId = $parent->parent_id;
        }

        return '/'.implode('/', $ids);
    }

    private function isDescendant(Category $category, ?int $candidateId): bool
    {
        if ($candidateId === null || $category->path === null) {
            return false;
        }

        return Category::query()
            ->when(
                $category->tenant_id === null,
                fn (Builder $query): Builder => $query->whereNull('tenant_id'),
                fn (Builder $query): Builder => $query->where('tenant_id', $category->tenant_id),
            )
            ->whereKey($candidateId)
            ->where(function ($query) use ($category): void {
                $query->where('path', $category->path)
                    ->orWhere('path', 'like', $category->path.'/%');
            })
            ->exists();
    }
}
