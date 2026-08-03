<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Course\PublishCourseAction;
use App\Modules\Learning\Actions\Course\SyncCourseCategoriesAction;
use App\Modules\Learning\Actions\Course\UnpublishCourseAction;
use App\Modules\Learning\Http\Requests\Course\SyncCourseCategoriesRequest;
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
        private readonly PublishCourseAction $publishCourseAction,
        private readonly UnpublishCourseAction $unpublishCourseAction,
        private readonly SyncCourseCategoriesAction $syncCourseCategoriesAction,
    ) {}

    /**
     * Ver Curso (Admin)
     *
     * Retorna um curso do próprio tenant com módulos e categorias, incluindo drafts.
     * Restrito à área Admin.
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

    /**
     * Publicar Curso (Admin)
     *
     * Publica um curso do próprio tenant.
     * Requer a permissão canônica `learning.courses.publish`.
     *
     * @urlParam id int required ID do curso
     *
     * @response 200 scenario="Curso publicado"
     * {
     *   "data": {
     *     "id": 1,
     *     "status": "published",
     *     "published_at": "2026-02-22T10:00:00Z"
     *   }
     * }
     * @response 403 scenario="Sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "access_denied", "message": "Acesso negado."}]
     * }
     * @response 404 scenario="Curso não encontrado"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function publish(ApiContext $context, int $id): CourseDetailResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.publish-check', [$context->tenant, $course]);

        return CourseDetailResource::make($this->publishCourseAction->handle($course));
    }

    /**
     * Despublicar Curso (Admin)
     *
     * Retorna o curso para `draft`.
     * Requer a permissão canônica `learning.courses.publish`.
     *
     * @urlParam id int required ID do curso
     *
     * @response 200 scenario="Curso despublicado"
     * {
     *   "data": {
     *     "id": 1,
     *     "status": "draft"
     *   }
     * }
     * @response 403 scenario="Sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "access_denied", "message": "Acesso negado."}]
     * }
     * @response 404 scenario="Curso não encontrado"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function unpublish(ApiContext $context, int $id): CourseDetailResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.publish-check', [$context->tenant, $course]);

        return CourseDetailResource::make($this->unpublishCourseAction->handle($course));
    }

    /**
     * Sincronizar Categorias do Curso (Admin)
     *
     * Substitui o conjunto completo de categorias do curso. A posição no array vira a
     * ordem de exibição; array vazio remove todos os vínculos. Aceita categoria de
     * sistema ou do próprio tenant. Requer a permissão canônica `learning.courses.update`.
     *
     * @urlParam id int required ID do curso
     *
     * @response 200 scenario="Categorias sincronizadas"
     * {
     *   "data": {
     *     "id": 1,
     *     "title": "Curso",
     *     "categories": [{"id": 12, "name": "Tecnologia"}]
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
     * @response 422 scenario="Categoria inexistente ou de outro tenant"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "Category 99 was not found."}]
     * }
     */
    public function syncCategories(SyncCourseCategoriesRequest $request, ApiContext $context, int $id): CourseDetailResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->tenant, $course]);

        /** @var list<array{id: int, is_featured?: bool|null}> $categories */
        $categories = $request->validated('categories');

        return CourseDetailResource::make(
            $this->syncCourseCategoriesAction->handle($course, $categories)->load('modules')
        );
    }
}
