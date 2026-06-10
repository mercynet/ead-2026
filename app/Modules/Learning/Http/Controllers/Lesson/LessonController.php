<?php

namespace App\Modules\Learning\Http\Controllers\Lesson;

use App\Modules\Learning\Actions\Lesson\GetLessonAction;
use App\Modules\Learning\Actions\Lesson\UpdateProgressAction;
use App\Modules\Learning\Http\Requests\Lesson\ShowLessonRequest;
use App\Modules\Learning\Http\Requests\Lesson\StoreProgressRequest;
use App\Modules\Learning\Http\Resources\Lesson\LessonDetailResource;
use App\Modules\Learning\Http\Resources\Lesson\LessonProgressResource;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
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
        private readonly UpdateProgressAction $updateProgressAction,
    ) {}

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

        return LessonDetailResource::make(
            $lesson,
            $canAccess,
            $progress?->time_spent_seconds,
            $progress?->isCompleted() ?? false,
            $progress?->current_time_seconds
        );
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
}
