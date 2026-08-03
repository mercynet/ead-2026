<?php

namespace App\Modules\Learning\Http\Controllers\Mzrt;

use App\Modules\Learning\Actions\Catalog\DeleteCategoryAction;
use App\Modules\Learning\Actions\Catalog\GetCategoryAction;
use App\Modules\Learning\Actions\Catalog\StoreCategoryAction;
use App\Modules\Learning\Actions\Catalog\UpdateCategoryAction;
use App\Modules\Learning\Http\Requests\Catalog\DeleteCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\StoreSystemCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Modules\Learning\Http\Resources\Catalog\CatalogCategoryResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Mzrt · Categorias
 *
 * Banco global de categorias de sistema (área Mzrt, só developer). Sem contexto de
 * tenant: a categoria é global, não pertence a nenhum tenant. `GetCategoryAction`
 * sem tenant resolve apenas categorias de sistema, então categoria de tenant nunca
 * é alcançável por esta superfície.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly StoreCategoryAction $storeCategoryAction,
        private readonly GetCategoryAction $getCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction,
    ) {}

    /**
     * Criar Categoria de Sistema (Mzrt)
     *
     * @response 201 scenario="Categoria de sistema criada"
     * {
     *   "data": {"id": 3, "name": "Tecnologia", "is_system": true, "tenant_id": null}
     * }
     * @response 403 scenario="Fora da área ou sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "area_forbidden", "message": "Acesso negado à área mzrt."}]
     * }
     */
    public function store(StoreSystemCategoryRequest $request, ApiContext $context): CatalogCategoryResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.categories.system.manage');

        $category = $this->storeCategoryAction->handle(
            null,
            [...$request->validated(), 'is_system' => true],
        );

        return CatalogCategoryResource::make($category);
    }

    /**
     * Atualizar Categoria de Sistema (Mzrt)
     *
     * @urlParam id int required ID da categoria de sistema
     *
     * @response 404 scenario="Categoria inexistente ou de tenant"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function update(UpdateCategoryRequest $request, ApiContext $context, int $id): CatalogCategoryResource
    {
        $category = $this->getCategoryAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.update-check', [null, $category]);

        return CatalogCategoryResource::make(
            $this->updateCategoryAction->handle($category, null, $request->validated())
        );
    }

    /**
     * Excluir Categoria de Sistema (Mzrt)
     *
     * Soft delete. Categoria de sistema com cursos vinculados **bloqueia** — nem
     * developer apaga, porque o vínculo vive em tenants que a Mzrt não enxerga.
     *
     * @urlParam id int required ID da categoria de sistema
     *
     * @response 422 scenario="Categoria de sistema com cursos vinculados"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "System categories with attached courses cannot be deleted."}]
     * }
     */
    public function destroy(DeleteCategoryRequest $request, ApiContext $context, int $id): array
    {
        $category = $this->getCategoryAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.delete-check', [null, $category]);

        $this->deleteCategoryAction->handle(
            $category,
            (bool) ($request->validated('force') ?? false),
            (bool) ($request->validated('confirm') ?? false),
        );

        return ['message' => 'Category deleted successfully.'];
    }
}
