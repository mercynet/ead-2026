<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialDownload extends Model
{
    protected static string $factory = \Database\Factories\MaterialDownloadFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'course_material_id',
        'user_id',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_material_id' => 'integer',
            'user_id' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }
}
