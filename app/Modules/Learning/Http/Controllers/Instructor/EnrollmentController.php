<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Enrollment\GetInstructorEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\ListInstructorEnrollmentsAction;
use App\Modules\Learning\Actions\Enrollment\StoreInstructorFreeEnrollmentAction;
use App\Modules\Learning\Http\Requests\Instructor\ListInstructorEnrollmentRequest;
use App\Modules\Learning\Http\Requests\Instructor\StoreInstructorEnrollmentRequest;
use App\Modules\Learning\Http\Resources\Instructor\EnrollmentResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** @group Instructor · Roster e progresso */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly GetInstructorEnrollmentAction $getEnrollmentAction,
        private readonly ListInstructorEnrollmentsAction $listEnrollmentsAction,
        private readonly StoreInstructorFreeEnrollmentAction $storeEnrollmentAction,
    ) {}

    /** Listar alunos dos meus cursos. */
    public function index(ListInstructorEnrollmentRequest $request, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.list', [$context->requiredTenant()]);

        return EnrollmentResource::collection($this->listEnrollmentsAction->handle($request, $context));
    }

    /** Ver matrícula e progresso de um aluno em um curso próprio. */
    public function show(ApiContext $context, int $id): EnrollmentResource
    {
        $enrollment = $this->getEnrollmentAction->handle($context, $id);
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.view', [$context->requiredTenant(), $enrollment]);

        return EnrollmentResource::make($enrollment);
    }

    /** Ver o progresso pedagógico mínimo da matrícula. */
    public function progress(ApiContext $context, int $id): EnrollmentResource
    {
        $enrollment = $this->getEnrollmentAction->handle($context, $id);
        Gate::forUser($context->requiredUser())->authorize('learning.progress.view', [$context->requiredTenant(), $enrollment]);

        return EnrollmentResource::make($enrollment);
    }

    /** Criar matrícula manual gratuita, sem operação financeira pelo Instructor. */
    public function store(StoreInstructorEnrollmentRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.create', [$context->requiredTenant()]);

        $enrollment = $this->storeEnrollmentAction->handle(
            $context,
            (int) $request->validated('course_id'),
            (int) $request->validated('user_id'),
        );

        return EnrollmentResource::make($enrollment)
            ->response()
            ->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }
}
