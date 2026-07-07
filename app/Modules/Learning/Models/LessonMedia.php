<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMedia extends Model
{
    protected static string $factory = \Database\Factories\LessonMediaFactory::class;

    use HasFactory;

    protected $table = 'lesson_media';

    protected $fillable = [
        'tenant_id',
        'lesson_id',
        'media_type',
        'provider',
        'provider_ref',
        'url',
        'content',
        'duration_seconds',
        'sort_order',
        'is_active',
        'metadata',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'lesson_id' => 'integer',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
