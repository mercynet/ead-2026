<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;

class StoreModuleAction
{
    public function handle(ApiContext $context, array $attributes): CourseModule
    {
        $courseId = (int) $attributes['course_id'];

        $sortOrder = (int) (CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $courseId)
            ->max('sort_order') ?? 0);

        return CourseModule::query()->create([
            'tenant_id' => $context->requiredTenant()->id,
            'course_id' => $courseId,
            'title' => $attributes['title'],
            'sort_order' => $sortOrder + 1,
        ]);
    }
}
