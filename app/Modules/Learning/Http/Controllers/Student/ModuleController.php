<?php

namespace App\Modules\Learning\Http\Controllers\Student;

use App\Modules\Learning\Actions\Lesson\ListStudentLessonsAction;
use App\Modules\Learning\Http\Resources\Student\LessonSummaryResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Student — navegação
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly ListStudentLessonsAction $listStudentLessonsAction,
    ) {}

    /**
     * Aulas do módulo
     *
     * Lista somente aulas publicadas e ativas do módulo pertencente ao curso acessível.
     *
     * @urlParam courseId int required ID do curso
     * @urlParam moduleId int required ID do módulo
     */
    public function lessons(int $courseId, int $moduleId, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.view', [$context->requiredTenant()]);

        return LessonSummaryResource::collection($this->listStudentLessonsAction->handle($context, $courseId, $moduleId));
    }
}
