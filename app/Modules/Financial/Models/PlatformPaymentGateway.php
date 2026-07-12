<?php

namespace App\Modules\Financial\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gateway das vendas da plataforma (Mzrt→tenant), plano Plataforma — global, do
 * landlord (ADR-005). Diferente do gateway do tenant (que é config de plugin): o
 * Mzrt não "compra" o próprio gateway. Um driver ativo por vez.
 *
 * `configuration` guarda segredos (chaves do PSP do Mzrt): **encriptada em
 * repouso** (`encrypted:array`) e **fora da serialização** (`$hidden`).
 *
 * @property int $id
 * @property string $gateway_slug
 * @property array<string, mixed> $configuration
 * @property bool $is_active
 * @property bool $is_default
 */
class PlatformPaymentGateway extends Model
{
    protected static string $factory = \Database\Factories\PlatformPaymentGatewayFactory::class;

    use HasFactory;

    protected $fillable = [
        'gateway_slug',
        'configuration',
        'is_active',
        'is_default',
    ];

    /** @var list<string> */
    protected $hidden = [
        'configuration',
    ];

    /**
     * @param  Builder<PlatformPaymentGateway>  $query
     * @return Builder<PlatformPaymentGateway>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Torna este o driver padrão, rebaixando os demais — atômico (transação).
     */
    public function makeDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
