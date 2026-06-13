<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Str;

class StoreCourseAction
{
    public function handle(ApiContext $context, array $attributes): Course
    {
        $attributes['tenant_id'] = $context->requiredTenant()->id;
        $attributes['instructor_id'] = $context->requiredUser()->id;
        $attributes['slug'] = Str::slug($attributes['title']);
        $attributes['status'] ??= 'draft';
        $attributes['price_cents'] ??= 0;
        $attributes['is_active'] ??= true;

        if ($attributes['status'] === 'published') {
            $attributes['published_at'] = now();
        }

        return Course::query()->create($attributes);
    }
}
