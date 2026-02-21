<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCoursesAction;
use App\Actions\Learning\Catalog\ShowCourseAction;
use App\Http\Controllers\Concerns\InteractsWithApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\ListCatalogCoursesRequest;
use App\Http\Resources\Learning\Catalog\CourseCatalogResource;
use App\Http\Resources\Learning\Catalog\CourseDetailResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    use InteractsWithApiContext;

    public function __construct(
        private readonly ListCoursesAction $listCoursesAction,
        private readonly ShowCourseAction $showCourseAction,
    ) {}

    public function index(ListCatalogCoursesRequest $request): Response
    {
        $tenant = $this->currentTenant();
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser !== null) {
            Gate::forUser($authenticatedUser)->authorize('learning.catalog.courses.list', [$tenant]);
        }

        $paginator = $this->listCoursesAction->handle($request, $tenant, $authenticatedUser);

        return response(CourseCatalogResource::collection($paginator)->response()->getData(true));
    }

    public function show(string $slug, ListCatalogCoursesRequest $request): Response
    {
        $tenant = $this->currentTenant();
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser !== null) {
            Gate::forUser($authenticatedUser)->authorize('learning.catalog.courses.show', [$tenant]);
        }

        $course = $this->showCourseAction->handle($tenant, $slug);

        if ($course === null) {
            return response([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => 'Course not found.',
                    ],
                ],
            ], 404);
        }

        return response([
            'data' => CourseDetailResource::make($course)->resolve(),
        ]);
    }
}
