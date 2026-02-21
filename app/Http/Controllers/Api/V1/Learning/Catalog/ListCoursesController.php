<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\ListCatalogCoursesRequest;
use App\Http\Resources\Learning\Catalog\CourseCatalogResource;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ListCoursesController extends Controller
{
    public function __invoke(ListCatalogCoursesRequest $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->user('sanctum');

        if ($tenant === null) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'tenant_not_resolved',
                        'message' => 'Tenant context is required.',
                    ],
                ],
            ], 422);
        }

        if (
            $authenticatedUser !== null
            && ! $authenticatedUser->isDeveloper()
            && ! $authenticatedUser->belongsToTenant($tenant)
        ) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'User does not belong to tenant.',
                    ],
                ],
            ], 403);
        }

        if (
            $authenticatedUser !== null
            && Gate::forUser($authenticatedUser)->denies('learning.catalog.courses.list', [$tenant])
        ) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'Not authorized to list catalog courses.',
                    ],
                ],
            ], 403);
        }

        $coursesQuery = Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->with(['categories:id,name,slug']);

        $categorySlug = $request->query('category');
        if (is_string($categorySlug) && $categorySlug !== '') {
            $coursesQuery->whereHas('categories', function ($query) use ($categorySlug): void {
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

        $paginator = $coursesQuery
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => CourseCatalogResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
