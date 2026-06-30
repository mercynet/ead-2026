<?php

namespace App\Modules\Learning\Http\Controllers\Course;

use App\Modules\Learning\Actions\Course\DeleteCourseAction;
use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Course\GetCourseModulesAction;
use App\Modules\Learning\Actions\Course\StoreCourseAction;
use App\Modules\Learning\Actions\Course\UpdateCourseAction;
use App\Modules\Learning\Http\Requests\Course\StoreCourseRequest;
use App\Modules\Learning\Http\Requests\Course\UpdateCourseRequest;
use App\Modules\Learning\Http\Resources\Catalog\CourseDetailResource;
use App\Modules\Learning\Http\Resources\Course\CourseModulesResource;
use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Módulos de Curso
 *
 * Gerenciamento de módulos e aulas do curso
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly GetCourseModulesAction $getCourseModulesAction,
        private readonly GetCourseAction $getCourseAction,
        private readonly StoreCourseAction $storeCourseAction,
        private readonly UpdateCourseAction $updateCourseAction,
        private readonly DeleteCourseAction $deleteCourseAction,
    ) {}

    /**
     * Criar Curso
     *
     * Cria um novo curso no tenant atual.
     * O curso sempre nasce como `draft`; publicar é tarefa da área Admin.
     *
     * @response 201 scenario="Curso criado com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "title": "Novo Curso",
     *     "slug": "novo-curso",
     *     "status": "draft",
     *     "price_cents": 0,
     *     "is_free": true
     *   }
     * }
     * @response 403 scenario="Sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "access_denied", "message": "Acesso negado."}]
     * }
     */
    public function store(StoreCourseRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.courses.create-check', [$context->tenant]);

        $course = $this->storeCourseAction->handle($context, $request->validated());

        return CourseDetailResource::make($course)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Listar Módulos
     *
     * Retorna todos os módulos de um curso matriculado.
     *
     * @urlParam courseId int required ID do curso
     */
    public function modules(int $courseId, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('modules', [Course::class, $context->requiredTenant()]);

        $modules = $this->getCourseModulesAction->handle($context, $courseId);

        return CourseModulesResource::collection($modules);
    }

    /**
     * Atualizar Curso
     *
     * Atualiza um curso existente.
     * Não gerencia publicação; o status é alterado apenas na área Admin.
     *
     * @response 200 scenario="Curso atualizado com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "title": "Curso Atualizado",
     *     "slug": "curso-atualizado",
     *     "description": "Descrição atualizada",
     *     "status": "draft",
     *     "price_cents": 9900,
     *     "level": "beginner",
     *     "is_featured": true,
     *     "is_active": true,
     *     "tenant_id": 1,
     *     "instructor_id": 1,
     *     "published_at": "2026-02-22T10:00:00Z",
     *     "created_at": "2026-02-20T10:00:00Z",
     *     "updated_at": "2026-02-22T10:30:00Z"
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
    public function update(UpdateCourseRequest $request, ApiContext $context, int $id): CourseDetailResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->tenant, $course]);

        $course = $this->updateCourseAction->handle($course, $request->validated());

        return CourseDetailResource::make($course);
    }

    /**
     * Deletar Curso
     *
     * Remove um curso existente.
     *
     * @response 200 scenario="Curso deletado com sucesso"
     * {
     *   "message": "Course deleted successfully."
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
    public function destroy(ApiContext $context, int $id): array
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.delete-check', [$context->tenant, $course]);

        $this->deleteCourseAction->handle($course);

        return ['message' => 'Course deleted successfully.'];
    }
}
