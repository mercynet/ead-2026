<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Actions\Question\ListQuestionsAction;
use App\Modules\Assessment\Actions\Question\ShowQuestionAction;
use App\Modules\Assessment\Actions\Question\StoreQuestionAction;
use App\Modules\Assessment\Actions\Question\UpdateQuestionAction;
use App\Modules\Assessment\Http\Requests\StoreQuestionRequest;
use App\Modules\Assessment\Http\Requests\UpdateQuestionRequest;
use App\Modules\Assessment\Http\Resources\QuestionResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pedagógico / Questões
 *
 * Gerenciamento do banco de questões
 */
class QuestionController extends Controller
{
    public function __construct(
        private readonly ListQuestionsAction $listQuestionsAction,
        private readonly ShowQuestionAction $showQuestionAction,
        private readonly StoreQuestionAction $storeQuestionAction,
        private readonly UpdateQuestionAction $updateQuestionAction,
    ) {}

    /**
     * Listar Questões
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->user)->authorize('assessment.questions.list', [$context->tenant]);

        $paginator = $this->listQuestionsAction->handle(request(), $context);

        return QuestionResource::collection($paginator);
    }

    /**
     * Criar Questão
     */
    public function store(StoreQuestionRequest $request, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->user)->authorize('assessment.questions.create', [$context->tenant]);

        $question = $this->storeQuestionAction->handle($request, $context);

        return QuestionResource::make($question);
    }

    /**
     * Ver Questão
     */
    public function show(int $id, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->user)->authorize('assessment.questions.view', [$context->tenant]);

        $question = $this->showQuestionAction->handle($id, $context);

        return QuestionResource::make($question);
    }

    /**
     * Atualizar Questão
     *
     * Não é possível editar questões que já foram usadas em tentativas.
     */
    public function update(UpdateQuestionRequest $request, int $id, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->user)->authorize('assessment.questions.update', [$context->tenant]);

        $question = $this->updateQuestionAction->handle($request, $id, $context);

        return QuestionResource::make($question);
    }
}
