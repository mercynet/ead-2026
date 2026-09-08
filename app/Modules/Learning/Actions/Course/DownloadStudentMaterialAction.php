<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialDownload;
use App\Shared\Http\ApiContext;

class DownloadStudentMaterialAction
{
    public function __construct(
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
        private readonly GenerateCourseMaterialDownloadUrlAction $generateDownloadUrlAction,
        private readonly StoreMaterialDownloadAction $storeMaterialDownloadAction,
    ) {}

    public function handle(ApiContext $context, int $courseId, int $materialId): MaterialDownload
    {
        $course = $this->resolveStudentAccessAction->requireCourse($context, $courseId);
        $material = CourseMaterial::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $course->id)
            ->findOrFail($materialId);

        $downloadUrl = $this->generateDownloadUrlAction->handle($material);
        $download = $this->storeMaterialDownloadAction->handle($context, $course, $material);
        $download->setAttribute('download_url', $downloadUrl['url']);
        $download->setAttribute('download_url_expires_at', $downloadUrl['expires_at']);

        return $download;
    }
}
