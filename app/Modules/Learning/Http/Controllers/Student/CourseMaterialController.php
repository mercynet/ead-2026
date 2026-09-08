<?php

namespace App\Modules\Learning\Http\Controllers\Student;

use App\Modules\Learning\Actions\Course\DownloadStudentMaterialAction;
use App\Modules\Learning\Actions\Course\ListStudentMaterialsAction;
use App\Modules\Learning\Http\Resources\Student\CourseMaterialResource;
use App\Modules\Learning\Http\Resources\Student\MaterialDownloadResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Student — materiais
 */
class CourseMaterialController extends Controller
{
    public function __construct(
        private readonly ListStudentMaterialsAction $listStudentMaterialsAction,
        private readonly DownloadStudentMaterialAction $downloadStudentMaterialAction,
    ) {}

    /**
     * Materiais do curso
     *
     * Lista os materiais disponíveis para matrícula ativa do aluno.
     *
     * @urlParam courseId int required ID do curso
     */
    public function index(int $courseId, ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.view', [$context->requiredTenant()]);

        return CourseMaterialResource::collection($this->listStudentMaterialsAction->handle($context, $courseId));
    }

    /**
     * Baixar material do curso
     *
     * Autoriza o curso e o material antes de gerar URL temporária; o backend não faz proxy binário.
     *
     * @urlParam courseId int required ID do curso
     * @urlParam materialId int required ID do material
     */
    public function download(int $courseId, int $materialId, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('learning.student.courses.view', [$context->requiredTenant()]);

        return MaterialDownloadResource::make($this->downloadStudentMaterialAction->handle($context, $courseId, $materialId))
            ->response()
            ->setStatusCode(201);
    }
}
