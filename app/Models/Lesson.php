<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'course_module_id',
        'title',
        'slug',
        'short_description',
        'description',
        'video_path',
        'status',
        'thumbnail',
        'content_type',
        'duration',
        'sort_order',
        'is_free',
        'is_active',
        'published_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === 'published';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_module_id' => 'integer',
            'sort_order' => 'integer',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
