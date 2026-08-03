<?php

namespace App\Modules\Learning\Http\Controllers\Catalog;

use App\Modules\Learning\Actions\Catalog\ListCategoriesAction;
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
 * Leitura do catálogo de categorias (sistema + tenant). A escrita é área-first:
 * categoria de tenant em `v1/admin/categories`, de sistema em `v1/mzrt/categories`.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
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
}
