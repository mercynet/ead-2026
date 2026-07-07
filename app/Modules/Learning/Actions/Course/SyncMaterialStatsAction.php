<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialDownload;
use App\Modules\Learning\Models\MaterialStats;

class SyncMaterialStatsAction
{
    public function handle(CourseMaterial $courseMaterial): MaterialStats
    {
        $now = now();

        $downloads = MaterialDownload::query()
            ->where('tenant_id', $courseMaterial->tenant_id)
            ->where('course_material_id', $courseMaterial->id);

        $payload = [
            'tenant_id' => $courseMaterial->tenant_id,
            'course_material_id' => $courseMaterial->id,
            'total_downloads' => (clone $downloads)->count(),
            'downloads_today' => (clone $downloads)->where('downloaded_at', '>=', $now->copy()->startOfDay())->count(),
            'downloads_week' => (clone $downloads)->where('downloaded_at', '>=', $now->copy()->startOfWeek())->count(),
            'downloads_month' => (clone $downloads)->where('downloaded_at', '>=', $now->copy()->startOfMonth())->count(),
            'last_downloaded_at' => (clone $downloads)->max('downloaded_at'),
            'updated_at' => $now,
            'created_at' => $now,
        ];

        MaterialStats::query()->upsert(
            [$payload],
            uniqueBy: ['course_material_id'],
            update: [
                'tenant_id',
                'total_downloads',
                'downloads_today',
                'downloads_week',
                'downloads_month',
                'last_downloaded_at',
                'updated_at',
            ],
        );

        return MaterialStats::query()
            ->where('course_material_id', $courseMaterial->id)
            ->firstOrFail();
    }
}
