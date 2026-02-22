<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'instructor_id',
        'title',
        'slug',
        'description',
        'short_description',
        'target_audience',
        'requirements',
        'what_you_will_learn',
        'what_you_will_build',
        'status',
        'thumbnail',
        'banner',
        'level',
        'price_cents',
        'duration_hours',
        'access_days',
        'is_featured',
        'certificate_enabled',
        'certificate_min_progress',
        'certificate_requires_quiz',
        'certificate_min_score',
        'is_active',
        'published_at',
        'vehiculation_started_at',
        'vehiculation_ended_at',
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
        return $this->belongsToMany(Category::class)
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function isFree(): bool
    {
        return (int) $this->price_cents === 0;
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === 'published';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'instructor_id' => 'integer',
            'price_cents' => 'integer',
            'duration_hours' => 'integer',
            'access_days' => 'integer',
            'is_featured' => 'boolean',
            'certificate_enabled' => 'boolean',
            'certificate_min_progress' => 'integer',
            'certificate_requires_quiz' => 'boolean',
            'certificate_min_score' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'vehiculation_started_at' => 'datetime',
            'vehiculation_ended_at' => 'datetime',
        ];
    }
}
