<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'quiz_attempt_id',
        'question_snapshot',
        'selected_options',
        'is_correct',
        'points_earned',
        'answered_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'quiz_attempt_id' => 'integer',
            'question_snapshot' => 'array',
            'selected_options' => 'array',
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
            'answered_at' => 'datetime',
        ];
    }
}
