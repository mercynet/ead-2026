<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Models\MaterialDownload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaterialDownload
 *
 * @property int $id
 * @property int $course_material_id
 * @property string|null $download_url
 * @property \Illuminate\Support\Carbon|null $download_url_expires_at
 * @property \Illuminate\Support\Carbon|null $downloaded_at
 */
class MaterialDownloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_material_id' => $this->course_material_id,
            'download_url' => $this->download_url,
            'download_url_expires_at' => $this->download_url_expires_at?->toIso8601String(),
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),
        ];
    }
}
