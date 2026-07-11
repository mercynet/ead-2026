---
domain: financial
last-updated: 2026-07-09
---

# Tasks — Financial

Domínio **não iniciado**. Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model).
Critério de aceite = teste.

## Done

- [x] Fundação mínima interna do ledger tenant→aluno: módulo `Financial` registrado, models +
  migrations + factories de `Order`/`OrderItem`/`Payment`, `OrderPaidEvent` com payload primitivo e
  guard monetário ampliado para migrations modulares.
- [x] `OrderPaidEvent` + listener no Learning (`EnrollService`).

## In Progress

- [ ] Registro financeiro espelho para matrículas gratuitas (auditoria/LTV).

## Pending

- [ ] Completar a fundação financeira restante: `PriceHistory` + `TenantPaymentGateway`.
- [ ] `POST /financial/checkout` (calcula preço no servidor, gera Order + sessão do gateway).
- [ ] `GET /financial/orders` e `GET /financial/orders/{id}`.
- [ ] `POST /financial/webhooks/gateway/{gateway_slug}` (rota cega) + `ProcessPaymentWebhookJob`.
- [ ] Tradução PT-BR de exceções de gateway.

### Reuso eadIA + billing (ver ADR-001)
- [ ] **`PaymentGatewayInterface`** (contrato) + `TenantPaymentGateway` (config encriptada por tenant) — portar do eadIA. Fundação que não trava em gateway.
- [ ] **`StripeGateway` via `laravel/cashier`** (1º adaptador; add Cashier nesta task). Cobre cartão/PIX/boleto BR + global.
- [ ] **Gateways adicionais como plugins financeiros** (Mercado Pago, PagSeguro, PIX-nativo, Asaas) — cada um implementa `PaymentGatewayInterface`; tenant ativa via `50-ecosystem-plugins`. NÃO no MVP, mas o contrato já prevê.
- [ ] **3ª camada — comissão de instrutor**: `commission_rate` + `CommissionLog` (repasse tenant/plataforma→instrutor). Não existia no eadIA — gap a modelar.
- [ ] **Portar (revisar, não copiar cego):** `Order`, `OrderItem` (polimórfico itemable: Course/SubscriptionPlan/Plugin + `item_snapshot`), `Payment` (`gateway_response` cru), enum `OrderOriginType`. Padronizar **centavos inteiros** (eadIA mistura decimal/cents — corrigir).

## Needs Review

- _(nenhuma)_

## Open Questions

- _(resolvido)_ Gateways: **Stripe no MVP** (via Cashier); demais como **plugins financeiros**
  tenant-selecionáveis (taxas variam por tamanho da escola). Ver ADR-001.
