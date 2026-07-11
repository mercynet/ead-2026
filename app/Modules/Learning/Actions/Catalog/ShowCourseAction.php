<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use App\Shared\Exceptions\ResourceNotFoundException;

class ShowCourseAction
{
    public function handle(?Tenant $tenant, string $slug): Course
    {
        $query = Course::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'categories:id,name,slug',
                'ratingStats',
                'modules' => fn ($query) => $query->orderBy('sort_order'),
                'modules.lessons' => fn ($query) => $query->orderBy('sort_order'),
            ]);

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        $course = $query->first();

        if ($course === null) {
            throw ResourceNotFoundException::course($slug);
        }

        return $course;
    }
}
