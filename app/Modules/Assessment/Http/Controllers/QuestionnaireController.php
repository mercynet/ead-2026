<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Actions\Questionnaire\DeleteQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\ListQuestionnairesAction;
use App\Modules\Assessment\Actions\Questionnaire\ShowQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\StoreQuestionnaireAction;
use App\Modules\Assessment\Actions\Questionnaire\UpdateQuestionnaireAction;
use App\Modules\Assessment\Http\Requests\StoreQuestionnaireRequest;
use App\Modules\Assessment\Http\Requests\UpdateQuestionnaireRequest;
use App\Modules\Assessment\Http\Resources\QuestionnaireResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
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
