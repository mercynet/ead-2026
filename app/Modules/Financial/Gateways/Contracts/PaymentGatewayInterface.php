<?php

namespace App\Modules\Financial\Gateways\Contracts;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;

/**
 * Contrato que todo adaptador de gateway de pagamento implementa.
 *
 * Adaptadores são stateless (singletons) e **agnósticos de ledger**: recebem as
 * credenciais (array decifrado) e uma {@see ChargeIntent} neutra — nunca o model
 * do ledger. Assim o mesmo adaptador serve o plano Venda (tenant→aluno,
 * credenciais do tenant) e o plano Plataforma (Mozart→tenant, credenciais do
 * Mozart) sem duplicação (ADR-003), e o billing não trava em um PSP (Stripe no
 * MVP; Mercado Pago/PagSeguro/PIX como plugins — ver ADR-001).
 */
interface PaymentGatewayInterface
{
    /**
     * Identificador estável do gateway (casa com `gateway_identifier`), ex.: 'stripe'.
     */
    public function identifier(): string;

    /**
     * Nome legível para exibição, ex.: 'Stripe'.
     */
    public function label(): string;

    public function confirmationMode(): PaymentConfirmationMode;

    /**
     * Schema público de configuração requerido para operar este gateway.
     */
    public function configurationSchema(): GatewayConfigurationDefinition;

    /**
     * Cria uma cobrança avulsa a partir das credenciais e da intenção neutra.
     *
     * @param  array<string, mixed>  $credentials  configuração decifrada do gateway (chaves de API etc.)
     */
    public function charge(array $credentials, ChargeIntent $intent): ChargeResult;

    /**
     * Valida se a configuração fornecida pelo tenant é suficiente para operar.
     *
     * @param  array<string, mixed>  $config
     */
    public function validateConfiguration(array $config): bool;
}
