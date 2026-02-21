<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\Catalog\CourseDetailResource;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowCourseController extends Controller
{
    public function __invoke(Request $request, string $slug): JsonResponse
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
            && Gate::forUser($authenticatedUser)->denies('learning.catalog.courses.show', [$tenant])
        ) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'Not authorized to view catalog course.',
                    ],
                ],
            ], 403);
        }

        $course = Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'categories:id,name,slug',
                'modules' => fn ($query) => $query->orderBy('sort_order'),
                'modules.lessons' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->first();

        if ($course === null) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => 'Course not found.',
                    ],
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'course' => CourseDetailResource::make($course)->resolve(),
            ],
            'meta' => [],
        ]);
    }
}
