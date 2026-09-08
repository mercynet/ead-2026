<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Lesson\DeleteLessonAction;
use App\Modules\Learning\Actions\Lesson\GetInstructorLessonAction;
use App\Modules\Learning\Actions\Lesson\ListInstructorLessonsAction;
use App\Modules\Learning\Actions\Lesson\ReorderLessonAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonAction;
use App\Modules\Learning\Actions\Module\GetInstructorModuleAction;
use App\Modules\Learning\Http\Requests\Instructor\ReorderLessonRequest;
use App\Modules\Learning\Http\Requests\Instructor\StoreLessonRequest;
use App\Modules\Learning\Http\Requests\Instructor\UpdateLessonRequest;
use App\Modules\Learning\Http\Resources\Instructor\LessonResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Aulas
 *
 * Gestão de aulas pertencentes aos módulos e cursos próprios do Instructor.
 * Lifecycle de publicação permanece fora desta superfície.
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly GetInstructorModuleAction $getModuleAction,
        private readonly GetInstructorLessonAction $getLessonAction,
        private readonly ListInstructorLessonsAction $listLessonsAction,
        private readonly StoreLessonAction $storeLessonAction,
        private readonly UpdateLessonAction $updateLessonAction,
        private readonly ReorderLessonAction $reorderLessonAction,
        private readonly DeleteLessonAction $deleteLessonAction,
    ) {}

    /**
     * Listar aulas do meu módulo.
     *
     * @urlParam moduleId int required ID do módulo próprio
     */
    public function index(ApiContext $context, int $moduleId): AnonymousResourceCollection
    {
        $module = $this->getModuleAction->handle($context, $moduleId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.list-check', [$context->requiredTenant(), $module]);

        return LessonResource::collection($this->listLessonsAction->handle($context, $module->id));
    }

    /**
     * Criar aula no meu módulo.
     */
    public function store(StoreLessonRequest $request, ApiContext $context): JsonResponse
    {
        $moduleId = (int) $request->validated('course_module_id');

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.create-check', [$context->requiredTenant(), $moduleId]);

        return LessonResource::make($this->storeLessonAction->handle($context, $request->validated()))
            ->response()->setStatusCode(201);
    }

    /**
     * Ver minha aula para authoring.
     */
    public function show(ApiContext $context, int $id): LessonResource
    {
        $lesson = $this->getLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.instructor.lessons.view-check', [$context->requiredTenant(), $lesson]);

        return LessonResource::make($lesson);
    }

    /**
     * Atualizar metadados da minha aula sem alterar lifecycle ou parent.
     */
    public function update(UpdateLessonRequest $request, ApiContext $context, int $id): LessonResource
    {
        $lesson = $this->getLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.update-check', [$context->requiredTenant(), $lesson]);

        return LessonResource::make($this->updateLessonAction->handle($lesson, $request->validated()));
    }

    /**
     * Reordenar todas as aulas do meu módulo.
     */
    public function reorder(ReorderLessonRequest $request, ApiContext $context): JsonResponse
    {
        $moduleId = (int) $request->validated('course_module_id');

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.reorder-check', [$context->requiredTenant(), $moduleId]);

        return LessonResource::collection($this->reorderLessonAction->handle($context, $request->validated()))
            ->response();
    }

    /**
     * Remover minha aula.
     */
    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.delete-check', [$context->requiredTenant(), $lesson]);

        $this->deleteLessonAction->handle($lesson);

        return new JsonResponse(['data' => ['message' => 'Lesson deleted successfully.']]);
    }
}
