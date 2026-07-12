<?php

namespace App\Modules\Ecosystem\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Config de instância de um plugin por tenant (ADR-005): store genérico, um por
 * (tenant, plugin). O schema dos campos é declarado em código pelo plugin e
 * validado na persistência.
 *
 * `config` guarda os valores preenchidos pelo tenant — inclusive segredos (chaves
 * de gateway) — persistidos **encriptados em repouso** (`encrypted:array`) e
 * **fora da serialização** (`$hidden`): o cast protege o banco, `$hidden` protege
 * `toArray`/JSON/log.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plugin_id
 * @property array<string, mixed> $config
 * @property bool $enabled
 */
class TenantPluginConfig extends Model
{
    protected static string $factory = \Database\Factories\TenantPluginConfigFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plugin_id',
        'config',
        'enabled',
    ];

    /** @var list<string> */
    protected $hidden = [
        'config',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * @param  Builder<TenantPluginConfig>  $query
     * @return Builder<TenantPluginConfig>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Credenciais decifradas para o adaptador (contrato agnóstico de gateway).
     *
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->config ?? [];
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'plugin_id' => 'integer',
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }
}
