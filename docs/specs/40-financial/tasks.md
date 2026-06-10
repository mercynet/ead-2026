---
domain: financial
last-updated: 2026-06-10
---

# Tasks — Financial

Domínio **não iniciado**. Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model).
Critério de aceite = teste.

## Done

- _(nenhuma)_

## In Progress

- _(nenhuma)_

## Pending

- [ ] Models + migrations: `Order`, `OrderItem`, `PriceHistory`, `Payment`, `TenantPaymentGateway`.
- [ ] `POST /financial/checkout` (calcula preço no servidor, gera Order + sessão do gateway).
- [ ] `GET /financial/orders` e `GET /financial/orders/{id}`.
- [ ] `POST /financial/webhooks/gateway/{gateway_slug}` (rota cega) + `ProcessPaymentWebhookJob`.
- [ ] `OrderPaidEvent` + listener no Learning (`EnrollService`).
- [ ] Registro financeiro espelho para matrículas gratuitas (auditoria/LTV).
- [ ] Tradução PT-BR de exceções de gateway.

## Needs Review

- _(nenhuma)_

## Open Questions

- Quais gateways no MVP (Stripe, MercadoPago, Pagar.me)?
