<?php

namespace App\Modules\Learning\Http\Resources\Course;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialDownloadResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly ?string $downloadUrl = null,
        private readonly ?CarbonInterface $downloadUrlExpiresAt = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_material_id' => $this->course_material_id,
            'user_id' => $this->user_id,
            'download_url' => $this->downloadUrl,
            'download_url_expires_at' => $this->downloadUrlExpiresAt?->toIso8601String(),
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
