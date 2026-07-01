<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Str;

class StoreLessonAction
{
    public function handle(ApiContext $context, array $attributes): Lesson
    {
        $module = CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($attributes['course_module_id'])
            ->firstOrFail();

        $attributes['tenant_id'] = $context->requiredTenant()->id;
        $attributes['slug'] = Str::slug($attributes['title']);
        $attributes['status'] = 'draft';
        $attributes['is_active'] ??= true;
        $attributes['is_free'] ??= false;
        $attributes['sort_order'] = (int) Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_module_id', $module->id)
            ->max('sort_order') + 1;

        return Lesson::query()->create($attributes);
    }
}
