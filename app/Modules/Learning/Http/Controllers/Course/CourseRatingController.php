<?php

namespace App\Modules\Learning\Http\Controllers\Course;

use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Course\StoreCourseRatingAction;
use App\Modules\Learning\Http\Requests\Course\StoreCourseRatingRequest;
use App\Modules\Learning\Http\Resources\Course\CourseRatingResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CourseRatingController extends Controller
{
    public function __construct(
        private readonly GetCourseAction $getCourseAction,
        private readonly StoreCourseRatingAction $storeCourseRatingAction,
    ) {}

    public function store(int $courseId, StoreCourseRatingRequest $request, ApiContext $context): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [
            $context->requiredTenant(),
            $course,
        ]);

        $rating = $this->storeCourseRatingAction->handle($context, $course, $request->validated());

        return CourseRatingResource::make($rating)
            ->response()
            ->setStatusCode($rating->wasRecentlyCreated ? 201 : 200);
    }
}
