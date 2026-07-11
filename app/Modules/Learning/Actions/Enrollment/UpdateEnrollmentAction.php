<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;

class UpdateEnrollmentAction
{
    public function handle(ApiContext $context, Enrollment $enrollment, array $attributes): Enrollment
    {
        $enrollment->fill(array_intersect_key($attributes, array_flip([
            'status',
            'progress_percentage',
            'access_expires_at',
        ])));
        $enrollment->save();

        return $enrollment->fresh()->load(['course:id,title,slug', 'user:id,name,tenant_id']);
    }
}
