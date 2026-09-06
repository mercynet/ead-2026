<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Course\DeleteCourseMaterialAction;
use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Course\GetCourseMaterialAction;
use App\Modules\Learning\Actions\Course\ListCourseMaterialsAction;
use App\Modules\Learning\Actions\Course\StoreCourseMaterialAction;
use App\Modules\Learning\Actions\Course\UpdateCourseMaterialAction;
use App\Modules\Learning\Http\Requests\Admin\StoreCourseMaterialRequest;
use App\Modules\Learning\Http\Requests\Admin\UpdateCourseMaterialRequest;
use App\Modules\Learning\Http\Resources\Course\CourseMaterialResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Materiais
 *
 * Gestão administrativa dos metadados de materiais do curso.
 */
class CourseMaterialController extends Controller
{
    public function __construct(
        private readonly GetCourseAction $getCourseAction,
        private readonly GetCourseMaterialAction $getCourseMaterialAction,
        private readonly ListCourseMaterialsAction $listCourseMaterialsAction,
        private readonly StoreCourseMaterialAction $storeCourseMaterialAction,
        private readonly UpdateCourseMaterialAction $updateCourseMaterialAction,
        private readonly DeleteCourseMaterialAction $deleteCourseMaterialAction,
    ) {}

    public function index(ApiContext $context, int $courseId): AnonymousResourceCollection
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::collection($this->listCourseMaterialsAction->handle($context, $course->id));
    }

    public function show(ApiContext $context, int $courseId, int $materialId): CourseMaterialResource
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getCourseMaterialAction->handle($context, $course->id, $materialId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make($material);
    }

    public function store(int $courseId, StoreCourseMaterialRequest $request, ApiContext $context): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make(
            $this->storeCourseMaterialAction->handle($context, $course, $request->validated())
        )
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $courseId, int $materialId, UpdateCourseMaterialRequest $request, ApiContext $context): CourseMaterialResource
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getCourseMaterialAction->handle($context, $course->id, $materialId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make($this->updateCourseMaterialAction->handle($material, $request->validated()));
    }

    public function destroy(ApiContext $context, int $courseId, int $materialId): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getCourseMaterialAction->handle($context, $course->id, $materialId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        $this->deleteCourseMaterialAction->handle($material);

        return new JsonResponse([
            'data' => [
                'message' => 'Course material deleted successfully.',
            ],
        ]);
    }
}
