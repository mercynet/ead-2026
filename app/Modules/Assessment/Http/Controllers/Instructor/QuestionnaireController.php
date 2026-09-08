<?php

namespace App\Modules\Assessment\Http\Controllers\Instructor;

use App\Modules\Assessment\Actions\Questionnaire\AttachInstructorQuestionsAction;
use App\Modules\Assessment\Actions\Questionnaire\DeleteInstructorQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\DetachInstructorQuestionAction;
use App\Modules\Assessment\Actions\Questionnaire\ListInstructorQuestionnaireQuestionsAction;
use App\Modules\Assessment\Actions\Questionnaire\ListInstructorQuestionnairesAction;
use App\Modules\Assessment\Actions\Questionnaire\ReorderInstructorQuestionsAction;
use App\Modules\Assessment\Actions\Questionnaire\ShowInstructorQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\StoreInstructorQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\UpdateInstructorQuestionnaireAction;
use App\Modules\Assessment\Http\Requests\Instructor\AttachQuestionsRequest;
use App\Modules\Assessment\Http\Requests\Instructor\ReorderQuestionsRequest;
use App\Modules\Assessment\Http\Requests\Instructor\StoreQuestionnaireRequest;
use App\Modules\Assessment\Http\Requests\Instructor\UpdateQuestionnaireRequest;
use App\Modules\Assessment\Http\Resources\Instructor\QuestionnaireQuestionResource;
use App\Modules\Assessment\Http\Resources\Instructor\QuestionnaireResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Assessment
 *
 * Assessment básico próprio, limitado a Courses e Lessons pertencentes ao Instructor.
 */
class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly ListInstructorQuestionnairesAction $listAction,
        private readonly ShowInstructorQuestionnaireAction $showAction,
        private readonly StoreInstructorQuestionnaireAction $storeAction,
        private readonly UpdateInstructorQuestionnaireAction $updateAction,
        private readonly DeleteInstructorQuestionnaireAction $deleteAction,
        private readonly ListInstructorQuestionnaireQuestionsAction $listQuestionsAction,
        private readonly AttachInstructorQuestionsAction $attachQuestionsAction,
        private readonly DetachInstructorQuestionAction $detachQuestionAction,
        private readonly ReorderInstructorQuestionsAction $reorderQuestionsAction,
    ) {}

    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.list', [$context->requiredTenant()]);

        return QuestionnaireResource::collection($this->listAction->handle(request(), $context));
    }

    public function store(StoreQuestionnaireRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.create', [$context->requiredTenant()]);

        return QuestionnaireResource::make($this->storeAction->handle($request, $context))->response()->setStatusCode(201);
    }

    public function show(ApiContext $context, int $id): QuestionnaireResource
    {
        $questionnaire = $this->showAction->handle($id, $context, true);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.view-check', [$context->requiredTenant(), $questionnaire]);

        return QuestionnaireResource::make($questionnaire);
    }

    public function update(UpdateQuestionnaireRequest $request, ApiContext $context, int $id): QuestionnaireResource
    {
        $questionnaire = $this->showAction->handle($id, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.update-check', [$context->requiredTenant(), $questionnaire]);

        return QuestionnaireResource::make($this->updateAction->handle($request, $id, $context));
    }

    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $questionnaire = $this->showAction->handle($id, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.delete-check', [$context->requiredTenant(), $questionnaire]);
        $this->deleteAction->handle($id, $context);

        return new JsonResponse(['data' => null]);
    }

    public function questions(ApiContext $context, int $questionnaireId): AnonymousResourceCollection
    {
        $questionnaire = $this->showAction->handle($questionnaireId, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.view-check', [$context->requiredTenant(), $questionnaire]);

        return QuestionnaireQuestionResource::collection($this->listQuestionsAction->handle($questionnaireId, $context));
    }

    public function attach(AttachQuestionsRequest $request, ApiContext $context, int $questionnaireId): QuestionnaireResource
    {
        $questionnaire = $this->showAction->handle($questionnaireId, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.update-check', [$context->requiredTenant(), $questionnaire]);
        $this->attachQuestionsAction->handle($request, $questionnaireId, $context);

        return QuestionnaireResource::make($this->showAction->handle($questionnaireId, $context, true));
    }

    public function detach(ApiContext $context, int $questionnaireId, int $questionId): JsonResponse
    {
        $questionnaire = $this->showAction->handle($questionnaireId, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.update-check', [$context->requiredTenant(), $questionnaire]);
        $this->detachQuestionAction->handle($questionnaireId, $questionId, $context);

        return new JsonResponse(['data' => null]);
    }

    public function reorder(ReorderQuestionsRequest $request, ApiContext $context, int $questionnaireId): QuestionnaireResource
    {
        $questionnaire = $this->showAction->handle($questionnaireId, $context);
        Gate::forUser($context->requiredUser())->authorize('assessment.instructor.questionnaires.update-check', [$context->requiredTenant(), $questionnaire]);
        $this->reorderQuestionsAction->handle($request, $questionnaireId, $context);

        return QuestionnaireResource::make($this->showAction->handle($questionnaireId, $context, true));
    }
}
