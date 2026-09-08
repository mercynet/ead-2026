<?php

namespace App\Modules\Assessment\Http\Controllers\Instructor;

use App\Modules\Assessment\Actions\Question\DeleteInstructorQuestionAction;
use App\Modules\Assessment\Actions\Question\ListInstructorQuestionsAction;
use App\Modules\Assessment\Actions\Question\ShowInstructorQuestionAction;
use App\Modules\Assessment\Actions\Question\StoreInstructorQuestionAction;
use App\Modules\Assessment\Actions\Question\UpdateInstructorQuestionAction;
use App\Modules\Assessment\Http\Requests\Instructor\StoreQuestionRequest;
use App\Modules\Assessment\Http\Requests\Instructor\UpdateQuestionRequest;
use App\Modules\Assessment\Http\Resources\Instructor\QuestionResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Assessment
 *
 * Banco de questões próprio do Instructor.
 */
class QuestionController extends Controller
{
    public function __construct(
        private readonly ListInstructorQuestionsAction $listAction,
        private readonly ShowInstructorQuestionAction $showAction,
        private readonly StoreInstructorQuestionAction $storeAction,
        private readonly UpdateInstructorQuestionAction $updateAction,
        private readonly DeleteInstructorQuestionAction $deleteAction,
    ) {}

    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.list', [$context->requiredTenant()]);

        return QuestionResource::collection($this->listAction->handle(request(), $context));
    }

    public function store(StoreQuestionRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.create', [$context->requiredTenant()]);

        return QuestionResource::make($this->storeAction->handle($request, $context))->response()->setStatusCode(201);
    }

    public function show(ApiContext $context, int $id): QuestionResource
    {
        $question = $this->showAction->handle($id, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questions.view-check', [$context->requiredTenant(), $question]);

        return QuestionResource::make($question);
    }

    public function update(UpdateQuestionRequest $request, ApiContext $context, int $id): QuestionResource
    {
        $question = $this->showAction->handle($id, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questions.update-check', [$context->requiredTenant(), $question]);

        return QuestionResource::make($this->updateAction->handle($request, $id, $context));
    }

    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $question = $this->showAction->handle($id, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questions.delete-check', [$context->requiredTenant(), $question]);
        $this->deleteAction->handle($id, $context);

        return new JsonResponse(['data' => null]);
    }
}
