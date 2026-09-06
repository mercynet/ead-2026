<?php

namespace App\Modules\Learning\Services;

use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Lesson;

class AssessmentCatalogResolver implements AssessmentCatalog
{
    public function parentBelongsToTenant(string $type, int $id, int $tenantId): bool
    {
        $modelClass = match ($type) {
            'lesson' => Lesson::class,
            'course' => Course::class,
            default => null,
        };

        return $modelClass !== null && $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->exists();
    }

    public function categoryIdsAvailableForTenant(array $categoryIds, int $tenantId): array
    {
        return Category::query()
            ->whereIn('id', $categoryIds)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
