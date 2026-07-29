---
domain: financial
parent: ../spec.md
resource: webhooks-events
last-reviewed: 2026-07-29
---

# Webhooks & Events

## Rules

- **Rota cega de webhook:** `POST /api/v1/webhooks/gateways/{gateway_slug}` recebe o ping do gateway
  sinalizando que `PAY-XXXXX` virou `paid` ou `failed`. É pública.
- O webhook **não processa inline**: enfileira um Job (`ProcessPaymentWebhookJob`) para um worker,
  que vira a `Order` de `pending` → `completed`/`paid` e grava `OrderPaidEvent` no outbox.
- **Outbox durável:** transição financeira para pago grava `OrderPaidEvent` no outbox dentro da
  mesma transação. Após commit, publicação imediata é best-effort; drainer agendado recupera
  pendências com entrega ao menos uma vez. Learning consome o evento e invoca `EnrollService`
  (matrícula automática), sem garantia de matrícula antes da resposta de checkout.
- Exceções de gateway são capturadas e traduzidas para PT-BR antes de qualquer resposta ao cliente.
- Gateways offline (`cash`) não têm webhook: o Admin confirma a transação manualmente na superfície
  `/api/v1/admin`, com a mesma transição idempotente de Order/Payment e o mesmo `OrderPaidEvent`.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/webhooks/gateways/{gateway_slug}` | Recebe status do gateway (enfileira job) | público (rota cega) |

## Events

- `OrderPaidEvent` — registrado no outbox por transição paga e publicado após commit; consumido
  por Learning (matrícula automática). Mecânica de fila em
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).

## Notes

- Validar assinatura/segredo do gateway no webhook antes de enfileirar.
