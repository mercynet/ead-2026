<?php

namespace App\Modules\Financial\Gateways\Data;

/**
 * Resultado normalizado de uma tentativa de cobrança, agnóstico de gateway.
 */
final readonly class ChargeResult
{
    /**
     * @param  string  $status  estado normalizado: pending | paid | failed
     * @param  string|null  $externalId  id da transação/cobrança no gateway
     * @param  string|null  $redirectUrl  URL de checkout/pagamento (PIX, hosted checkout) quando aplicável
     * @param  array<string, mixed>  $raw  resposta crua do gateway (auditoria/debug)
     */
    public function __construct(
        public bool $successful,
        public string $status,
        public ?string $externalId = null,
        public ?string $redirectUrl = null,
        public array $raw = [],
    ) {}
}
