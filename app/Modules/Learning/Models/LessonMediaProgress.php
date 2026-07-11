<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMediaProgress extends Model
{
    use HasFactory;

    protected static string $factory = \Database\Factories\LessonMediaProgressFactory::class;

    protected $table = 'lesson_media_progress';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'lesson_media_id',
        'watched_seconds',
        'completion_percentage',
        'watch_sessions',
        'is_completed',
        'completed_at',
        'last_watched_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonMedia(): BelongsTo
    {
        return $this->belongsTo(LessonMedia::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'lesson_media_id' => 'integer',
            'watched_seconds' => 'integer',
            'completion_percentage' => 'decimal:2',
            'watch_sessions' => 'array',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'last_watched_at' => 'datetime',
        ];
    }
}
