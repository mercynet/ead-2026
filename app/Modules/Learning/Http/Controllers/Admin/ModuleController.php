<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Module\DeleteModuleAction;
use App\Modules\Learning\Actions\Module\GetModuleAction;
use App\Modules\Learning\Actions\Module\ListAdminModulesAction;
use App\Modules\Learning\Actions\Module\ReorderModuleAction;
use App\Modules\Learning\Actions\Module\StoreModuleAction;
use App\Modules\Learning\Actions\Module\UpdateModuleAction;
use App\Modules\Learning\Http\Requests\Admin\ReorderModuleRequest;
use App\Modules\Learning\Http\Requests\Admin\StoreModuleRequest;
use App\Modules\Learning\Http\Requests\Admin\UpdateModuleRequest;
use App\Modules\Learning\Http\Resources\Module\ModuleResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Módulos
 *
 * Gestão administrativa de módulos dentro do tenant.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly GetCourseAction $getCourseAction,
        private readonly GetModuleAction $getModuleAction,
        private readonly ListAdminModulesAction $listAdminModulesAction,
        private readonly StoreModuleAction $storeModuleAction,
        private readonly UpdateModuleAction $updateModuleAction,
        private readonly ReorderModuleAction $reorderModuleAction,
        private readonly DeleteModuleAction $deleteModuleAction,
    ) {}

    public function index(ApiContext $context, int $courseId): AnonymousResourceCollection
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.list-check', [$context->requiredTenant(), $course]);

        return ModuleResource::collection($this->listAdminModulesAction->handle($context, $course->id));
    }

    public function show(ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.view-check', [$context->requiredTenant(), $module]);

        return ModuleResource::make($module);
    }

    public function store(StoreModuleRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.modules.create-check', [$context->requiredTenant(), (int) $request->validated('course_id')]);

        return ModuleResource::make($this->storeModuleAction->handle($context, $request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateModuleRequest $request, ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.update-check', [$context->requiredTenant(), $module]);

        return ModuleResource::make($this->updateModuleAction->handle($module, $request->validated()));
    }

    public function reorder(ReorderModuleRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.modules.reorder-check', [$context->requiredTenant(), (int) $request->validated('course_id')]);

        return ModuleResource::collection($this->reorderModuleAction->handle($context, $request->validated()))
            ->response();
    }

    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.delete-check', [$context->requiredTenant(), $module]);

        $this->deleteModuleAction->handle($module);

        return new JsonResponse([
            'data' => [
                'message' => 'Module deleted successfully.',
            ],
        ]);
    }
}
