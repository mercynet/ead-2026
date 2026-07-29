<?php

namespace App\Modules\Financial\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $order_number
 * @property string $status
 * @property string $origin_type
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property string|null $source_key
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Order extends Model
{
    protected static string $factory = \Database\Factories\OrderFactory::class;

    use HasFactory, LogsActivity;

    protected $hidden = ['source_key', 'idempotency_key', 'metadata'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('financial')
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'order_number',
        'status',
        'origin_type',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'source_key',
        'idempotency_key',
        'metadata',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }
}
