<?php

namespace App\Modules\Learning\Http\Controllers\Catalog;

use App\Modules\Learning\Actions\Catalog\DeleteCategoryAction;
use App\Modules\Learning\Actions\Catalog\GetCategoryAction;
use App\Modules\Learning\Actions\Catalog\ListCategoriesAction;
use App\Modules\Learning\Actions\Catalog\StoreCategoryAction;
use App\Modules\Learning\Actions\Catalog\UpdateCategoryAction;
use App\Modules\Learning\Http\Requests\Catalog\DeleteCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\StoreCategoryRequest;
use App\Modules\Learning\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Modules\Learning\Http\Resources\Catalog\CatalogCategoryResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pedagogic
 *
 * @subgroup Categories
 *
 * Course category management endpoints
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
        private readonly GetCategoryAction $getCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction,
    ) {}

    /**
     * Listar Categorias
     *
     * Retorna uma lista de categorias disponíveis.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.categories.list', [$context->tenant]);

        $paginator = $this->listCategoriesAction->handle($context);

        return CatalogCategoryResource::collection($paginator);
    }

    /**
     * Criar Categoria
     *
     * Cria uma nova categoria (custom ou de sistema).
     */
    public function store(StoreCategoryRequest $request, ApiContext $context): CatalogCategoryResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.categories.create-category', [$context->tenant, $request->boolean('is_system')]);

        $category = $this->storeCategoryAction->handle($context->requiredTenant(), $request->validated());

        return CatalogCategoryResource::make($category);
    }

    /**
     * Atualizar Categoria
     *
     * Atualiza uma categoria existente (custom ou de sistema).
     *
     * @response 200 scenario="Categoria atualizada com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "name": "Desenvolvimento Web Atualizado",
     *     "slug": "desenvolvimento-web-atualizado",
     *     "is_system": false,
     *     "tenant_id": 1,
     *     "parent_id": null,
     *     "created_at": "2026-02-22T10:00:00Z",
     *     "updated_at": "2026-02-22T10:30:00Z"
     *   }
     * }
     * @response 403 scenario="Sem permissão"
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

        $category = $this->updateCategoryAction->handle($category, $context->requiredTenant(), $request->validated());

        return CatalogCategoryResource::make($category);
    }

    /**
     * Deletar Categoria
     *
     * Remove uma categoria (apenas categorias do tenant, não de sistema).
     *
     * @response 200 scenario="Categoria deletada com sucesso"
     * {
     *   "message": "Category deleted successfully."
     * }
     * @response 403 scenario="Sem permissão"
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
    public function destroy(DeleteCategoryRequest $request, ApiContext $context, int $id): array
    {
        $category = $this->getCategoryAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.categories.tenant.delete-check', [$context->tenant, $category]);

        $this->deleteCategoryAction->handle(
            $category,
            (bool) ($request->validated('force') ?? false),
            (bool) ($request->validated('confirm') ?? false),
        );

        return ['message' => 'Category deleted successfully.'];
    }
}
