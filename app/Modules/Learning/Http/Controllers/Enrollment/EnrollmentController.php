<?php

namespace App\Modules\Learning\Http\Controllers\Enrollment;

use App\Modules\Learning\Actions\Enrollment\DeleteEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\GetEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\ListEnrollmentsAction;
use App\Modules\Learning\Actions\Enrollment\ShowEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\StoreEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\UpdateEnrollmentAction;
use App\Modules\Learning\Http\Requests\Enrollment\ListEnrollmentRequest;
use App\Modules\Learning\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Modules\Learning\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Modules\Learning\Http\Resources\Enrollment\EnrollmentResource;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Matrículas
 *
 * Gerenciamento de matrículas em cursos
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly DeleteEnrollmentAction $deleteEnrollmentAction,
        private readonly ListEnrollmentsAction $listEnrollmentsAction,
        private readonly GetEnrollmentAction $getEnrollmentAction,
        private readonly ShowEnrollmentAction $showEnrollmentAction,
        private readonly UpdateEnrollmentAction $updateEnrollmentAction,
        private readonly StoreEnrollmentAction $storeEnrollmentAction,
    ) {}

    public function index(ListEnrollmentRequest $request, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.list', [$context->requiredTenant()]);

        return EnrollmentResource::collection($this->listEnrollmentsAction->handle($request, $context));
    }

    /**
     * Criar Matrícula
     *
     * Cria uma matrícula ativa para o usuário informado (ou o usuário autenticado,
     * quando `user_id` for omitido).
     *
     * @response 201 scenario="Matrícula criada com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "status": "active",
     *     "is_active": true,
     *     "progress_percentage": 0,
     *     "course": {"id": 1, "title": "Curso", "slug": "curso"}
     *   }
     * }
     */
    public function store(StoreEnrollmentRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.create', [$context->requiredTenant()]);

        $enrollment = $this->storeEnrollmentAction->handle($context, $request->validated());

        return EnrollmentResource::make($enrollment)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ver Matrícula
     *
     * Retorna os dados da matrícula do usuário em um curso.
     *
     * @urlParam courseId int required ID do curso
     */
    public function show(int $courseId, ApiContext $context): JsonResource|JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.view', [$context->requiredTenant()]);

        $enrollment = $this->getEnrollmentAction->handle($context, $courseId);

        if ($enrollment === null) {
            return new JsonResponse(['data' => null]);
        }

        return EnrollmentResource::make($enrollment);
    }

    public function showById(int $id, ApiContext $context): JsonResource|JsonResponse
    {
        $enrollment = $this->showEnrollmentAction->handle($context, $id);

        if ($context->requiredUser()->isStudent() && (int) $enrollment->user_id !== (int) $context->requiredUser()->id) {
            throw AccessDeniedException::make('enrollment', $enrollment->id);
        }

        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.view', [$context->requiredTenant(), $enrollment]);

        return EnrollmentResource::make($enrollment);
    }

    public function update(UpdateEnrollmentRequest $request, int $id, ApiContext $context): JsonResource
    {
        $enrollment = $this->showEnrollmentAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.update', [$context->requiredTenant(), $enrollment]);

        return EnrollmentResource::make(
            $this->updateEnrollmentAction->handle($context, $enrollment, $request->validated())
        );
    }

    public function destroy(int $id, ApiContext $context): JsonResponse
    {
        $enrollment = $this->showEnrollmentAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.delete', [$context->requiredTenant(), $enrollment]);

        $this->deleteEnrollmentAction->handle($enrollment);

        return new JsonResponse([
            'data' => [
                'message' => 'Enrollment cancelled successfully.',
            ],
        ]);
    }
}
