<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RatingStats extends Model
{
    protected static string $factory = \Database\Factories\RatingStatsFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'rateable_type',
        'rateable_id',
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
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'rateable_id' => 'integer',
            'average_stars' => 'float',
            'total_ratings' => 'integer',
            'five_stars' => 'integer',
            'four_stars' => 'integer',
            'three_stars' => 'integer',
            'two_stars' => 'integer',
            'one_star' => 'integer',
            'likes_count' => 'integer',
            'dislikes_count' => 'integer',
            'last_rated_at' => 'datetime',
        ];
    }
}
