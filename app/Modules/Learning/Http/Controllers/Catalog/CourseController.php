<?php

namespace App\Modules\Learning\Http\Controllers\Catalog;

use App\Modules\Learning\Actions\Catalog\ListCoursesAction;
use App\Modules\Learning\Actions\Catalog\ShowCourseAction;
use App\Modules\Learning\Http\Requests\Catalog\ListCatalogCoursesRequest;
use App\Modules\Learning\Http\Resources\Catalog\CourseCatalogResource;
use App\Modules\Learning\Http\Resources\Catalog\CourseDetailResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
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
     *
     * @unauthenticated
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
     *
     * @unauthenticated
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
