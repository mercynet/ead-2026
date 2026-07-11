<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Rating;
use App\Modules\Learning\Models\RatingStats;
use Illuminate\Support\Facades\DB;

class SyncRatingStatsAction
{
    public function handle(Course|Lesson $rateable): RatingStats
    {
        return DB::transaction(function () use ($rateable): RatingStats {
            $now = now();
            $ratings = Rating::query()
                ->where('tenant_id', $rateable->tenant_id)
                ->where('rateable_type', $rateable->getMorphClass())
                ->where('rateable_id', $rateable->id);

            $payload = [
                'tenant_id' => $rateable->tenant_id,
                'rateable_type' => $rateable->getMorphClass(),
                'rateable_id' => $rateable->id,
                'average_stars' => round((float) ((clone $ratings)->avg('stars') ?? 0), 2),
                'total_ratings' => (clone $ratings)->count(),
                'five_stars' => (clone $ratings)->where('stars', 5)->count(),
                'four_stars' => (clone $ratings)->where('stars', 4)->count(),
                'three_stars' => (clone $ratings)->where('stars', 3)->count(),
                'two_stars' => (clone $ratings)->where('stars', 2)->count(),
                'one_star' => (clone $ratings)->where('stars', 1)->count(),
                'likes_count' => (clone $ratings)->where('reaction', 'like')->count(),
                'dislikes_count' => (clone $ratings)->where('reaction', 'dislike')->count(),
                'last_rated_at' => (clone $ratings)->max('updated_at'),
                'updated_at' => $now,
                'created_at' => $now,
            ];

            RatingStats::query()->upsert(
                [$payload],
                uniqueBy: ['tenant_id', 'rateable_type', 'rateable_id'],
                update: [
                    'average_stars',
                    'total_ratings',
                    'five_stars',
                    'four_stars',
                    'three_stars',
                    'two_stars',
                    'one_star',
                    'likes_count',
                    'dislikes_count',
                    'last_rated_at',
                    'updated_at',
                ],
            );

            return RatingStats::query()
                ->where('tenant_id', $rateable->tenant_id)
                ->where('rateable_type', $rateable->getMorphClass())
                ->where('rateable_id', $rateable->id)
                ->lockForUpdate()
                ->firstOrFail();
        });
    }
}
