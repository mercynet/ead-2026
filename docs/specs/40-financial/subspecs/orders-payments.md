---
domain: financial
parent: ../spec.md
resource: orders-payments
last-reviewed: 2026-06-10
---

# Orders & Payments

## Model / Schema

```
orders
- id, tenant_id, user_id
- order_number
- subtotal, taxes        // centavos
- origin_type            // Direct | Cart | Subscription | Renewal
- status                 // pending | paid | failed | cancelled | refunded
- metadata               // JSON

order_items
- id, order_id
- itemable_type, itemable_id   // morph: Course | Plano | Plugin
- item_snapshot          // JSON (nome/preço congelados)
- price_cents

price_history
- id, course_id, old_price_cents, new_price_cents, changed_at

payments
- id, order_id
- gateway_response       // payload cru do gateway
- external_id
- status                 // pending | completed | failed

tenant_payment_gateways
- id, tenant_id
- gateway                // stripe | mercadopago | pagarme | ...
- credentials            // encrypted:json
- is_active
```

## Rules

- **Valores em centavos** (`price_cents`); preços calculados no servidor.
- `OrderItem` é polimórfico (`itemable`) e guarda `item_snapshot` para preservar histórico mesmo
  se o produto mudar de nome/preço.
- `TenantPaymentGateway.credentials` sempre `encrypted:json`. Ver
  [`../../00-architecture/security-privacy-lgpd.md`](../../00-architecture/security-privacy-lgpd.md).
- **Checkout** trata 1 item direto ou N itens (via plugin Cart); aplica cupons (futuro), gera
  `Order` + `OrderItems`, retorna o ID da Order e chave de sessão do gateway ativo do tenant.
- Toda matrícula gera registro financeiro espelho (auditoria/LTV), mesmo gratuita.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/financial/checkout` | Submete itens, calcula preço, gera Order + sessão do gateway | autenticado |
| GET | `/api/v1/financial/orders` | Histórico financeiro do aluno | autenticado (own) |
| GET | `/api/v1/financial/orders/{id}` | Detalhe da compra | autenticado (own) |

## Permissions

`financial.orders.*` (via plugin/role). Ver
[`../../00-architecture/rbac.md`](../../00-architecture/rbac.md).

## Notes

- `Cart`/`CartItem`/`Coupon` são plugins (Cart é free e incluído por padrão). Ver
  `50-ecosystem-plugins/`.
