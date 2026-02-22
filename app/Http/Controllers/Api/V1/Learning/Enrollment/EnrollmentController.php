<?php

namespace App\Http\Controllers\Api\V1\Learning\Enrollment;

use App\Actions\Learning\Enrollment\GetEnrollmentAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\Enrollment\EnrollmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly GetEnrollmentAction $getEnrollmentAction,
    ) {}

    public function show(int $courseId, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollment.view', [$context->requiredTenant()]);

        $enrollment = $this->getEnrollmentAction->handle($context, $courseId);

        if ($enrollment === null) {
            return new JsonResponse(['data' => null]);
        }

        return EnrollmentResource::make($enrollment)->toResponse(request());
    }
}
