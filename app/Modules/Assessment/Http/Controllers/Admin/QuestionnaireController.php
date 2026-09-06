<?php

namespace App\Modules\Assessment\Http\Controllers\Admin;

use App\Modules\Assessment\Actions\Questionnaire\DeleteQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\ListQuestionnairesAction;
use App\Modules\Assessment\Actions\Questionnaire\ShowQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\StoreAdminQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\UpdateQuestionnaireAction;
use App\Modules\Assessment\Http\Requests\StoreQuestionnaireRequest;
use App\Modules\Assessment\Http\Requests\UpdateQuestionnaireRequest;
use App\Modules\Assessment\Http\Resources\QuestionnaireResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Assessment
 *
 * Assessment básico tenant-wide. Criação administrativa não atribui ownership pedagógico.
 */
class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly ListQuestionnairesAction $listQuestionnairesAction,
        private readonly ShowQuestionnaireAction $showQuestionnaireAction,
        private readonly StoreAdminQuestionnaireAction $storeQuestionnaireAction,
        private readonly UpdateQuestionnaireAction $updateQuestionnaireAction,
        private readonly DeleteQuestionnaireAction $deleteQuestionnaireAction,
    ) {}

    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.list', [$context->requiredTenant()]);

        return QuestionnaireResource::collection(
            $this->listQuestionnairesAction->handle(request(), $context),
        );
    }

    public function store(StoreQuestionnaireRequest $request, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.create', [$context->requiredTenant()]);

        return QuestionnaireResource::make($this->storeQuestionnaireAction->handle($request, $context));
    }

    public function show(int $id, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.view', [$context->requiredTenant()]);

        return QuestionnaireResource::make($this->showQuestionnaireAction->handle($id, $context));
    }

    public function update(UpdateQuestionnaireRequest $request, int $id, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.update', [$context->requiredTenant()]);

        return QuestionnaireResource::make($this->updateQuestionnaireAction->handle($request, $id, $context));
    }

    public function destroy(int $id, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.delete', [$context->requiredTenant()]);
        $this->deleteQuestionnaireAction->handle($id, $context);

        return new JsonResponse(['data' => null]);
    }
}
