<?php

namespace App\Actions\Learning\Catalog;

use App\Http\Context\ApiContext;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListCoursesAction
{
    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $coursesQuery = Course::query()
            ->where('status', 'published')
            ->with(['categories:id,name,slug'])
            ->orderBy('id');

        if ($context->tenant !== null) {
            $coursesQuery->where('tenant_id', $context->tenant->id);
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

        if ($context->hasUser() && ! $context->user->isDeveloper()) {
            $coursesQuery->whereDoesntHave('enrollments', function (Builder $query) use ($context): void {
                $query->where('user_id', $context->user->id);
            });
        }

        return $coursesQuery->cursorPaginate(15);
    }
}
