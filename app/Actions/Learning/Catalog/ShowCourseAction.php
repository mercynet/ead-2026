<?php

namespace App\Actions\Learning\Catalog;

use App\Exceptions\ResourceNotFoundException;
use App\Models\Course;
use App\Models\Tenant;

class ShowCourseAction
{
    public function handle(?Tenant $tenant, string $slug): Course
    {
        $query = Course::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'categories:id,name,slug',
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
