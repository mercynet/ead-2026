<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Lesson\DeleteLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\GetAdminLessonAction;
use App\Modules\Learning\Actions\Lesson\GetLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\ListLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonMediaAction;
use App\Modules\Learning\Actions\Lesson\UpdateLessonMediaAction;
use App\Modules\Learning\Http\Requests\Admin\StoreLessonMediaRequest;
use App\Modules\Learning\Http\Requests\Admin\UpdateLessonMediaRequest;
use App\Modules\Learning\Http\Resources\Lesson\LessonMediaResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Mídia de aulas
 *
 * Gestão administrativa de metadados de mídia sem resolver URL de consumo.
 */
class LessonMediaController extends Controller
{
    public function __construct(
        private readonly GetAdminLessonAction $getAdminLessonAction,
        private readonly GetLessonMediaAction $getLessonMediaAction,
        private readonly ListLessonMediaAction $listLessonMediaAction,
        private readonly StoreLessonMediaAction $storeLessonMediaAction,
        private readonly UpdateLessonMediaAction $updateLessonMediaAction,
        private readonly DeleteLessonMediaAction $deleteLessonMediaAction,
    ) {}

    public function index(ApiContext $context, int $lessonId): AnonymousResourceCollection
    {
        $lesson = $this->getAdminLessonAction->handle($context, $lessonId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        return LessonMediaResource::collection($this->listLessonMediaAction->handle($context, $lesson->id));
    }

    public function show(ApiContext $context, int $lessonId, int $mediaId): LessonMediaResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $lessonId);
        $media = $this->getLessonMediaAction->handle($context, $lesson->id, $mediaId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        return LessonMediaResource::make($media);
    }

    public function store(int $lessonId, StoreLessonMediaRequest $request, ApiContext $context): JsonResponse
    {
        $lesson = $this->getAdminLessonAction->handle($context, $lessonId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.store-check', [$context->requiredTenant(), $lesson]);

        return LessonMediaResource::make(
            $this->storeLessonMediaAction->handle($context, $lesson, $request->validated())
        )
            ->response()
            ->setStatusCode(201);
    }

    public function update(int $lessonId, int $mediaId, UpdateLessonMediaRequest $request, ApiContext $context): LessonMediaResource
    {
        $lesson = $this->getAdminLessonAction->handle($context, $lessonId);
        $media = $this->getLessonMediaAction->handle($context, $lesson->id, $mediaId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.update-check', [$context->requiredTenant(), $lesson]);

        return LessonMediaResource::make($this->updateLessonMediaAction->handle($media, $request->validated()));
    }

    public function destroy(ApiContext $context, int $lessonId, int $mediaId): JsonResponse
    {
        $lesson = $this->getAdminLessonAction->handle($context, $lessonId);
        $media = $this->getLessonMediaAction->handle($context, $lesson->id, $mediaId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.media.delete-check', [$context->requiredTenant(), $lesson]);

        $this->deleteLessonMediaAction->handle($media);

        return new JsonResponse([
            'data' => [
                'message' => 'Lesson media deleted successfully.',
            ],
        ]);
    }
}
