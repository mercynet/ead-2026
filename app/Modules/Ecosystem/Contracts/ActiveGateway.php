<?php

namespace App\Modules\Ecosystem\Contracts;

/**
 * Gateway ativo/configurado de um tenant, exposto na fronteira pública do
 * Ecosystem para o Financial resolver o adaptador — sem vazar models internos.
 */
final readonly class ActiveGateway
{
    /**
     * @param  string  $slug  identificador do adaptador (casa com `PaymentGatewayInterface::identifier()`)
     * @param  array<string, mixed>  $credentials  config decifrada do plugin de gateway do tenant
     */
    public function __construct(
        public string $slug,
        public array $credentials,
        public int $tenantPluginConfigId,
        public string $configurationVersion,
    ) {
        if ($tenantPluginConfigId <= 0 || $configurationVersion === '') {
            throw new \InvalidArgumentException('Gateway configuration identity is required.');
        }
    }
}
