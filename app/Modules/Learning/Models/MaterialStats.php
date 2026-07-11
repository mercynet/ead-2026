<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStats extends Model
{
    protected static string $factory = \Database\Factories\MaterialStatsFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'course_material_id',
        'total_downloads',
        'downloads_today',
        'downloads_week',
        'downloads_month',
        'last_downloaded_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_material_id' => 'integer',
            'total_downloads' => 'integer',
            'downloads_today' => 'integer',
            'downloads_week' => 'integer',
            'downloads_month' => 'integer',
            'last_downloaded_at' => 'datetime',
        ];
    }
}
