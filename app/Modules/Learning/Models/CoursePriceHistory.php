<?php

namespace App\Modules\Learning\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePriceHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'course_id',
        'changed_by_user_id',
        'old_price_cents',
        'new_price_cents',
        'changed_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Course price history is append-only.'));
        static::deleting(fn (): never => throw new \LogicException('Course price history is append-only.'));
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'course_id' => 'integer',
            'changed_by_user_id' => 'integer',
            'old_price_cents' => 'integer',
            'new_price_cents' => 'integer',
            'changed_at' => 'datetime',
        ];
    }
}
