<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'course_id',
        'enrollment_id',
        'lesson_id',
        'started_at',
        'completed_at',
        'time_spent_seconds',
        'progress_percentage',
        'is_completed',
        'current_time_seconds',
        'total_time_seconds',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isCompleted(): bool
    {
        return $this->is_completed || $this->completed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'course_id' => 'integer',
            'enrollment_id' => 'integer',
            'lesson_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'time_spent_seconds' => 'integer',
            'progress_percentage' => 'integer',
            'is_completed' => 'boolean',
            'current_time_seconds' => 'integer',
            'total_time_seconds' => 'integer',
            'last_watched_at' => 'datetime',
        ];
    }
}
