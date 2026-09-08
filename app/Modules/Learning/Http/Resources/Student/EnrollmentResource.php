<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 *
 * @property int $id
 * @property int $course_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $enrolled_at
 * @property \Illuminate\Support\Carbon|null $access_expires_at
 * @property int $progress_percentage
 */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'access_expires_at' => $this->access_expires_at?->toIso8601String(),
            'progress_percentage' => $this->progress_percentage,
        ];
    }
}
