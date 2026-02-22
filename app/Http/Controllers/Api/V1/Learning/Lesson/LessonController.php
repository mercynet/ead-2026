<?php

namespace App\Http\Controllers\Api\V1\Learning\Lesson;

use App\Actions\Learning\Lesson\GetLessonAction;
use App\Actions\Learning\Lesson\UpdateProgressAction;
use App\Exceptions\AccessDeniedException;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Lesson\ShowLessonRequest;
use App\Http\Requests\Learning\Lesson\StoreProgressRequest;
use App\Http\Resources\Learning\Lesson\LessonDetailResource;
use App\Http\Resources\Learning\Lesson\LessonProgressResource;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function __construct(
        private readonly GetLessonAction $getLessonAction,
        private readonly UpdateProgressAction $updateProgressAction,
    ) {}

    public function show(int $id, ShowLessonRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lesson.view', [$context->requiredTenant()]);

        $lesson = $this->getLessonAction->handle($context, $id);
        $canAccess = $this->getLessonAction->canAccess($lesson, $context);

        $progress = null;
        if ($canAccess) {
            $progress = LessonProgress::query()
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('user_id', $context->requiredUser()->id)
                ->where('lesson_id', $lesson->id)
                ->first();
        }

        return LessonDetailResource::make(
            $lesson,
            $canAccess,
            $progress?->time_spent_seconds,
            $progress?->isCompleted() ?? false,
            $progress?->current_time_seconds
        )->toResponse(request());
    }

    public function progress(int $id, StoreProgressRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.lesson.progress', [$context->requiredTenant()]);

        $lesson = $this->getLessonAction->handle($context, $id);

        if (! $this->getLessonAction->canAccess($lesson, $context)) {
            throw AccessDeniedException::lesson($id);
        }

        $progress = $this->updateProgressAction->handle(
            $context,
            $lesson,
            $request->validated()
        );

        return LessonProgressResource::make($progress)->toResponse(request())->setStatusCode(200);
    }
}
