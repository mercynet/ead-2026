<?php

namespace App\Http\Resources\Learning\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'is_free' => $this->isFree(),
            'is_featured' => $this->is_featured,
            'access_days' => $this->access_days,
            'categories' => CategoryResource::collection($this->categories),
        ];
    }
}
