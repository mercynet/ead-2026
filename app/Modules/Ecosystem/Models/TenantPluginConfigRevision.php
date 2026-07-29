<?php

namespace App\Modules\Ecosystem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_plugin_config_id
 * @property array<string, mixed> $config
 * @property string $configuration_version
 * @property-read TenantPluginConfig $tenantPluginConfig
 */
class TenantPluginConfigRevision extends Model
{
    protected $fillable = [
        'tenant_plugin_config_id',
        'configuration_version',
        'config',
    ];

    /** @var list<string> */
    protected $hidden = [
        'config',
    ];

    /**
     * @return BelongsTo<TenantPluginConfig, $this>
     */
    public function tenantPluginConfig(): BelongsTo
    {
        return $this->belongsTo(TenantPluginConfig::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->config ?? [];
    }

    protected function casts(): array
    {
        return [
            'tenant_plugin_config_id' => 'integer',
            'config' => 'encrypted:array',
            'configuration_version' => 'string',
        ];
    }
}
