<?php

namespace App\Modules\Learning\Http\Resources\Enrollment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'progress_percentage' => $this->progress_percentage,
            'access_expires_at' => $this->access_expires_at?->toISOString(),
            'enrolled_at' => $this->enrolled_at?->toISOString(),
            'course' => [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ],
            'user' => $this->whenLoaded('user', function (): array {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'tenant_id' => $this->user->tenant_id,
                ];
            }),
        ];
    }
}
