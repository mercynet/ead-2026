<?php

namespace App\Modules\Learning\Http\Controllers\Student;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Actions\Course\ListStudentCoursesAction;
use App\Modules\Learning\Actions\Course\ListStudentModulesAction;
use App\Modules\Learning\Http\Resources\Student\CourseResource;
use App\Modules\Learning\Http\Resources\Student\ModuleResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Student — cursos
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly ListStudentCoursesAction $listStudentCoursesAction,
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
        private readonly ListStudentModulesAction $listStudentModulesAction,
    ) {}

    /**
     * Meus cursos
     *
     * Lista os cursos publicados e ativos que o aluno pode consumir agora.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.list', [$context->requiredTenant()]);

        return CourseResource::collection($this->listStudentCoursesAction->handle($context));
    }

    /**
     * Meu curso
     *
     * Retorna o resumo do curso acessível e a projeção da própria matrícula.
     *
     * @urlParam courseId int required ID do curso
     */
    public function show(int $courseId, ApiContext $context): CourseResource
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.view', [$context->requiredTenant()]);

        return CourseResource::make($this->resolveStudentAccessAction->requireCourse($context, $courseId));
    }

    /**
     * Módulos do meu curso
     *
     * Lista módulos navegáveis do curso acessível, por cursor.
     *
     * @urlParam courseId int required ID do curso
     */
    public function modules(int $courseId, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.view', [$context->requiredTenant()]);

        return ModuleResource::collection($this->listStudentModulesAction->handle($context, $courseId));
    }
}
