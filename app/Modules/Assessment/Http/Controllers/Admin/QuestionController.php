<?php

namespace App\Modules\Assessment\Http\Controllers\Admin;

use App\Modules\Assessment\Actions\Question\ListQuestionsAction;
use App\Modules\Assessment\Actions\Question\ShowQuestionAction;
use App\Modules\Assessment\Actions\Question\StoreAdminQuestionAction;
use App\Modules\Assessment\Actions\Question\UpdateQuestionAction;
use App\Modules\Assessment\Http\Requests\StoreQuestionRequest;
use App\Modules\Assessment\Http\Requests\UpdateQuestionRequest;
use App\Modules\Assessment\Http\Resources\QuestionResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Assessment
 *
 * Banco de questões administrado tenant-wide, sem converter Admin em Instructor.
 */
class QuestionController extends Controller
{
    public function __construct(
        private readonly ListQuestionsAction $listQuestionsAction,
        private readonly ShowQuestionAction $showQuestionAction,
        private readonly StoreAdminQuestionAction $storeQuestionAction,
        private readonly UpdateQuestionAction $updateQuestionAction,
    ) {}

    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.list', [$context->requiredTenant()]);

        return QuestionResource::collection($this->listQuestionsAction->handle(request(), $context));
    }

    public function store(StoreQuestionRequest $request, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.create', [$context->requiredTenant()]);

        return QuestionResource::make($this->storeQuestionAction->handle($request, $context));
    }

    public function show(int $id, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.view', [$context->requiredTenant()]);

        return QuestionResource::make($this->showQuestionAction->handle($id, $context));
    }

    public function update(UpdateQuestionRequest $request, int $id, ApiContext $context): QuestionResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.questions.update', [$context->requiredTenant()]);

        return QuestionResource::make($this->updateQuestionAction->handle($request, $id, $context));
    }
}
