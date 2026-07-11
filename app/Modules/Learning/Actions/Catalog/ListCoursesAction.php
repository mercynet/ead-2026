<?php

namespace App\Modules\Learning\Actions\Catalog;

use App\Modules\Learning\Http\Requests\Catalog\ListCatalogCoursesRequest;
use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

class ListCoursesAction
{
    public function handle(ListCatalogCoursesRequest $request, ApiContext $context): CursorPaginator
    {
        $coursesQuery = Course::query()
            ->where('status', 'published')
            ->with(['categories:id,name,slug', 'ratingStats']);

        if ($context->tenant !== null) {
            $coursesQuery->where('courses.tenant_id', $context->tenant->id);
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

        $sort = $request->validated('sort');
        $minRatings = $request->validated('min_ratings');

        if ($sort === 'top_rated') {
            $this->applyTopRatedSorting($coursesQuery, $context, $minRatings === null ? null : (int) $minRatings);
        }

        if ($context->hasUser() && ! $context->user->isDeveloper()) {
            $coursesQuery->whereDoesntHave('enrollments', function (Builder $query) use ($context): void {
                $query->where('user_id', $context->user->id);
            });
        }

        if ($sort !== 'top_rated') {
            $coursesQuery->orderBy('id');
        }

        return $coursesQuery->cursorPaginate(15);
    }

    private function applyTopRatedSorting(Builder $coursesQuery, ApiContext $context, ?int $minRatings): void
    {
        $coursesQuery->leftJoin('rating_stats', function ($join) use ($context): void {
            $join->on('rating_stats.rateable_id', '=', 'courses.id')
                ->where('rating_stats.rateable_type', '=', Course::class);

            if ($context->tenant !== null) {
                $join->where('rating_stats.tenant_id', '=', $context->tenant->id);
            } else {
                $join->whereColumn('rating_stats.tenant_id', 'courses.tenant_id');
            }
        })
            ->select('courses.*')
            ->selectRaw('COALESCE(rating_stats.average_stars, 0) as top_rated_average_stars')
            ->selectRaw('COALESCE(rating_stats.total_ratings, 0) as top_rated_total_ratings')
            ->orderByDesc('top_rated_average_stars')
            ->orderByDesc('top_rated_total_ratings')
            ->orderBy('courses.id');

        if ($minRatings !== null) {
            $coursesQuery->where('rating_stats.total_ratings', '>=', $minRatings);
        }
    }
}
