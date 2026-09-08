<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Lesson\DeleteLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\GetInstructorLessonAction;
use App\Modules\Learning\Actions\Lesson\GetLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\ListLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonMediaAction;
use App\Modules\Learning\Http\Requests\Instructor\StoreLessonMediaRequest;
use App\Modules\Learning\Http\Requests\Instructor\UpdateLessonMediaRequest;
use App\Modules\Learning\Http\Resources\Instructor\LessonMediaResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** @group Instructor · Mídia de aulas */
class LessonMediaController extends Controller
{
    public function __construct(
        private readonly GetInstructorLessonAction $getLessonAction,
        private readonly GetLessonMediaAction $getMediaAction,
        private readonly ListLessonMediaAction $listMediaAction,
        private readonly StoreLessonMediaAction $storeMediaAction,
        private readonly UpdateLessonMediaAction $updateMediaAction,
        private readonly DeleteLessonMediaAction $deleteMediaAction,
    ) {}

    /** Listar mídias da minha aula. */
    public function index(ApiContext $context, int $lessonId): AnonymousResourceCollection
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        return LessonMediaResource::collection($this->listMediaAction->handle($context, $lesson->id));
    }

    /** Ver metadata de uma mídia da minha aula. */
    public function show(ApiContext $context, int $lessonId, int $mediaId): LessonMediaResource
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        $media = $this->getMediaAction->handle($context, $lesson->id, $mediaId);
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        return LessonMediaResource::make($media);
    }

    /** Criar metadata de uma mídia na minha aula. */
    public function store(StoreLessonMediaRequest $request, ApiContext $context, int $lessonId): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.store-check', [$context->requiredTenant(), $lesson]);

        return LessonMediaResource::make($this->storeMediaAction->handle($context, $lesson, $request->validated()))
            ->response()->setStatusCode(201);
    }

    /** Atualizar metadata de uma mídia da minha aula. */
    public function update(UpdateLessonMediaRequest $request, ApiContext $context, int $lessonId, int $mediaId): LessonMediaResource
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        $media = $this->getMediaAction->handle($context, $lesson->id, $mediaId);
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.update-check', [$context->requiredTenant(), $lesson]);

        return LessonMediaResource::make($this->updateMediaAction->handle($media, $request->validated()));
    }

    /** Remover metadata de uma mídia da minha aula. */
    public function destroy(ApiContext $context, int $lessonId, int $mediaId): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        $media = $this->getMediaAction->handle($context, $lesson->id, $mediaId);
        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.delete-check', [$context->requiredTenant(), $lesson]);
        $this->deleteMediaAction->handle($media);

        return new JsonResponse(['data' => ['message' => 'Lesson media deleted successfully.']]);
    }
}
