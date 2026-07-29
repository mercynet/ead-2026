<?php

namespace App\Modules\Ecosystem\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
 * @property string $configuration_version
 * @property-read Plugin $plugin
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

    protected static function booted(): void
    {
        static::creating(function (self $config): void {
            $config->configuration_version ??= (string) Str::uuid();
        });

        static::saving(function (self $config): void {
            if ($config->exists && ($config->isDirty('plugin_id') || $config->isDirty('tenant_id'))) {
                throw new RuntimeException('Tenant and plugin identity cannot change after configuration creation.');
            }

            if ($config->exists && ($config->isDirty('config') || $config->isDirty('enabled'))) {
                $config->configuration_version = (string) Str::uuid();
            }
        });

        static::saved(function (self $config): void {
            $config->revisions()->firstOrCreate(
                ['configuration_version' => $config->configuration_version],
                ['config' => $config->config],
            );
        });
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * @return HasMany<TenantPluginConfigRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(TenantPluginConfigRevision::class);
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
            'configuration_version' => 'string',
        ];
    }
}
