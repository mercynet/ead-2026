<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Course\GetInstructorCourseAction;
use App\Modules\Learning\Actions\Module\DeleteModuleAction;
use App\Modules\Learning\Actions\Module\GetInstructorModuleAction;
use App\Modules\Learning\Actions\Module\ListInstructorModulesAction;
use App\Modules\Learning\Actions\Module\ReorderModuleAction;
use App\Modules\Learning\Actions\Module\StoreModuleAction;
use App\Modules\Learning\Actions\Module\UpdateModuleAction;
use App\Modules\Learning\Http\Requests\Instructor\ReorderModuleRequest;
use App\Modules\Learning\Http\Requests\Instructor\StoreModuleRequest;
use App\Modules\Learning\Http\Requests\Instructor\UpdateModuleRequest;
use App\Modules\Learning\Http\Resources\Instructor\ModuleResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Módulos
 *
 * Gestão de módulos pertencentes aos cursos próprios do Instructor.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly GetInstructorCourseAction $getCourseAction,
        private readonly GetInstructorModuleAction $getModuleAction,
        private readonly ListInstructorModulesAction $listModulesAction,
        private readonly StoreModuleAction $storeModuleAction,
        private readonly UpdateModuleAction $updateModuleAction,
        private readonly ReorderModuleAction $reorderModuleAction,
        private readonly DeleteModuleAction $deleteModuleAction,
    ) {}

    /**
     * Listar módulos do meu curso.
     *
     * @urlParam courseId int required ID do curso próprio
     */
    public function index(ApiContext $context, int $courseId): AnonymousResourceCollection
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.list-check', [$context->requiredTenant(), $course]);

        return ModuleResource::collection($this->listModulesAction->handle($context, $course->id));
    }

    /**
     * Criar módulo no meu curso.
     */
    public function store(StoreModuleRequest $request, ApiContext $context): JsonResponse
    {
        $courseId = (int) $request->validated('course_id');

        Gate::forUser($context->requiredUser())->authorize('learning.modules.create-check', [$context->requiredTenant(), $courseId]);

        return ModuleResource::make($this->storeModuleAction->handle($context, $request->validated()))
            ->response()->setStatusCode(201);
    }

    /**
     * Ver meu módulo e suas aulas.
     */
    public function show(ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.view-check', [$context->requiredTenant(), $module]);

        return ModuleResource::make($module);
    }

    /**
     * Atualizar meu módulo.
     */
    public function update(UpdateModuleRequest $request, ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.update-check', [$context->requiredTenant(), $module]);

        return ModuleResource::make($this->updateModuleAction->handle($module, $request->validated()));
    }

    /**
     * Reordenar todos os módulos do meu curso.
     */
    public function reorder(ReorderModuleRequest $request, ApiContext $context): JsonResponse
    {
        $courseId = (int) $request->validated('course_id');

        Gate::forUser($context->requiredUser())->authorize('learning.modules.reorder-check', [$context->requiredTenant(), $courseId]);

        return ModuleResource::collection($this->reorderModuleAction->handle($context, $request->validated()))
            ->response();
    }

    /**
     * Remover meu módulo.
     */
    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.delete-check', [$context->requiredTenant(), $module]);

        $this->deleteModuleAction->handle($module);

        return new JsonResponse(['data' => ['message' => 'Module deleted successfully.']]);
    }
}
