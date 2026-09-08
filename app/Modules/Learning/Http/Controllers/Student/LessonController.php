<?php

namespace App\Modules\Learning\Http\Controllers\Student;

use App\Modules\Learning\Actions\Lesson\ConsumeStudentLessonAction;
use App\Modules\Learning\Actions\Lesson\GetStudentLessonAction;
use App\Modules\Learning\Actions\Lesson\UpdateProgressAction;
use App\Modules\Learning\Http\Requests\Lesson\StoreProgressRequest;
use App\Modules\Learning\Http\Resources\Student\LessonMediaResource;
use App\Modules\Learning\Http\Resources\Student\LessonProgressResource;
use App\Modules\Learning\Http\Resources\Student\LessonResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Student — consumo
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly ConsumeStudentLessonAction $consumeStudentLessonAction,
        private readonly GetStudentLessonAction $getStudentLessonAction,
        private readonly UpdateProgressAction $updateProgressAction,
    ) {}

    /**
     * Consumir aula
     *
     * Retorna o conteúdo pedagógico e somente mídia ativa consumível. Uma aula `is_free`
     * pode ser lida como preview sem matrícula e sem side effect persistente.
     *
     * @urlParam lessonId int required ID da aula
     */
    public function show(int $lessonId, ApiContext $context): LessonResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.lessons.view', [$context->requiredTenant()]);

        $lesson = $this->consumeStudentLessonAction->handle($context, $lessonId);

        return LessonResource::make($lesson);
    }

    /**
     * Mídias consumíveis da aula
     *
     * @urlParam lessonId int required ID da aula
     */
    public function media(int $lessonId, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.lessons.view', [$context->requiredTenant()]);

        $lesson = $this->getStudentLessonAction->handle($context, $lessonId);

        return LessonMediaResource::collection($lesson->media);
    }

    /**
     * Registrar progresso da aula
     *
     * Preview não cria progresso; somente matrícula ativa e não expirada pode gravar heartbeat.
     *
     * @urlParam lessonId int required ID da aula
     */
    public function progress(int $lessonId, StoreProgressRequest $request, ApiContext $context): LessonProgressResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.progress.update', [$context->requiredTenant()]);

        $lesson = $this->getStudentLessonAction->handle($context, $lessonId, false);

        return LessonProgressResource::make($this->updateProgressAction->handle($context, $lesson, $request->validated()));
    }
}
