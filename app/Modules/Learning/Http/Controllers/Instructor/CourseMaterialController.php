<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Course\DeleteCourseMaterialAction;
use App\Modules\Learning\Actions\Course\GenerateCourseMaterialDownloadUrlAction;
use App\Modules\Learning\Actions\Course\GetCourseMaterialAction;
use App\Modules\Learning\Actions\Course\GetInstructorCourseAction;
use App\Modules\Learning\Actions\Course\ListCourseMaterialsAction;
use App\Modules\Learning\Actions\Course\StoreCourseMaterialAction;
use App\Modules\Learning\Actions\Course\StoreMaterialDownloadAction;
use App\Modules\Learning\Actions\Course\UpdateCourseMaterialAction;
use App\Modules\Learning\Http\Requests\Course\StoreMaterialDownloadRequest;
use App\Modules\Learning\Http\Requests\Instructor\StoreCourseMaterialRequest;
use App\Modules\Learning\Http\Requests\Instructor\UpdateCourseMaterialRequest;
use App\Modules\Learning\Http\Resources\Course\MaterialDownloadResource;
use App\Modules\Learning\Http\Resources\Instructor\CourseMaterialResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** @group Instructor · Materiais */
class CourseMaterialController extends Controller
{
    public function __construct(
        private readonly GetInstructorCourseAction $getCourseAction,
        private readonly GetCourseMaterialAction $getMaterialAction,
        private readonly ListCourseMaterialsAction $listMaterialsAction,
        private readonly StoreCourseMaterialAction $storeMaterialAction,
        private readonly UpdateCourseMaterialAction $updateMaterialAction,
        private readonly DeleteCourseMaterialAction $deleteMaterialAction,
        private readonly GenerateCourseMaterialDownloadUrlAction $downloadUrlAction,
        private readonly StoreMaterialDownloadAction $storeDownloadAction,
    ) {}

    /** Listar materiais do meu curso. */
    public function index(ApiContext $context, int $courseId): AnonymousResourceCollection
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::collection($this->listMaterialsAction->handle($context, $course->id));
    }

    /** Ver material do meu curso. */
    public function show(ApiContext $context, int $courseId, int $materialId): CourseMaterialResource
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getMaterialAction->handle($context, $course->id, $materialId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make($material);
    }

    /** Criar material no meu curso. */
    public function store(StoreCourseMaterialRequest $request, ApiContext $context, int $courseId): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make($this->storeMaterialAction->handle($context, $course, $request->validated(), $context->requiredUser()->id))
            ->response()->setStatusCode(201);
    }

    /** Atualizar material do meu curso. */
    public function update(UpdateCourseMaterialRequest $request, ApiContext $context, int $courseId, int $materialId): CourseMaterialResource
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getMaterialAction->handle($context, $course->id, $materialId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        return CourseMaterialResource::make($this->updateMaterialAction->handle($material, $request->validated()));
    }

    /** Remover material do meu curso. */
    public function destroy(ApiContext $context, int $courseId, int $materialId): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getMaterialAction->handle($context, $course->id, $materialId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);
        $this->deleteMaterialAction->handle($material);

        return new JsonResponse(['data' => ['message' => 'Course material deleted successfully.']]);
    }

    /** Gerar URL temporária e registrar download do material. */
    public function download(StoreMaterialDownloadRequest $request, ApiContext $context, int $courseId, int $materialId): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $material = $this->getMaterialAction->handle($context, $course->id, $materialId);
        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->requiredTenant(), $course]);
        $url = $this->downloadUrlAction->handle($material);
        $download = $this->storeDownloadAction->handle($context, $course, $material, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return (new MaterialDownloadResource($download, $url['url'], $url['expires_at']))->response()->setStatusCode(201);
    }
}
