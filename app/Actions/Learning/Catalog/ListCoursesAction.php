<?php

namespace App\Actions\Learning\Catalog;

use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListCoursesAction
{
    public function handle(Request $request, ?Tenant $tenant, ?User $authenticatedUser): CursorPaginator
    {
        $coursesQuery = Course::query()
            ->where('status', 'published')
            ->with(['categories:id,name,slug'])
            ->orderBy('id');

        if ($tenant !== null) {
            $coursesQuery->where('tenant_id', $tenant->id);
        }

        $categorySlug = $request->query('category');
        if (is_string($categorySlug) && $categorySlug !== '') {
            $coursesQuery->whereHas('categories', function (Builder $query) use ($categorySlug): void {
                $query->where('slug', $categorySlug);
            });
        }

        $isFreeFilter = $request->query('is_free');
        if ($isFreeFilter !== null) {
            if ($request->boolean('is_free')) {
                $coursesQuery->where('price_cents', 0);
            } else {
                $coursesQuery->where('price_cents', '>', 0);
            }
        }

        $isFeaturedFilter = $request->query('is_featured');
        if ($isFeaturedFilter !== null) {
            $coursesQuery->where('is_featured', $request->boolean('is_featured'));
        }

        if ($authenticatedUser !== null && ! $authenticatedUser->isDeveloper()) {
            $coursesQuery->whereDoesntHave('enrollments', function (Builder $query) use ($authenticatedUser): void {
                $query->where('user_id', $authenticatedUser->id);
            });
        }

        return $coursesQuery->cursorPaginate(15);
    }
}
