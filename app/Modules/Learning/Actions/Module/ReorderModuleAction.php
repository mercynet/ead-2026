<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class ReorderModuleAction
{
    /**
     * @return array<int, CourseModule>
     */
    public function handle(ApiContext $context, array $attributes): array
    {
        $tenant = $context->requiredTenant();
        $courseId = (int) $attributes['course_id'];
        $moduleIds = array_map('intval', $attributes['module_ids']);

        $course = Course::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($courseId)
            ->whereNull('deleted_at')
            ->first();

        if ($course === null) {
            throw ValidationException::withMessages([
                'course_id' => 'Course must belong to the current tenant and be active.',
            ]);
        }

        $modules = CourseModule::query()
            ->where('tenant_id', $tenant->id)
            ->where('course_id', $course->id)
            ->whereIn('id', $moduleIds)
            ->get()
            ->keyBy('id');

        $orderedModules = [];

        foreach ($moduleIds as $index => $moduleId) {
            $module = $modules->get($moduleId);

            if ($module === null) {
                throw ValidationException::withMessages([
                    'module_ids' => 'Module list must contain exactly all modules from the course in the desired order.',
                ]);
            }

            $module->fill(['sort_order' => $index + 1]);
            $module->save();
            $orderedModules[] = $module->refresh();
        }

        return $orderedModules;
    }
}
