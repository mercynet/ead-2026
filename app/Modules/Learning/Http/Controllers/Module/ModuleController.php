<?php

namespace App\Modules\Learning\Http\Controllers\Module;

use App\Modules\Learning\Actions\Module\DeleteModuleAction;
use App\Modules\Learning\Actions\Module\GetModuleAction;
use App\Modules\Learning\Actions\Module\ReorderModuleAction;
use App\Modules\Learning\Actions\Module\StoreModuleAction;
use App\Modules\Learning\Actions\Module\UpdateModuleAction;
use App\Modules\Learning\Http\Requests\Module\ReorderModuleRequest;
use App\Modules\Learning\Http\Requests\Module\StoreModuleRequest;
use App\Modules\Learning\Http\Requests\Module\UpdateModuleRequest;
use App\Modules\Learning\Http\Resources\Module\ModuleResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Módulos
 *
 * Gerenciamento administrativo de módulos de curso.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly GetModuleAction $getModuleAction,
        private readonly DeleteModuleAction $deleteModuleAction,
        private readonly StoreModuleAction $storeModuleAction,
        private readonly ReorderModuleAction $reorderModuleAction,
        private readonly UpdateModuleAction $updateModuleAction,
    ) {}

    /**
     * Mostrar Módulo
     *
     * Retorna um módulo do tenant atual.
     *
     * @urlParam id int required ID do módulo
     */
    public function show(ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.view', [$context->requiredTenant(), $module]);

        return ModuleResource::make($module);
    }

    /**
     * Criar Módulo
     *
     * Cria um módulo no curso informado do tenant atual e posiciona no fim da ordem atual.
     *
     * @response 201 scenario="Módulo criado com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "course_id": 10,
     *     "title": "Módulo 1 — Fundamentos",
     *     "sort_order": 1
     *   }
     * }
     */
    public function store(StoreModuleRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.modules.create-check', [$context->requiredTenant()]);

        $module = $this->storeModuleAction->handle($context, $request->validated());

        return ModuleResource::make($module)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualizar Módulo
     *
     * Atualiza apenas o título do módulo.
     *
     * @urlParam id int required ID do módulo
     */
    public function update(UpdateModuleRequest $request, ApiContext $context, int $id): ModuleResource
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.update', [$context->requiredTenant(), $module]);

        $module = $this->updateModuleAction->handle($module, $request->validated());

        return ModuleResource::make($module);
    }

    /**
     * Reordenar Módulos
     *
     * Reordena todos os módulos de um curso do tenant atual.
     */
    public function reorder(ReorderModuleRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.modules.reorder', [$context->requiredTenant()]);

        $modules = $this->reorderModuleAction->handle($context, $request->validated());

        return ModuleResource::collection($modules)->response();
    }

    /**
     * Remover Módulo
     *
     * Remove um módulo do tenant atual.
     *
     * @urlParam id int required ID do módulo
     */
    public function destroy(ApiContext $context, int $id): array
    {
        $module = $this->getModuleAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.modules.delete', [$context->requiredTenant(), $module]);

        $this->deleteModuleAction->handle($module);

        return ['message' => 'Module deleted successfully.'];
    }
}
