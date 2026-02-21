<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'course_module_id',
        'title',
        'sort_order',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_module_id' => 'integer',
            'sort_order' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class);
    }
}
