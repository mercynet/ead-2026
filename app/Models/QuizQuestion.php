<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'instructor_id',
        'question',
        'type',
        'options',
        'correct_options',
        'explanation',
        'points',
        'is_active',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'quiz_question_categories')
            ->withTimestamps();
    }

    public function questionnaires(): BelongsToMany
    {
        return $this->belongsToMany(Questionnaire::class, 'questionnaire_questions')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'instructor_id' => 'integer',
            'options' => 'array',
            'correct_options' => 'array',
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
