<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;

class GetModuleAction
{
    public function handle(ApiContext $context, int $moduleId): CourseModule
    {
        return CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->findOrFail($moduleId);
    }
}
