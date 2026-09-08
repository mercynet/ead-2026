<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;

class GetInstructorModuleAction
{
    public function handle(ApiContext $context, int $moduleId): CourseModule
    {
        return CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($moduleId)
            ->whereHas('course', fn ($query) => $query->where('instructor_id', $context->requiredUser()->id))
            ->with(['course:id,title,instructor_id', 'lessons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->findOrFail($moduleId);
    }
}
