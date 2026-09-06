<?php

namespace App\Modules\Learning\Http\Resources\Admin;

use App\Modules\Learning\Http\Resources\Catalog\CategoryResource;
use App\Modules\Learning\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property int $price_cents
 * @property bool $is_featured
 * @property int $access_days
 * @property int|null $instructor_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Learning\Models\Category> $categories
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'price_cents' => $this->price_cents,
            'is_free' => $this->isFree(),
            'is_featured' => $this->is_featured,
            'access_days' => $this->access_days,
            'instructor_id' => $this->instructor_id,
            'categories' => CategoryResource::collection($this->categories),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
