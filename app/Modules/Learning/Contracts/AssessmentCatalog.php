<?php

namespace App\Modules\Learning\Contracts;

interface AssessmentCatalog
{
    public function parentBelongsToTenant(string $type, int $id, int $tenantId): bool;

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function categoryIdsAvailableForTenant(array $categoryIds, int $tenantId): array;
}
