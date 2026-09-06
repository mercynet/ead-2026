<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $parent_id
 * @property int $depth
 * @property string|null $path
 * @property string $name
 * @property string $slug
 * @property string $normalized_name
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_system
 * @property string $status
 * @property bool $is_featured
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Category extends Model
{
    protected static string $factory = \Database\Factories\CategoryFactory::class;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'path',
        'depth',
        'name',
        'slug',
        'normalized_name',
        'is_system',
        'color',
        'description',
        'icon',
        'status',
        'is_featured',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->withPivot('tenant_id', 'sort_order', 'is_featured')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('courses.id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'parent_id' => 'integer',
            'depth' => 'integer',
            'is_system' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
