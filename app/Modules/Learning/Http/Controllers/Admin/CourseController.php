<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Http\Resources\Catalog\CourseDetailResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Cursos
 *
 * Superfície administrativa do tenant para cursos (área Admin).
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly GetCourseAction $getCourseAction,
    ) {}

    /**
     * Ver Curso (Admin)
     *
     * Retorna um curso do próprio tenant com módulos e categorias, incluindo drafts.
     * Restrito à área Admin (admin + developer por hierarquia).
     *
     * @urlParam id int required ID do curso
     *
     * @response 200 scenario="Curso encontrado"
     * {
     *   "data": {
     *     "id": 1,
     *     "title": "Curso",
     *     "slug": "curso",
     *     "status": "draft",
     *     "price_cents": 0,
     *     "is_free": true,
     *     "categories": [],
     *     "modules": []
     *   }
     * }
     * @response 403 scenario="Fora da área ou sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "area_forbidden", "message": "Acesso negado à área admin."}]
     * }
     * @response 404 scenario="Curso não encontrado"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function show(ApiContext $context, int $id): CourseDetailResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [$context->tenant, $course]);

        $course->load(['categories', 'modules']);

        return CourseDetailResource::make($course);
    }
}
