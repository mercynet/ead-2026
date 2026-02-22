<?php

namespace App\Http\Controllers\Api\V1\Learning\Catalog;

use App\Actions\Learning\Catalog\ListCoursesAction;
use App\Actions\Learning\Catalog\ShowCourseAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Catalog\ListCatalogCoursesRequest;
use App\Http\Resources\Learning\Catalog\CourseCatalogResource;
use App\Http\Resources\Learning\Catalog\CourseDetailResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Catálogo de Cursos
 *
 * Endpoints públicos para visualização do catálogo de cursos
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly ListCoursesAction $listCoursesAction,
        private readonly ShowCourseAction $showCourseAction,
    ) {}

    /**
     * Listar Cursos
     *
     * Retorna uma lista de cursos disponíveis no catálogo.
     */
    public function index(ListCatalogCoursesRequest $request, ApiContext $context): AnonymousResourceCollection
    {
        if ($context->hasUser()) {
            Gate::forUser($context->user)->authorize('learning.catalog.courses.list', [$context->tenant]);
        }

        $paginator = $this->listCoursesAction->handle($request, $context);

        return CourseCatalogResource::collection($paginator);
    }

    /**
     * Mostrar Curso
     *
     * Retorna os detalhes de um curso específico.
     *
     * @urlParam slug string required O slug do curso
     */
    public function show(string $slug, ListCatalogCoursesRequest $request, ApiContext $context): CourseDetailResource
    {
        if ($context->hasUser()) {
            Gate::forUser($context->user)->authorize('learning.catalog.courses.show', [$context->tenant]);
        }

        $course = $this->showCourseAction->handle($context->tenant, $slug);

        return CourseDetailResource::make($course);
    }
}
