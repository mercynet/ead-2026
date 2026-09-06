<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Catalog\DeleteCategoryAction;
use App\Modules\Learning\Actions\Catalog\GetCategoryAction;
use App\Modules\Learning\Actions\Catalog\StoreCategoryAction;
use App\Modules\Learning\Actions\Catalog\UpdateCategoryAction;
use App\Modules\Learning\Http\Requests\Catalog\DeleteCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\StoreTenantCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Modules\Learning\Http\Resources\Catalog\CatalogCategoryResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Categorias
 *
 * Categorias do próprio tenant (área Admin). Categoria de sistema é contrato da
 * área Mzrt; aqui ela é somente selecionável, nunca editável.
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
     * Criar Categoria do Tenant (Admin)
     *
     * @response 201 scenario="Categoria criada"
     * {
     *   "data": {"id": 12, "name": "Desenvolvimento Web", "type": "custom", "tenant_id": 1}
     * }
     * @response 403 scenario="Fora da área ou sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "area_forbidden", "message": "Acesso negado à área admin."}]
     * }
     * @response 422 scenario="Nome duplicado ou reservado por categoria de sistema"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "Category name already exists in this scope."}]
     * }
     */
    public function store(StoreTenantCategoryRequest $request, ApiContext $context): CatalogCategoryResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.create', [$context->tenant]);

        $category = $this->storeCategoryAction->handle(
            $context->requiredTenant(),
            [...$request->validated(), 'is_system' => false],
        );

        return CatalogCategoryResource::make($category);
    }

    /**
     * Atualizar Categoria do Tenant (Admin)
     *
     * @urlParam id int required ID da categoria
     *
     * @response 403 scenario="Categoria de sistema ou sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "access_denied", "message": "Acesso negado."}]
     * }
     * @response 404 scenario="Categoria não encontrada"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function update(UpdateCategoryRequest $request, ApiContext $context, int $id): CatalogCategoryResource
    {
        $category = $this->getCategoryAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.update-check', [$context->tenant, $category]);

        return CatalogCategoryResource::make(
            $this->updateCategoryAction->handle($category, $context->requiredTenant(), $request->validated())
        );
    }

    /**
     * Excluir Categoria do Tenant (Admin)
     *
     * Soft delete. Categoria com cursos vinculados exige `force` e `confirm`, e o
     * vínculo é desfeito antes da exclusão.
     *
     * @urlParam id int required ID da categoria
     *
     * @response 200 scenario="Categoria excluída"
     * {
     *   "data": {"message": "Category deleted successfully."}
     * }
     * @response 422 scenario="Cursos vinculados sem force/confirm"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "Attached courses require force and confirm to delete this category."}]
     * }
     */
    public function destroy(DeleteCategoryRequest $request, ApiContext $context, int $id): JsonResponse
    {
        $category = $this->getCategoryAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.delete-check', [$context->tenant, $category]);

        $this->deleteCategoryAction->handle(
            $category,
            (bool) ($request->validated('force') ?? false),
            (bool) ($request->validated('confirm') ?? false),
        );

        return new JsonResponse([
            'data' => [
                'message' => 'Category deleted successfully.',
            ],
        ]);
    }
}
