<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Actions\Attempt\FinishAttemptAction;
use App\Modules\Assessment\Actions\Attempt\ShowAttemptAction;
use App\Modules\Assessment\Actions\Attempt\StartAttemptAction;
use App\Modules\Assessment\Actions\Attempt\SubmitAnswerAction;
use App\Modules\Assessment\Http\Requests\SubmitAnswerRequest;
use App\Modules\Assessment\Http\Resources\AttemptAnswerResource;
use App\Modules\Assessment\Http\Resources\AttemptResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pedagógico / Tentativas
 *
 * Gerenciamento de tentativas de questionário
 */
class AttemptController extends Controller
{
    public function __construct(
        private readonly StartAttemptAction $startAttemptAction,
        private readonly ShowAttemptAction $showAttemptAction,
        private readonly SubmitAnswerAction $submitAnswerAction,
        private readonly FinishAttemptAction $finishAttemptAction,
    ) {}

    /**
     * Iniciar Tentativa
     *
     * Inicia uma nova tentativa para um questionário.
     */
    public function store(int $questionnaireId, ApiContext $context): AttemptResource
    {
        Gate::forUser($context->user)->authorize('assessment.attempts.create', [$context->tenant]);

        $attempt = $this->startAttemptAction->handle($questionnaireId, $context);

        return AttemptResource::make($attempt);
    }

    /**
     * Ver Tentativa
     */
    public function show(int $id, ApiContext $context): AttemptResource
    {
        Gate::forUser($context->user)->authorize('assessment.attempts.view', [$context->tenant]);

        $attempt = $this->showAttemptAction->handle($id, $context);

        return AttemptResource::make($attempt);
    }

    /**
     * Enviar Resposta
     */
    public function update(SubmitAnswerRequest $request, int $attemptId, ApiContext $context): AttemptAnswerResource
    {
        Gate::forUser($context->user)->authorize('assessment.attempts.answer', [$context->tenant]);

        $answer = $this->submitAnswerAction->handle($request, $attemptId, $context);

        return AttemptAnswerResource::make($answer);
    }

    /**
     * Finalizar Tentativa
     */
    public function finish(int $attemptId, ApiContext $context): AttemptResource
    {
        Gate::forUser($context->user)->authorize('assessment.attempts.finish', [$context->tenant]);

        $attempt = $this->finishAttemptAction->handle($attemptId, $context);

        return AttemptResource::make($attempt);
    }
}
