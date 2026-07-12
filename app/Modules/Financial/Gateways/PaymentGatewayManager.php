<?php

namespace App\Modules\Financial\Gateways;

use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;

/**
 * Registro de adaptadores de gateway de pagamento.
 *
 * Os adaptadores concretos (ex.: StripeGateway) registram-se aqui no boot; a
 * fundação não trava em nenhum PSP (ADR-001) e serve os dois ledgers via
 * contrato agnóstico (ADR-003).
 *
 * A RESOLUÇÃO do gateway ativo por tenant (credenciais, escolha de default,
 * entitlement) depende da config de instância do plugin — âmbito do módulo
 * Ecosystem, ainda não implementado. Vive fora deste registro até lá; ao ser
 * construída, deve ligar adaptador + credenciais atomicamente e só resolver
 * config validada (ver Needs Review em docs/specs/40-financial/tasks.md).
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->identifier()] = $gateway;
    }

    public function get(string $identifier): ?PaymentGatewayInterface
    {
        return $this->gateways[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->gateways[$identifier]);
    }

    /** @return array<string, PaymentGatewayInterface> */
    public function all(): array
    {
        return $this->gateways;
    }
}
