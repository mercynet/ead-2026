<?php

namespace App\Modules\Learning\Models;

use App\Modules\Assessment\Models\Certificate;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $instructor_id
 * @property int $price_cents
 * @property string $title
 * @property string $slug
 */
class Course extends Model
{
    protected static string $factory = \Database\Factories\CourseFactory::class;

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

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function materialDownloads(): HasManyThrough
    {
        return $this->hasManyThrough(MaterialDownload::class, CourseMaterial::class);
    }

    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function ratingStats(): MorphOne
    {
        return $this->morphOne(RatingStats::class, 'rateable');
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
