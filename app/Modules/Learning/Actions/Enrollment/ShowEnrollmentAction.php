<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;

class ShowEnrollmentAction
{
    public function handle(ApiContext $context, int $id): Enrollment
    {
        return Enrollment::query()
            ->whereKey($id)
            ->where('tenant_id', $context->requiredTenant()->id)
            ->with(['course:id,title,slug', 'user:id,name,tenant_id'])
            ->firstOrFail();
    }
}
