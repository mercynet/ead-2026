<?php

namespace App\Modules\Financial\Gateways\Data;

/**
 * Intenção de cobrança neutra de ledger.
 *
 * Montada a partir de um `Order` (plano Venda, tenant→aluno) ou de um
 * `PlatformOrder` (plano Plataforma, Mozart→tenant). O adaptador de gateway só
 * enxerga a intenção + as credenciais — nunca o model do ledger — o que deixa
 * um mesmo adaptador (ex.: StripeGateway) servir os dois ledgers (ADR-003).
 */
final readonly class ChargeIntent
{
    /**
     * @param  int  $amountCents  valor total em centavos inteiros
     * @param  string  $currency  código ISO-4217 minúsculo, ex.: 'brl'
     * @param  string  $reference  referência do pagador (ex.: `order_number`) para correlação
     * @param  string|null  $description  descrição legível da cobrança
     * @param  array<string, mixed>  $metadata  dados extras repassados ao gateway
     */
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $reference,
        public string $idempotencyKey,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
