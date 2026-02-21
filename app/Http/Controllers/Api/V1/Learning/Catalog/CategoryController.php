<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCategoriesAction;
use App\Actions\Learning\Catalog\StoreCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\StoreCategoryRequest;
use App\Http\Resources\Learning\Catalog\CatalogCategoryResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

        Gate::forUser($authenticatedUser)->authorize('learning.categories.list', [$tenant]);
        $paginator = $this->listCategoriesAction->handle($tenant);

        return response(CatalogCategoryResource::collection($paginator)->response()->getData(true));
    }

    public function store(StoreCategoryRequest $request): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

        if ($request->boolean('is_system')) {
            Gate::forUser($authenticatedUser)->authorize('learning.categories.system.manage');
        } else {
            Gate::forUser($authenticatedUser)->authorize('learning.categories.tenant.create', [$tenant]);
        }

        try {
            $category = $this->storeCategoryAction->handle($tenant, $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response([
            'data' => [
                'category' => CatalogCategoryResource::make($category)->resolve(),
            ],
            'meta' => [],
        ], 201);
    }

    private function resolveTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
