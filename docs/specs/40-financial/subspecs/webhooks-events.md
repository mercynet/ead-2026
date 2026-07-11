---
domain: financial
parent: ../spec.md
resource: webhooks-events
last-reviewed: 2026-06-10
---

# Webhooks & Events

## Rules

- **Rota cega de webhook:** `POST /webhooks/gateway/{gateway_slug}` recebe o ping do gateway
  sinalizando que `PAY-XXXXX` virou `paid` ou `failed`. É pública.
- O webhook **não processa inline**: enfileira um Job (`ProcessPaymentWebhookJob`) para um worker,
  que vira a `Order` de `pending` → `completed`/`paid` e dispara `OrderPaidEvent`.
- **Event-driven:** quando `OrderPaidEvent` é disparado, o domínio Learning escuta e invoca
  `EnrollService` (matrícula automática). Garante isolamento — não há código de matrícula nas
  rotas financeiras.
- Exceções de gateway são capturadas e traduzidas para PT-BR antes de qualquer resposta ao cliente.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/financial/webhooks/gateway/{gateway_slug}` | Recebe status do gateway (enfileira job) | público (rota cega) |

## Events

- `OrderPaidEvent` — emitido pelo worker; consumido por Learning (matrícula automática). Mecânica
  de fila em
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).

## Notes

- Validar assinatura/segredo do gateway no webhook antes de enfileirar.
