<?php

namespace App\Http\Controllers\Api\V1\Learning\Course;

use App\Actions\Learning\Course\GetCourseModulesAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\Course\CourseModulesResource;
use App\Models\Course;
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
    ) {}

    /**
     * Listar Módulos
     *
     * Retorna todos os módulos de um curso matriculado.
     *
     * @urlParam courseId int required ID do curso
     */
    public function modules(int $courseId, ApiContext $context): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('modules', [Course::class, $context->requiredTenant()]);

        $modules = $this->getCourseModulesAction->handle($context, $courseId);

        return CourseModulesResource::collection($modules);
    }
}
