<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Events\MaterialDownloadedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialDownload;
use App\Shared\Http\ApiContext;
use Illuminate\Contracts\Events\Dispatcher;

class StoreMaterialDownloadAction
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly SyncMaterialStatsAction $syncMaterialStatsAction,
    ) {}

    public function handle(ApiContext $context, Course $course, CourseMaterial $courseMaterial, array $attributes = []): MaterialDownload
    {
        $download = MaterialDownload::query()->create([
            'tenant_id' => $context->requiredTenant()->id,
            'course_material_id' => $courseMaterial->id,
            'user_id' => $context->requiredUser()->id,
            'ip_address' => $attributes['ip_address'] ?? null,
            'user_agent' => $attributes['user_agent'] ?? null,
            'downloaded_at' => $attributes['downloaded_at'] ?? now(),
        ]);

        $this->syncMaterialStatsAction->handle($courseMaterial);

        $this->events->dispatch(new MaterialDownloadedEvent(
            $courseMaterial,
            $context->requiredUser(),
            $course,
            $download,
        ));

        return $download;
    }
}
