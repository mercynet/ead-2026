<?php

namespace App\Modules\Financial\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property string $event_type
 * @property array<string, mixed>|null $payload
 * @property string|null $claim_token
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property int $attempt_count
 * @property \Illuminate\Support\Carbon|null $last_failed_at
 * @property string|null $last_error_class
 */
class OrderPaidOutbox extends Model
{
    protected $table = 'order_paid_outbox';

    protected $fillable = ['order_id', 'event_type', 'payload', 'claim_token', 'claimed_at', 'dispatched_at', 'attempt_count', 'last_failed_at', 'last_error_class'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'claimed_at' => 'datetime', 'dispatched_at' => 'datetime', 'last_failed_at' => 'datetime', 'attempt_count' => 'integer'];
    }
}
