<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Course;
use App\Models\Tenant;

class ShowCourseAction
{
    public function handle(Tenant $tenant, string $slug): ?Course
    {
        return Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'categories:id,name,slug',
                'modules' => fn ($query) => $query->orderBy('sort_order'),
                'modules.lessons' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->first();
    }
}
