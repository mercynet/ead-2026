<?php

namespace App\Modules\Learning\Http\Controllers\Instructor;

use App\Modules\Learning\Actions\Course\DeleteCourseAction;
use App\Modules\Learning\Actions\Course\GetInstructorCourseAction;
use App\Modules\Learning\Actions\Course\ListInstructorCoursesAction;
use App\Modules\Learning\Actions\Course\StoreCourseAction;
use App\Modules\Learning\Actions\Course\UpdateCourseAction;
use App\Modules\Learning\Http\Requests\Instructor\ListCoursesRequest;
use App\Modules\Learning\Http\Requests\Instructor\StoreCourseRequest;
use App\Modules\Learning\Http\Requests\Instructor\UpdateCourseRequest;
use App\Modules\Learning\Http\Resources\Instructor\CourseResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Instructor · Cursos
 *
 * Authoring de cursos próprios na área Instructor. Esta superfície não publica,
 * despublica ou arquiva cursos.
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly GetInstructorCourseAction $getCourseAction,
        private readonly ListInstructorCoursesAction $listCoursesAction,
        private readonly StoreCourseAction $storeCourseAction,
        private readonly UpdateCourseAction $updateCourseAction,
        private readonly DeleteCourseAction $deleteCourseAction,
    ) {}

    /**
     * Listar meus cursos.
     *
     * Inclui drafts e cursos inativos do Instructor autenticado.
     */
    public function index(ListCoursesRequest $request, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.instructor.courses.list', [$context->requiredTenant()]);

        return CourseResource::collection($this->listCoursesAction->handle($context));
    }

    /**
     * Criar meu curso.
     *
     * O tenant, o Instructor e o status draft são derivados do contexto/domínio.
     */
    public function store(StoreCourseRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.courses.create-check', [$context->requiredTenant()]);

        return CourseResource::make(
            $this->storeCourseAction->handle($context, $request->validated(), $context->requiredUser()->id)
        )->response()->setStatusCode(201);
    }

    /**
     * Ver meu curso para authoring.
     */
    public function show(ApiContext $context, int $id): CourseResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.instructor.courses.view-check', [$context->requiredTenant(), $course]);

        return CourseResource::make($course);
    }

    /**
     * Preview do meu curso, incluindo conteúdo ainda em preparação.
     */
    public function preview(ApiContext $context, int $id): CourseResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.preview-check', [$context->requiredTenant(), $course]);

        return CourseResource::make($course);
    }

    /**
     * Atualizar metadados do meu curso sem alterar lifecycle ou ownership.
     */
    public function update(UpdateCourseRequest $request, ApiContext $context, int $id): CourseResource
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [$context->requiredTenant(), $course]);

        return CourseResource::make(
            $this->updateCourseAction->handle($course, $request->validated(), $context->requiredUser()->id)
        );
    }

    /**
     * Remover meu curso.
     */
    public function destroy(ApiContext $context, int $id): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $id);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.delete-check', [$context->requiredTenant(), $course]);

        $this->deleteCourseAction->handle($course);

        return new JsonResponse(['data' => ['message' => 'Course deleted successfully.']]);
    }
}
