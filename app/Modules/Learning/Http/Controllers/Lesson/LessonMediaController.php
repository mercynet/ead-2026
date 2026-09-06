<?php

namespace App\Modules\Learning\Http\Controllers\Lesson;

use App\Modules\Learning\Actions\Lesson\DeleteLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\GetLessonAction;
use App\Modules\Learning\Actions\Lesson\GetLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonMediaAction;
use App\Modules\Learning\Http\Requests\Lesson\StoreLessonMediaRequest;
use App\Modules\Learning\Http\Requests\Lesson\UpdateLessonMediaRequest;
use App\Modules\Learning\Http\Resources\Lesson\LessonMediaResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonMediaController extends Controller
{
    public function __construct(
        private readonly GetLessonAction $getLessonAction,
        private readonly GetLessonMediaAction $getLessonMediaAction,
        private readonly StoreLessonMediaAction $storeLessonMediaAction,
        private readonly UpdateLessonMediaAction $updateLessonMediaAction,
        private readonly DeleteLessonMediaAction $deleteLessonMediaAction,
    ) {}

    public function store(int $lessonId, StoreLessonMediaRequest $request, ApiContext $context): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.store-check', [$context->requiredTenant(), $lesson]);

        $lessonMedia = $this->storeLessonMediaAction->handle($context, $lesson, $request->validated());

        return LessonMediaResource::make($lessonMedia)
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $lessonId, int $mediaId, UpdateLessonMediaRequest $request, ApiContext $context): LessonMediaResource
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        $lessonMedia = $this->getLessonMediaAction->handle($context, $lessonId, $mediaId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.update-check', [$context->requiredTenant(), $lesson]);

        $lessonMedia = $this->updateLessonMediaAction->handle($lessonMedia, $request->validated());

        return LessonMediaResource::make($lessonMedia);
    }

    public function destroy(int $lessonId, int $mediaId, ApiContext $context): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);
        $lessonMedia = $this->getLessonMediaAction->handle($context, $lessonId, $mediaId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.delete-check', [$context->requiredTenant(), $lesson]);

        $this->deleteLessonMediaAction->handle($lessonMedia);

        return new JsonResponse([
            'data' => [
                'message' => 'Lesson media deleted successfully.',
            ],
        ]);
    }
}
