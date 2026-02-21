<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCategoriesAction;
use App\Actions\Learning\Catalog\StoreCategoryAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\StoreCategoryRequest;
use App\Http\Resources\Learning\Catalog\CatalogCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ListCategoriesAction $listCategoriesAction,
        private readonly StoreCategoryAction $storeCategoryAction,
    ) {}

    public function index(ApiContext $context): JsonResponse
    {
        Gate::forUser($context->user)->authorize('learning.categories.list', [$context->tenant]);

        $paginator = $this->listCategoriesAction->handle($context);

        return CatalogCategoryResource::collection($paginator)->toResponse(request());
    }

    public function store(StoreCategoryRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->user)->authorize('learning.categories.create', [$context->tenant, $request->boolean('is_system')]);

        try {
            $category = $this->storeCategoryAction->handle($context->requiredTenant(), $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return CatalogCategoryResource::make($category)
            ->toResponse(request())
            ->setStatusCode(201);
    }
}
