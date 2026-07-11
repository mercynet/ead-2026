<?php

namespace App\Modules\Learning\Http\Controllers\Lesson;

use App\Modules\Learning\Actions\Lesson\GetLessonAction;
use App\Modules\Learning\Actions\Lesson\StoreLessonRatingAction;
use App\Modules\Learning\Http\Requests\Lesson\StoreLessonRatingRequest;
use App\Modules\Learning\Http\Resources\Lesson\LessonRatingResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonRatingController extends Controller
{
    public function __construct(
        private readonly GetLessonAction $getLessonAction,
        private readonly StoreLessonRatingAction $storeLessonRatingAction,
    ) {}

    public function store(int $lessonId, StoreLessonRatingRequest $request, ApiContext $context): JsonResponse
    {
        $lesson = $this->getLessonAction->handle($context, $lessonId);

        Gate::forUser($context->requiredUser())->authorize('learning.lessons.view', [$context->requiredTenant()]);

        $rating = $this->storeLessonRatingAction->handle($context, $lesson, $request->validated());

        return LessonRatingResource::make($rating)
            ->response()
            ->setStatusCode($rating->wasRecentlyCreated ? 201 : 200);
    }
}
