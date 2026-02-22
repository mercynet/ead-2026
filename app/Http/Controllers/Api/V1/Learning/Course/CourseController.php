<?php

namespace App\Http\Controllers\Api\V1\Learning\Course;

use App\Actions\Learning\Course\GetCourseModulesAction;
use App\Actions\Learning\Course\UpdateCourseAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\Course\UpdateCourseRequest;
use App\Http\Resources\Learning\Course\CourseModulesResource;
use App\Models\Course;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Módulos de Curso
 *
 * Gerenciamento de módulos e aulas do curso
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly GetCourseModulesAction $getCourseModulesAction,
        private readonly UpdateCourseAction $updateCourseAction,
    ) {}

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
     *
     * @unauthenticated
     *
     * @response 200 scenario="Curso atualizado com sucesso"
     * {
     *   "data": {
     *     "id": 1,
     *     "title": "Curso Atualizado",
     *     "slug": "curso-atualizado",
     *     "description": "Descrição atualizada",
     *     "status": "published",
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
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Curso não encontrado"
     * {
     *   "message": "No query results for model [App\\Models\\Course]."
     * }
     */
    public function update(UpdateCourseRequest $request, ApiContext $context, int $id): \App\Http\Resources\Learning\Catalog\CourseDetailResource
    {
        $course = Course::query()
            ->where('tenant_id', $context->tenant?->id)
            ->findOrFail($id);

        if (! Gate::check('learning.courses.update-check', [$context->tenant, $course], $context->user)) {
            abort(403);
        }

        try {
            $course = $this->updateCourseAction->handle($course, $request->validated());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return \App\Http\Resources\Learning\Catalog\CourseDetailResource::make($course);
    }

    /**
     * Deletar Curso
     *
     * Remove um curso existente.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Curso deletado com sucesso"
     * {
     *   "message": "Course deleted successfully."
     * }
     * @response 403 scenario="Sem permissão"
     * {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Curso não encontrado"
     * {
     *   "message": "No query results for model [App\\Models\\Course]."
     * }
     */
    public function destroy(ApiContext $context, int $id): array
    {
        $course = Course::query()
            ->where('tenant_id', $context->tenant?->id)
            ->findOrFail($id);

        if (! Gate::check('learning.courses.delete-check', [$context->tenant, $course], $context->user)) {
            abort(403);
        }

        $course->delete();

        return ['message' => 'Course deleted successfully.'];
    }
}
