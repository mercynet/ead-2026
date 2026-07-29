<?php

namespace App\Modules\Financial\Gateways\Data;

use App\Modules\Financial\Enums\PaymentChargeStatus;

/**
 * Resultado normalizado de uma tentativa de cobrança, agnóstico de gateway.
 */
final readonly class ChargeResult
{
    /**
     * @param  PaymentChargeStatus  $status  estado normalizado
     * @param  string|null  $externalId  id da transação/cobrança no gateway
     * @param  string|null  $redirectUrl  URL de checkout/pagamento (PIX, hosted checkout) quando aplicável
     * @param  array<string, mixed>  $raw  resposta crua do gateway (auditoria/debug)
     */
    public function __construct(
        public PaymentChargeStatus $status,
        public ?string $externalId = null,
        public ?string $redirectUrl = null,
        public ?string $clientSecret = null,
        public array $raw = [],
    ) {}
}
