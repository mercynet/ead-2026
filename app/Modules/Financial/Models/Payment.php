<?php

namespace App\Modules\Financial\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $order_id
 * @property string $status
 * @property string $charge_state
 * @property int|null $tenant_plugin_config_id
 * @property string|null $gateway_configuration_version
 * @property string|null $psp_idempotency_key
 * @property string|null $charge_claim_token
 * @property \Illuminate\Support\Carbon|null $charge_claimed_at
 * @property string|null $gateway_slug
 * @property string|null $confirmation_mode
 * @property string|null $external_id
 * @property array<string, mixed>|null $gateway_response
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Payment extends Model
{
    protected static string $factory = \Database\Factories\PaymentFactory::class;

    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('financial')
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'order_id',
        'status',
        'gateway_slug',
        'confirmation_mode',
        'external_id',
        'gateway_response',
        'metadata',
        'tenant_plugin_config_id',
        'gateway_configuration_version',
        'psp_idempotency_key',
        'charge_state',
        'charge_claim_token',
        'charge_claimed_at',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected $hidden = ['gateway_response', 'metadata', 'psp_idempotency_key', 'charge_claim_token'];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'tenant_plugin_config_id' => 'integer',
            'charge_claimed_at' => 'datetime',
            'gateway_response' => 'array',
            'metadata' => 'array',
        ];
    }
}
