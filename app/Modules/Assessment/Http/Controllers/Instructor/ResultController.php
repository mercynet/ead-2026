<?php

namespace App\Modules\Assessment\Http\Controllers\Instructor;

use App\Modules\Assessment\Actions\Attempt\ListInstructorResultsAction;
use App\Modules\Assessment\Actions\Attempt\ShowInstructorResultAction;
use App\Modules\Assessment\Http\Resources\Instructor\ResultResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Assessment Results
 *
 * Results dos alunos matriculados nos Courses próprios.
 */
class ResultController extends Controller
{
    public function __construct(
        private readonly ListInstructorResultsAction $listAction,
        private readonly ShowInstructorResultAction $showAction,
    ) {}

    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.attempts.list', [$context->requiredTenant()]);

        return ResultResource::collection($this->listAction->handle(request(), $context));
    }

    public function show(ApiContext $context, int $id): ResultResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.attempts.view', [$context->requiredTenant()]);

        return ResultResource::make($this->showAction->handle($id, $context));
    }
}
