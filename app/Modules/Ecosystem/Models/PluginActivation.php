<?php

namespace App\Modules\Ecosystem\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entitlement de um plugin por tenant: se o tenant ativou (free) ou comprou
 * (pago) o plugin. É o gate de disponibilidade da capability (ADR-005) — uma
 * linha por (tenant, plugin).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plugin_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $deactivated_at
 * @property int|null $activated_by
 */
class PluginActivation extends Model
{
    protected static string $factory = \Database\Factories\PluginActivationFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plugin_id',
        'status',
        'activated_at',
        'deactivated_at',
        'activated_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * @param  Builder<PluginActivation>  $query
     * @return Builder<PluginActivation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'plugin_id' => 'integer',
            'activated_by' => 'integer',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }
}
