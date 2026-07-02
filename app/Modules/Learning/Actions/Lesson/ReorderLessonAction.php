<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class ReorderLessonAction
{
    /**
     * @return array<int, Lesson>
     */
    public function handle(ApiContext $context, array $attributes): array
    {
        $tenant = $context->requiredTenant();
        $moduleId = (int) $attributes['course_module_id'];
        $lessonIds = array_map('intval', $attributes['lesson_ids']);

        $module = CourseModule::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($moduleId)
            ->first();

        if ($module === null) {
            throw ValidationException::withMessages([
                'course_module_id' => 'Module must belong to the current tenant and be active.',
            ]);
        }

        $lessons = Lesson::query()
            ->where('tenant_id', $tenant->id)
            ->where('course_module_id', $module->id)
            ->whereNull('deleted_at')
            ->whereIn('id', $lessonIds)
            ->get()
            ->keyBy('id');

        $orderedLessons = [];

        foreach ($lessonIds as $index => $lessonId) {
            $lesson = $lessons->get($lessonId);

            if ($lesson === null) {
                throw ValidationException::withMessages([
                    'lesson_ids' => 'Lesson list must contain exactly all lessons from the module in the desired order.',
                ]);
            }

            $lesson->fill(['sort_order' => $index + 1]);
            $lesson->save();
            $orderedLessons[] = $lesson->refresh();
        }

        return $orderedLessons;
    }
}
