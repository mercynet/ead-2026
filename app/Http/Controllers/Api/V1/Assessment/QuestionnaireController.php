<?php

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Actions\Assessment\Questionnaire\DeleteQuestionnaireAction;
use App\Actions\Assessment\Questionnaire\ListQuestionnairesAction;
use App\Actions\Assessment\Questionnaire\ShowQuestionnaireAction;
use App\Actions\Assessment\Questionnaire\StoreQuestionnaireAction;
use App\Actions\Assessment\Questionnaire\UpdateQuestionnaireAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreQuestionnaireRequest;
use App\Http\Requests\Assessment\UpdateQuestionnaireRequest;
use App\Http\Resources\Assessment\QuestionnaireResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pedagógico / Questionários
 *
 * Gerenciamento de questionários (quizzes)
 */
class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly ListQuestionnairesAction $listQuestionnairesAction,
        private readonly ShowQuestionnaireAction $showQuestionnaireAction,
        private readonly StoreQuestionnaireAction $storeQuestionnaireAction,
        private readonly UpdateQuestionnaireAction $updateQuestionnaireAction,
        private readonly DeleteQuestionnaireAction $deleteQuestionnaireAction,
    ) {}

    /**
     * Listar Questionários
     *
     * Retorna uma lista de questionários do tenant.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->user)->authorize('assessment.questionnaires.list', [$context->tenant]);

        $paginator = $this->listQuestionnairesAction->handle(request(), $context);

        return QuestionnaireResource::collection($paginator);
    }

    /**
     * Criar Questionário
     *
     * Cria um novo questionário.
     */
    public function store(StoreQuestionnaireRequest $request, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->user)->authorize('assessment.questionnaires.create', [$context->tenant]);

        $questionnaire = $this->storeQuestionnaireAction->handle($request, $context);

        return QuestionnaireResource::make($questionnaire);
    }

    /**
     * Ver Questionário
     *
     * Retorna os detalhes de um questionário.
     */
    public function show(int $id, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->user)->authorize('assessment.questionnaires.view', [$context->tenant]);

        $questionnaire = $this->showQuestionnaireAction->handle($id, $context);

        return QuestionnaireResource::make($questionnaire);
    }

    /**
     * Atualizar Questionário
     *
     * Atualiza um questionário existente.
     */
    public function update(UpdateQuestionnaireRequest $request, int $id, ApiContext $context): QuestionnaireResource
    {
        Gate::forUser($context->user)->authorize('assessment.questionnaires.update', [$context->tenant]);

        $questionnaire = $this->updateQuestionnaireAction->handle($request, $id, $context);

        return QuestionnaireResource::make($questionnaire);
    }

    /**
     * Deletar Questionário
     *
     * Remove um questionário (apenas se não houver tentativas).
     */
    public function destroy(int $id, ApiContext $context): void
    {
        Gate::forUser($context->user)->authorize('assessment.questionnaires.delete', [$context->tenant]);

        $this->deleteQuestionnaireAction->handle($id, $context);
    }
}
