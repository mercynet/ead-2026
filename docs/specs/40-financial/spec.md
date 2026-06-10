---
domain: financial
maturity: draft
last-reviewed: 2026-06-10
owners: [paulo]
related:
  - ../00-architecture/api-conventions.md
  - ../00-architecture/security-privacy-lgpd.md
  - subspecs/orders-payments.md
  - subspecs/webhooks-events.md
---

# Financial

## Intent / Why

Monetização da plataforma. Transforma intenção de compra em receita auditável: gera orders,
submete a intenção ao gateway do tenant, recebe a confirmação por webhook e dispara matrícula
automática. O objetivo é desacoplar o checkout do core (gateway-agnostic) e garantir que toda
matrícula — paga ou gratuita — tenha rastro financeiro para LTV/auditoria.

## Overview

Cuida de intenções de compra (Orders), itens polimórficos (OrderItems), transações confirmadas
(Payments) e webhooks de gateway. Padrões transversais em
[`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md). Credenciais de
gateway em
[`../00-architecture/security-privacy-lgpd.md`](../00-architecture/security-privacy-lgpd.md).

Recursos detalhados nas subspecs:

- [`subspecs/orders-payments.md`](subspecs/orders-payments.md) — orders, items, payments, gateways.
- [`subspecs/webhooks-events.md`](subspecs/webhooks-events.md) — webhook de gateway e `OrderPaidEvent`.

## Entities

| Model | Invariantes |
|-------|-------------|
| `Order` | `order_number`; status `pending|paid|failed|cancelled|refunded`; `origin_type` (Direct/Cart/Subscription/Renewal). |
| `OrderItem` | Polimórfico (`itemable_type/id` → Curso/Plano/Plugin); guarda `item_snapshot` para histórico. |
| `PriceHistory` | Auditoria de alterações de preço. |
| `Payment` | Atrelado a `Order`; `gateway_response` cru, `external_id`; status `pending|completed|failed`. |
| `TenantPaymentGateway` | Credenciais cifradas por tenant (`encrypted:json`); múltiplos gateways. |
| `Cart` / `CartItem` (plugin) | Carrinho por usuário; itens polimórficos. |
| `Coupon` (plugin) | Desconto percentual/fixo, validade e limite de uso. |

## Business Rules

- **Gateway-agnostic:** core não acopla lógica de checkout. Gera `Order`, submete intenção via
  Factory, recebe webhooks. Exceções de gateway são capturadas e **traduzidas para PT-BR**.
- **Valores em centavos:** `price_cents` (inteiros) para evitar floating math. Ver
  [`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md).
- **Checkout desacoplado:** backend envia intenção e devolve `client_secret`/URL de redirect; o
  SPA executa em iframe/redirect.
- **Auditoria de transações:** toda matrícula, mesmo gratuita, gera registro financeiro espelho
  (ex.: método "Automático/Gratuito") para LTV/auditoria. Manter polimorfismo (`itemable`) sempre
  válido para evitar `RelationNotFoundException` em orders antigas.

## Domain Boundaries

- **Emite:** `OrderPaidEvent` — escutado pelo Learning para matrícula automática (`EnrollService`).
  Mantém código de matrícula fora das rotas financeiras.
- **Consome:** intenções de assinatura do Ecosystem (`origin_type: Subscription`).

## Authorization

Permissions financeiras (`financial.*`) chegam principalmente via plugin/role. Ver
[`../00-architecture/rbac.md`](../00-architecture/rbac.md).

## Events

- `OrderPaidEvent` — disparado pelo worker ao virar `Order` de pending → paid (via webhook).

## Quick Reference

| Recurso | Endpoint | Permission |
|---------|----------|------------|
| Checkout | `POST /api/v1/financial/checkout` | autenticado |
| Listar orders | `GET /api/v1/financial/orders` | autenticado (own) |
| Ver order | `GET /api/v1/financial/orders/{id}` | autenticado (own) |
| Webhook de gateway | `POST /api/v1/financial/webhooks/gateway/{gateway_slug}` | público (rota cega) |
