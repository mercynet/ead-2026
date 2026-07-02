<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    public const STATUSES = ['pending', 'active', 'cancelled', 'expired'];

    public const CURRENT_STATUSES = ['pending', 'active'];

    protected static string $factory = \Database\Factories\EnrollmentFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'course_id',
        'status',
        'access_expires_at',
        'enrolled_at',
        'completed_at',
        'progress_percentage',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function scopeForTenantUserCourse(Builder $query, int $tenantId, int $userId, int $courseId): Builder
    {
        return $query
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('course_id', $courseId);
    }

    public function scopeCurrentStatuses(Builder $query): Builder
    {
        return $query->whereIn('status', self::CURRENT_STATUSES);
    }

    public function scopeOrderedByCurrentStatusPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status IN ('pending', 'active') THEN 0 ELSE 1 END")
            ->orderByDesc('id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->access_expires_at === null) {
            return true;
        }

        return $this->access_expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        return $this->status === 'active' && $this->access_expires_at?->isPast() === true;
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'course_id' => 'integer',
            'progress_percentage' => 'integer',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'access_expires_at' => 'datetime',
        ];
    }
}
