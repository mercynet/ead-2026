<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseMaterial extends Model
{
    protected static string $factory = \Database\Factories\CourseMaterialFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'course_id',
        'instructor_id',
        'file_path',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MaterialDownload::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(MaterialStats::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_id' => 'integer',
            'instructor_id' => 'integer',
        ];
    }
}
