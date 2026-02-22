<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'questionnaire_id',
        'status',
        'questionnaire_snapshot',
        'course_snapshot',
        'module_snapshot',
        'started_at',
        'finished_at',
        'score',
        'passed',
        'time_spent_seconds',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'questionnaire_id' => 'integer',
            'questionnaire_snapshot' => 'array',
            'course_snapshot' => 'array',
            'module_snapshot' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'score' => 'integer',
            'passed' => 'boolean',
            'time_spent_seconds' => 'integer',
        ];
    }
}
