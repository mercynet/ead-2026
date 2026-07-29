<?php

namespace App\Modules\Financial\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $itemable_type
 * @property int $itemable_id
 * @property array<string, mixed>|null $item_snapshot
 * @property int $price_cents
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrderItem extends Model
{
    protected static string $factory = \Database\Factories\OrderItemFactory::class;

    use HasFactory;

    protected $fillable = [
        'order_id',
        'itemable_type',
        'itemable_id',
        'item_snapshot',
        'price_cents',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'itemable_id' => 'integer',
            'item_snapshot' => 'array',
            'price_cents' => 'integer',
        ];
    }
}
