<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Questionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'instructor_id',
        'title',
        'description',
        'type',
        'quizable_id',
        'quizable_type',
        'passing_score',
        'time_limit_minutes',
        'is_active',
        'show_results',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function quizable(): MorphTo
    {
        return $this->morphTo();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionnaireQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'instructor_id' => 'integer',
            'passing_score' => 'integer',
            'time_limit_minutes' => 'integer',
            'is_active' => 'boolean',
            'show_results' => 'boolean',
        ];
    }
}
