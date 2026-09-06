<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Lesson\DeleteLessonAction;
use App\Modules\Learning\Actions\Lesson\GetAdminLessonAction;
use App\Modules\Learning\Actions\Lesson\ListAdminLessonsAction;
use App\Modules\Learning\Actions\Lesson\PublishLessonAction;
use App\Modules\Learning\Actions\Lesson\ReorderLessonAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonAction;
use App\Modules\Learning\Actions\Lesson\UnpublishLessonAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonAction;
use App\Modules\Learning\Actions\Module\GetModuleAction;
use App\Modules\Learning\Http\Requests\Admin\ReorderLessonRequest;
use App\Modules\Learning\Http\Requests\Admin\StoreLessonRequest;
use App\Modules\Learning\Http\Requests\Admin\UpdateLessonRequest;
use App\Modules\Learning\Http\Resources\Admin\LessonResource as AdminLessonResource;
use App\Modules\Learning\Http\Resources\Catalog\LessonResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Aulas
 *
 * Gestão administrativa de aulas sem acionar consumo, progresso ou tracking de aluno.
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly GetModuleAction $getModuleAction,
        private readonly GetAdminLessonAction $getAdminLessonAction,
        private readonly ListAdminLessonsAction $listAdminLessonsAction,
        private readonly StoreLessonAction $storeLessonAction,
        private readonly UpdateLessonAction $updateLessonAction,
        private readonly PublishLessonAction $publishLessonAction,
        private readonly UnpublishLessonAction $unpublishLessonAction,
        private readonly ReorderLessonAction $reorderLessonAction,
        private readonly DeleteLessonAction $deleteLessonAction,
    ) {}

    public function index(ApiContext $context, int $moduleId): AnonymousResourceCollection
    {
        $module = $this->getModuleAction->handle($context, $moduleId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.list-check', [$context->requiredTenant(), $module]);

        return LessonResource::collection($this->listAdminLessonsAction->handle($context, $module->id));
    }

    public function show(ApiContext $context, int $id): AdminLessonResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        return AdminLessonResource::make($lesson);
    }

    public function store(StoreLessonRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.create-check', [$context->requiredTenant(), (int) $request->validated('course_module_id')]);

        return LessonResource::make($this->storeLessonAction->handle($context, $request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateLessonRequest $request, ApiContext $context, int $id): LessonResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.update-check', [$context->requiredTenant(), $lesson]);

        return LessonResource::make($this->updateLessonAction->handle($lesson, $request->validated()));
    }

    /**
     * Publicar Aula (Admin)
     *
     * Publica explicitamente uma aula do tenant sem alterar o curso pai.
     * A permissão de atualização de aula é mantida como teto da transição.
     *
     * @urlParam id int required ID da aula
     */
    public function publish(ApiContext $context, int $id): AdminLessonResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.publish-check', [$context->requiredTenant(), $lesson]);

        return AdminLessonResource::make($this->publishLessonAction->handle($lesson));
    }

    /**
     * Despublicar Aula (Admin)
     *
     * Retorna a aula para `draft` sem apagar o primeiro `published_at`.
     *
     * @urlParam id int required ID da aula
     */
    public function unpublish(ApiContext $context, int $id): AdminLessonResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.publish-check', [$context->requiredTenant(), $lesson]);

        return AdminLessonResource::make($this->unpublishLessonAction->handle($lesson));
    }

    public function reorder(ReorderLessonRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.reorder-check', [$context->requiredTenant(), (int) $request->validated('course_module_id')]);

        return LessonResource::collection($this->reorderLessonAction->handle($context, $request->validated()))
            ->response();
    }

    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $lesson = $this->getAdminLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.delete-check', [$context->requiredTenant(), $lesson]);

        $this->deleteLessonAction->handle($lesson);

        return new JsonResponse([
            'data' => [
                'message' => 'Lesson deleted successfully.',
            ],
        ]);
    }
}
