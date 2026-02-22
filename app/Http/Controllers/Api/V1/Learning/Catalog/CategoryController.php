<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCategoriesAction;
use App\Actions\Learning\Catalog\StoreCategoryAction;
use App\Actions\Learning\Catalog\UpdateCategoryAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\StoreCategoryRequest;
use App\Http\Requests\Learning\Catalog\UpdateCategoryRequest;
use App\Http\Resources\Learning\Catalog\CatalogCategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
        private readonly UpdateCategoryAction $updateCategoryAction,
    ) {}

    /**
     * Listar Categorias
     *
     * Retorna uma lista de categorias disponíveis.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::authorize('learning.categories.list', [$context->tenant]);

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
        if (! Gate::check('learning.categories.create-category', [$context->tenant, $request->boolean('is_system')], $context->user)) {
            abort(403);
        }

        try {
            $category = $this->storeCategoryAction->handle($context->requiredTenant(), $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return CatalogCategoryResource::make($category);
    }

    /**
     * Atualizar Categoria
     *
     * Atualiza uma categoria existente (custom ou de sistema).
     *
     * @unauthenticated
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
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Categoria não encontrada"
     * {
     *   "message": "No query results for model [App\\Models\\Category]."
     * }
     */
    public function update(UpdateCategoryRequest $request, ApiContext $context, int $id): CatalogCategoryResource
    {
        $category = Category::query()
            ->where(function ($q) use ($context) {
                $q->whereNull('tenant_id')
                    ->orWhere('tenant_id', $context->tenant?->id);
            })
            ->findOrFail($id);

        if (! Gate::check('learning.categories.tenant.update-check', [$context->tenant, $category], $context->user)) {
            abort(403);
        }

        try {
            $category = $this->updateCategoryAction->handle($category, $context->requiredTenant(), $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return CatalogCategoryResource::make($category);
    }

    /**
     * Deletar Categoria
     *
     * Remove uma categoria (apenas categorias do tenant, não de sistema).
     *
     * @unauthenticated
     *
     * @response 200 scenario="Categoria deletada com sucesso"
     * {
     *   "message": "Category deleted successfully."
     * }
     * @response 403 scenario="Sem permissão"
     * {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Categoria não encontrada"
     * {
     *   "message": "No query results for model [App\\Models\\Category]."
     * }
     */
    public function destroy(ApiContext $context, int $id): array
    {
        $category = Category::query()
            ->where(function ($q) use ($context) {
                $q->whereNull('tenant_id')
                    ->orWhere('tenant_id', $context->tenant?->id);
            })
            ->findOrFail($id);

        if (! Gate::check('learning.categories.tenant.delete-check', [$context->tenant, $category], $context->user)) {
            abort(403);
        }

        $category->delete();

        return ['message' => 'Category deleted successfully.'];
    }
}
