<?php

namespace App\Modules\Ecosystem\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Raiz do marketplace (catálogo, âmbito Mzrt/dev). Global — não tenant-scoped:
 * o catálogo é provisionado só pelo developer (cria/edita/ativa/desativa/depreicia).
 *
 * Um plugin é uma **capability do core** (ADR-005): `capability_key` liga/desliga
 * uma feature já presente no código, gated por tenant — não código carregado em
 * runtime. Gateways são plugins (`kind = 'gateway'`, `capability_key = 'gateway.<slug>'`)
 * cujo slug casa com o `identifier()` do adaptador registrado no `PaymentGatewayManager`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $capability_key
 * @property string $kind
 * @property string $status
 * @property string $visibility
 * @property string $tier
 * @property bool $is_curated
 * @property string|null $directory_name
 * @property string|null $short_description
 * @property string|null $long_description
 * @property string|null $logo_path
 * @property string|null $default_locale
 * @property string|null $support_url
 * @property string|null $docs_url
 */
class Plugin extends Model
{
    protected static string $factory = \Database\Factories\PluginFactory::class;

    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'capability_key',
        'kind',
        'status',
        'visibility',
        'tier',
        'is_curated',
        'directory_name',
        'short_description',
        'long_description',
        'logo_path',
        'default_locale',
        'support_url',
        'docs_url',
    ];

    /**
     * Plugins que estão no ciclo de vida "ao vivo" (aparecem na vitrine).
     *
     * @param  Builder<Plugin>  $query
     * @return Builder<Plugin>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'active']);
    }

    /**
     * Plugins visíveis à vitrine do tenant (público + publicado).
     *
     * @param  Builder<Plugin>  $query
     * @return Builder<Plugin>
     */
    public function scopeVisibleToTenants(Builder $query): Builder
    {
        return $query->published()->where('visibility', 'public');
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'active'], true);
    }

    public function isGateway(): bool
    {
        return $this->kind === 'gateway';
    }

    /**
     * Slug do adaptador de gateway (parte após `gateway.` da capability, ou o próprio slug).
     */
    public function gatewaySlug(): ?string
    {
        if (! $this->isGateway()) {
            return null;
        }

        return str_starts_with($this->capability_key, 'gateway.')
            ? substr($this->capability_key, strlen('gateway.'))
            : $this->slug;
    }

    protected function casts(): array
    {
        return [
            'is_curated' => 'boolean',
        ];
    }
}
