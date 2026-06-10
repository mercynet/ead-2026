<?php

namespace App\Modules\Learning\Http\Controllers\Enrollment;

use App\Modules\Learning\Actions\Enrollment\GetEnrollmentAction;
use App\Modules\Learning\Http\Resources\Enrollment\EnrollmentResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
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
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.view', [$context->requiredTenant()]);

        $enrollment = $this->getEnrollmentAction->handle($context, $courseId);

        if ($enrollment === null) {
            return new JsonResponse(['data' => null]);
        }

        return EnrollmentResource::make($enrollment);
    }
}
