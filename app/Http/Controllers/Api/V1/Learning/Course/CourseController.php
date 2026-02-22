<?php

namespace App\Http\Controllers\Api\V1\Learning\Course;

use App\Actions\Learning\Course\GetCourseModulesAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\Course\CourseModulesResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function __construct(
        private readonly GetCourseModulesAction $getCourseModulesAction,
    ) {}

    public function modules(int $courseId, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('modules', [Course::class, $context->requiredTenant()]);

        $modules = $this->getCourseModulesAction->handle($context, $courseId);

        return CourseModulesResource::collection($modules)->toResponse(request());
    }
}
