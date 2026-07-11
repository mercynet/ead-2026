<?php

namespace App\Modules\Learning\Http\Resources\Course;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'average_stars' => $this->average_stars,
            'total_ratings' => $this->total_ratings,
            'five_stars' => $this->five_stars,
            'four_stars' => $this->four_stars,
            'three_stars' => $this->three_stars,
            'two_stars' => $this->two_stars,
            'one_star' => $this->one_star,
            'likes_count' => $this->likes_count,
            'dislikes_count' => $this->dislikes_count,
            'last_rated_at' => $this->last_rated_at?->toIso8601String(),
        ];
    }
}
