<?php

namespace App\Modules\Financial\Gateways\Data;

use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;

/**
 * Adaptador + credenciais resolvidos como uma unidade atômica, para não casar o
 * adaptador de um gateway com a config de outro tenant/gateway. `charge()` já
 * injeta as credenciais certas.
 */
final readonly class ResolvedGateway
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public PaymentGatewayInterface $adapter,
        public array $credentials,
    ) {}

    public function charge(ChargeIntent $intent): ChargeResult
    {
        return $this->adapter->charge($this->credentials, $intent);
    }
}
