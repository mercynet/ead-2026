<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCategoriesAction;
use App\Actions\Learning\Catalog\StoreCategoryAction;
use App\Http\Controllers\Concerns\InteractsWithApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\StoreCategoryRequest;
use App\Http\Resources\Learning\Catalog\CatalogCategoryResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    use InteractsWithApiContext;

    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
    ) {}

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::forUser($authenticatedUser)->authorize('learning.categories.list', [$tenant]);
        $paginator = $this->listCategoriesAction->handle($tenant, $authenticatedUser);

        return CatalogCategoryResource::collection($paginator);
    }

    public function store(StoreCategoryRequest $request): Response
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::forUser($authenticatedUser)->authorize('learning.categories.create', [$tenant, $request->boolean('is_system')]);

        try {
            /** @var Tenant $resolvedTenant */
            $resolvedTenant = $tenant;
            $category = $this->storeCategoryAction->handle($resolvedTenant, $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response([
            'data' => CatalogCategoryResource::make($category)->resolve(),
        ], 201);
    }
}
