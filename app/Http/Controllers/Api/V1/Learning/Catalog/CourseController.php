<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCoursesAction;
use App\Actions\Learning\Catalog\ShowCourseAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\ListCatalogCoursesRequest;
use App\Http\Resources\Learning\Catalog\CourseCatalogResource;
use App\Http\Resources\Learning\Catalog\CourseDetailResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function __construct(
        private readonly ListCoursesAction $listCoursesAction,
        private readonly ShowCourseAction $showCourseAction,
    ) {}

    public function index(ListCatalogCoursesRequest $request, ApiContext $context): Response
    {
        if ($context->hasUser()) {
            Gate::forUser($context->user)->authorize('learning.catalog.courses.list', [$context->tenant]);
        }

        $paginator = $this->listCoursesAction->handle($request, $context);

        return response(CourseCatalogResource::collection($paginator)->response()->getData(true));
    }

    public function show(string $slug, ListCatalogCoursesRequest $request, ApiContext $context): Response
    {
        if ($context->hasUser()) {
            Gate::forUser($context->user)->authorize('learning.catalog.courses.show', [$context->tenant]);
        }

        $course = $this->showCourseAction->handle($context->tenant, $slug);

        return response([
            'data' => CourseDetailResource::make($course)->resolve(),
        ]);
    }
}
