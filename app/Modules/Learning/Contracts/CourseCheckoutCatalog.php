<?php

namespace App\Modules\Learning\Contracts;

interface CourseCheckoutCatalog
{
    public function resolve(int $tenantId, int $userId, int $courseId): CourseCheckoutOffering;
}
