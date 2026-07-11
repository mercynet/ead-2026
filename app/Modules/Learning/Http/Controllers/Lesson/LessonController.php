<?php

namespace App\Modules\Learning\Http\Controllers\Lesson;

use App\Modules\Learning\Actions\Lesson\DeleteLessonAction;
use App\Modules\Learning\Actions\Lesson\GetLessonAction;
use App\Modules\Learning\Actions\Lesson\ReorderLessonAction;
use App\Modules\Learning\Actions\Lesson\ResolveLessonMediaUrlAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonAction;
use App\Modules\Learning\Actions\Lesson\TrackLessonViewAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonAction;
use App\Modules\Learning\Actions\Lesson\UpdateProgressAction;
use App\Modules\Learning\Http\Requests\Lesson\ReorderLessonRequest;
use App\Modules\Learning\Http\Requests\Lesson\ShowLessonRequest;
use App\Modules\Learning\Http\Requests\Lesson\StoreLessonRequest;
use App\Modules\Learning\Http\Requests\Lesson\StoreProgressRequest;
use App\Modules\Learning\Http\Requests\Lesson\UpdateLessonRequest;
use App\Modules\Learning\Http\Resources\Catalog\LessonResource;
use App\Modules\Learning\Http\Resources\Lesson\LessonDetailResource;
use App\Modules\Learning\Http\Resources\Lesson\LessonProgressResource;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Aulas
 *
 * Gerenciamento de aulas e progresso
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly GetLessonAction $getLessonAction,
        private readonly ResolveLessonMediaUrlAction $resolveLessonMediaUrlAction,
        private readonly StoreLessonAction $storeLessonAction,
        private readonly ReorderLessonAction $reorderLessonAction,
        private readonly DeleteLessonAction $deleteLessonAction,
        private readonly UpdateLessonAction $updateLessonAction,
        private readonly UpdateProgressAction $updateProgressAction,
        private readonly TrackLessonViewAction $trackLessonViewAction,
    ) {}

    public function store(StoreLessonRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.create-check', [$context->requiredTenant(), (int) $request->validated('course_module_id')]);

        $lesson = $this->storeLessonAction->handle($context, $request->validated());

        return LessonResource::make($lesson)
            ->response()
            ->setStatusCode(201);
    }

    public function reorder(ReorderLessonRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.reorder-check', [$context->requiredTenant(), (int) $request->validated('course_module_id')]);

        $lessons = $this->reorderLessonAction->handle($context, $request->validated());

        return LessonResource::collection($lessons)->response();
    }

    /**
     * Mostrar Aula
     *
     * Retorna os detalhes de uma aula específica.
     *
     * @urlParam id int required ID da aula
     */
    public function show(int $id, ShowLessonRequest $request, ApiContext $context): LessonDetailResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        $lesson = $this->getLessonAction->handle($context, $id);
        $canAccess = $this->getLessonAction->canAccess($lesson, $context);

        $progress = $canAccess ? $this->getLessonAction->progressFor($lesson, $context) : null;
        $resolvedMediaUrls = $canAccess
            ? $lesson->media
                ->mapWithKeys(fn ($media): array => [$media->id => $this->resolveLessonMediaUrlAction->handle($media)])
                ->all()
            : [];

        if ($canAccess) {
            $this->trackLessonViewAction->handle($context, $lesson);
        }

        return LessonDetailResource::make(
            $lesson,
            $canAccess,
            $progress?->time_spent_seconds,
            $progress?->isCompleted() ?? false,
            $progress?->current_time_seconds,
            $resolvedMediaUrls,
        );
    }

    public function update(UpdateLessonRequest $request, ApiContext $context, int $id): LessonResource
    {
        $lesson = $this->getLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.update-check', [$context->requiredTenant(), $lesson]);

        $lesson = $this->updateLessonAction->handle($lesson, $request->validated());

        return LessonResource::make($lesson);
    }

    /**
     * Atualizar Progresso
     *
     * Registra o progresso de visualização da aula.
     *
     * @urlParam id int required ID da aula
     */
    public function progress(int $id, StoreProgressRequest $request, ApiContext $context): LessonProgressResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.progress.update', [$context->requiredTenant()]);

        $lesson = $this->getLessonAction->handle($context, $id);

        if (! $this->getLessonAction->canAccess($lesson, $context)) {
            throw AccessDeniedException::lesson($id);
        }

        $progress = $this->updateProgressAction->handle(
            $context,
            $lesson,
            $request->validated()
        );

        return LessonProgressResource::make($progress);
    }

    public function destroy(ApiContext $context, int $id): array
    {
        $lesson = $this->getLessonAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.delete-check', [$context->requiredTenant(), $lesson]);

        $this->deleteLessonAction->handle($lesson);

        return ['message' => 'Lesson deleted successfully.'];
    }
}
