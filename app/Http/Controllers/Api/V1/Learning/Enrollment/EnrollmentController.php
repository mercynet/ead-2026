<?php

namespace App\Http\Controllers\Api\V1\Learning\Enrollment;

use App\Actions\Learning\Enrollment\GetEnrollmentAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\Enrollment\EnrollmentResource;
use Illuminate\Http\JsonResponse;
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
        private readonly GetEnrollmentAction $getEnrollmentAction,
    ) {}

    /**
     * Ver Matrícula
     *
     * Retorna os dados da matrícula do usuário em um curso.
     *
     * @urlParam courseId int required ID do curso
     */
    public function show(int $courseId, ApiContext $context): JsonResource|JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollment.view', [$context->requiredTenant()]);

        $enrollment = $this->getEnrollmentAction->handle($context, $courseId);

        if ($enrollment === null) {
            return new JsonResponse(['data' => null]);
        }

        return EnrollmentResource::make($enrollment);
    }
}
