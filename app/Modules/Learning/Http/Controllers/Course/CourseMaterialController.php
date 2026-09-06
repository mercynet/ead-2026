<?php

namespace App\Modules\Learning\Http\Controllers\Course;

use App\Modules\Learning\Actions\Access\EvaluateCourseAccessAction;
use App\Modules\Learning\Actions\Course\GenerateCourseMaterialDownloadUrlAction;
use App\Modules\Learning\Actions\Course\GetCourseAction;
use App\Modules\Learning\Actions\Course\GetCourseMaterialAction;
use App\Modules\Learning\Actions\Course\StoreCourseMaterialAction;
use App\Modules\Learning\Actions\Course\StoreMaterialDownloadAction;
use App\Modules\Learning\Http\Requests\Course\StoreCourseMaterialRequest;
use App\Modules\Learning\Http\Requests\Course\StoreMaterialDownloadRequest;
use App\Modules\Learning\Http\Resources\Course\CourseMaterialResource;
use App\Modules\Learning\Http\Resources\Course\MaterialDownloadResource;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CourseMaterialController extends Controller
{
    public function __construct(
        private readonly EvaluateCourseAccessAction $evaluateCourseAccessAction,
        private readonly GenerateCourseMaterialDownloadUrlAction $generateCourseMaterialDownloadUrlAction,
        private readonly GetCourseAction $getCourseAction,
        private readonly GetCourseMaterialAction $getCourseMaterialAction,
        private readonly StoreCourseMaterialAction $storeCourseMaterialAction,
        private readonly StoreMaterialDownloadAction $storeMaterialDownloadAction,
    ) {}

    public function store(int $courseId, StoreCourseMaterialRequest $request, ApiContext $context): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.update-check', [
            $context->requiredTenant(),
            $course,
        ]);

        $courseMaterial = $this->storeCourseMaterialAction->handle($context, $course, $request->validated(), $context->requiredUser()->id);

        return CourseMaterialResource::make($courseMaterial)
            ->response()
            ->setStatusCode(201);
    }

    public function storeDownload(int $courseId, int $materialId, StoreMaterialDownloadRequest $request, ApiContext $context): JsonResponse
    {
        $course = $this->getCourseAction->handle($context, $courseId);
        $courseMaterial = $this->getCourseMaterialAction->handle($context, $courseId, $materialId);

        Gate::forUser($context->requiredUser())->authorize('learning.courses.view-check', [
            $context->requiredTenant(),
            $course,
        ]);

        if ($context->requiredUser()->isStudent() && ! $this->evaluateCourseAccessAction->canAccessPaidContent($course, $context)) {
            throw AccessDeniedException::make('course', $course->id);
        }

        $downloadUrl = $this->generateCourseMaterialDownloadUrlAction->handle($courseMaterial);
        $download = $this->storeMaterialDownloadAction->handle($context, $course, $courseMaterial, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return new MaterialDownloadResource(
            $download,
            $downloadUrl['url'],
            $downloadUrl['expires_at'],
        )
            ->response()
            ->setStatusCode(201);
    }
}
