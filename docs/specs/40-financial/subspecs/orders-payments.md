---
domain: financial
parent: ../spec.md
resource: orders-payments
last-reviewed: 2026-07-28
---

# Orders & Payments

## Model / Schema

### Plano Venda (tenant → aluno)

```
orders
- id, tenant_id, user_id
- order_number
- subtotal, taxes        // centavos
- origin_type            // Direct | Cart | Subscription (aluno→plano do tenant) | Renewal
- status                 // pending | paid | failed | cancelled | refunded
- metadata               // JSON

order_items
- id, order_id
- itemable_type, itemable_id   // morph: Course | Plano (do tenant)
- item_snapshot          // JSON (nome/preço congelados)
- price_cents

price_history
- id, priceable_type, priceable_id, old_price_cents, new_price_cents, changed_at  // polimórfico

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

### Plano Plataforma (Mozart → tenant) — ledger irmão, escopo global

```
platform_orders
- id, tenant_id          // tenant = PAGADOR (não escopo de isolamento; é global da master)
- order_number
- subtotal, taxes        // centavos
- origin_type            // PluginSubscription | Renewal
- status                 // pending | paid | failed | cancelled | refunded

platform_order_items
- id, platform_order_id
- itemable_type, itemable_id   // morph: Plugin | PluginPricing
- item_snapshot
- price_cents            // 0 quando free (registro espelho)

platform_payments
- id, platform_order_id
- gateway_response, external_id, status

platform_payment_gateways    // formas de pagamento DO MOZART (não do tenant)
- id, gateway, credentials   // encrypted:json
- is_active, is_default
```

> Por que tabela separada e não `origin_type` no `orders`: pagador (tenant vs aluno), gateway dono
> (Mozart vs tenant), escopo (global vs `tenant_id`) e LGPD divergem. Misturar exigiria query-scope
> condicional e quebraria o `area.guard` (Mzrt vs Admin). Mata o `plugin_purchases` legado.

## Costura de extensão (`itemable`) — ler antes de adicionar um vendável

O que cada plano vende é uma **morph** (`itemable_type/id`), **não** uma FK fixa. Isso é o ponto de
extensão e a fronteira de módulo de uma vez só:

| Vendável (agora) | Plano | `itemable_type` | Quem dirige | `origin_type` |
|------------------|-------|-----------------|-------------|---------------|
| Curso avulso | Venda | `Course` | core Learning | `Direct` |
| Plano de assinatura | Venda | `Plan` | **plugin Subscriptions** | `Subscription` (aluno→plano do tenant) |
| Bundle de carrinho | Venda | (N itens acima) | **plugin Cart** | `Cart` |
| Assinatura de plugin | Plataforma | `Plugin` / `PluginPricing` | Ecosystem | `PluginSubscription` |

- **Morph map por string** — Financial **não importa** o model do plugin; registra o tipo no morph
  map. `ModuleBoundaryTest` preservado. **Novo vendável = nova entrada no morph map (+ model se
  preciso), zero migration de `orders`.**
- **Cart não é `itemable`** — é container: gera **1 Order com N `order_items`** (`origin_type=Cart`).
- **YAGNI:** só `Course`, `Plan`, `Plugin`, `PluginPricing` são modelados. **Não** pré-modelar outros
  vendáveis; a morph é o espaço de futuro, não tabelas especulativas. Ver
  [`../../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md`](../../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md).
- **Invariante:** todo `itemable_type` usado existe no morph map fechado (evita
  `RelationNotFoundException` em order antiga) — coberto por teste.

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
| POST | `/api/v1/student/checkout` | Submete itens, calcula preço, gera Order + sessão do gateway | Student |
| GET | `/api/v1/student/orders` | Histórico financeiro do aluno | Student (own) |
| GET | `/api/v1/student/orders/{id}` | Detalhe da compra | Student (own) |
| POST | `/api/v1/admin/orders/{id}/confirm-manual-payment` | Confirma pagamento offline, audita ator e dispara `OrderPaidEvent` | Admin |

## Permissions

`financial.orders.*` (via plugin/role). Ver
[`../../00-architecture/rbac.md`](../../00-architecture/rbac.md).

## Notes

- `Cart`/`CartItem`/`Coupon` são plugins (Cart é free e incluído por padrão). Ver
  `50-ecosystem-plugins/`.
