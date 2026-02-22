<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCategoriesAction;
use App\Actions\Learning\Catalog\StoreCategoryAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\StoreCategoryRequest;
use App\Http\Resources\Learning\Catalog\CatalogCategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Categorias
 *
 * Gerenciamento de categorias de cursos
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
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
}
