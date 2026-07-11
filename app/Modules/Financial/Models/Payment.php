<?php

namespace App\Modules\Financial\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected static string $factory = \Database\Factories\PaymentFactory::class;

    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'external_id',
        'gateway_response',
        'metadata',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'gateway_response' => 'array',
            'metadata' => 'array',
        ];
    }
}
