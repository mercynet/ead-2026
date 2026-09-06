<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Enrollment\DeleteEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\ListEnrollmentsAction;
use App\Modules\Learning\Actions\Enrollment\ShowEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\StoreEnrollmentAction;
use App\Modules\Learning\Actions\Enrollment\UpdateEnrollmentAction;
use App\Modules\Learning\Http\Requests\Enrollment\ListEnrollmentRequest;
use App\Modules\Learning\Http\Requests\Enrollment\StoreAdminEnrollmentRequest;
use App\Modules\Learning\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Modules\Learning\Http\Resources\Enrollment\EnrollmentResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly DeleteEnrollmentAction $deleteEnrollmentAction,
        private readonly ListEnrollmentsAction $listEnrollmentsAction,
        private readonly ShowEnrollmentAction $showEnrollmentAction,
        private readonly UpdateEnrollmentAction $updateEnrollmentAction,
        private readonly StoreEnrollmentAction $storeEnrollmentAction,
    ) {}

    public function index(ListEnrollmentRequest $request, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.list', [$context->requiredTenant()]);

        return EnrollmentResource::collection($this->listEnrollmentsAction->handle($request, $context));
    }

    public function store(StoreAdminEnrollmentRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.enrollments.create', [$context->requiredTenant()]);

        return EnrollmentResource::make($this->storeEnrollmentAction->handle($context, $request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id, ApiContext $context): JsonResource
    {
        $enrollment = $this->showEnrollmentAction->handle($context, $id);

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
