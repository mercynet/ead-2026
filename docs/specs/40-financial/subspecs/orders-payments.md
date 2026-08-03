---
domain: financial
parent: ../spec.md
resource: orders-payments
last-reviewed: 2026-07-29
---

# Orders & Payments

## Model / Schema

### Plano Venda (tenant → aluno)

```
orders
- id, tenant_id, user_id, order_number
- subtotal, taxes        // centavos
- origin_type            // direct | cart | subscription | renewal
- status                 // pending | paid | failed | cancelled | refunded
- metadata               // JSON

order_items
- id, order_id, itemable_type, itemable_id
- item_snapshot, price_cents

payments
- id, order_id, gateway_slug, confirmation_mode, gateway_response, external_id, status
- tenant_plugin_config_id, gateway_configuration_version, psp_idempotency_key
- charge_state, charge_claim_token, charge_claimed_at

tenant_plugin_configs    // store canônico no Ecosystem, config por tenant+plugin
- id, tenant_id, plugin_id, configuration_version
- config                 // encrypted:array; inclui credenciais/configuração do gateway
- enabled                // no máximo um gateway habilitado por tenant, por troca atômica

tenant_plugin_config_revisions
- tenant_plugin_config_id, configuration_version, config
- snapshot imutável encrypted:array, tenant-bound, para resolução histórica exata
```

### Plano Plataforma (Mozart → tenant) — ledger irmão, escopo global

```
platform_orders
- id, tenant_id, order_number, subtotal, taxes
- origin_type, status

platform_order_items
- id, platform_order_id, itemable_type, itemable_id, item_snapshot, price_cents

platform_payments
- id, platform_order_id, gateway_response, external_id, status

platform_payment_gateways
- id, gateway, credentials, is_active, is_default
```

> Ledger separado: pagador, dono do gateway e escopo divergem. `plugin_purchases` legado é descartado.

## Costura de extensão (`itemable`)

| Vendável | Plano | `itemable_type` | Quem dirige | `origin_type` |
|----------|-------|-----------------|-------------|---------------|
| Curso avulso | Venda | `Course` | Learning | `Direct` |
| Plano de assinatura | Venda | `Plan` | plugin Subscriptions | `Subscription` |
| Bundle de carrinho | Venda | itens acima | plugin Cart | `Cart` |
| Assinatura de plugin | Plataforma | `Plugin` / `PluginPricing` | Ecosystem | `PluginSubscription` |

- Financial não importa model de plugin; registra tipo no morph map. Novo vendável não requer migration de `orders`.
- Cart é container: gera uma `Order` com N `order_items`.
- Só `Course`, `Plan`, `Plugin` e `PluginPricing` são modelados; não pré-modelar futuro.

## Rules

- Valores em centavos; preço calculado no servidor.
- `OrderItem` preserva `price_cents` e `item_snapshot` como preço/snapshot contratado imutável;
  não consulta histórico de preço genérico do Financial.
- Configuração/credenciais do gateway vivem em `TenantPluginConfig.config` (`encrypted:array` + `$hidden`); `TenantPluginConfigRevision` guarda snapshots imutáveis cifrados tenant-bound. API aceita escrita de segredo, mas nunca o devolve.
- `gateway_slug` e `confirmation_mode` (`manual|automatic`) são classificação interna autoritativa do pagamento; `gateway_response` permanece payload cru privado e nunca decide elegibilidade.
- `POST /api/v1/student/checkout` aceita somente `{course_id}` e header UUID `Idempotency-Key`; preço, snapshot, origem `direct` e gateway são autoridade do servidor.
- Mesmo idempotency key + mesmo curso com `Payment` `resolved` repete resposta sem nova cobrança; chave reutilizada para outro curso retorna `idempotency_conflict`. Pedido `pending|paid` no mesmo ciclo retorna `checkout_already_exists`.
- Claim de cobrança é transacional: `created` → `processing` com token e timestamp. `processing` recente retorna `checkout_in_progress`; claim vencido vira `unknown` sem takeover; `unknown` exige reconciliação e nunca recarga inline.
- Resultado normalizado `pending|paid|failed` decide o ledger; `charge_state`, não `gateway_response`, é fonte de verdade de execução. Persistência exige token de claim e `tenant_plugin_config_id`, `gateway_configuration_version`, `gateway_slug` e `psp_idempotency_key` exatos. Payload cru permanece privado.
- Retry resolve credenciais históricas exatas pelo snapshot cifrado persistido, inclusive após rotação ou desabilitação da configuração atual. `psp_idempotency_key` é gerada e controlada pelo servidor.
- Transição para pago grava outbox durável dentro da transação financeira. Publicação imediata ocorre após commit em best-effort; drainer agendado recupera entregas pendentes ao menos uma vez.
- Concessão manual zero-consideration (`EnrollmentCreatedEvent` com `source=manual` e
  `billing_type=null`) cria espelho atômico, independente do preço de catálogo: `Order`
  `paid/direct`, valores zero, `source_key=learning:enrollment:{id}`, `order_number=ENR-{id}` e
  UUIDv5 determinístico; um item `course` com preço de catálogo apenas no snapshot e `Payment`
  `completed/free/automatic/resolved`. Replay só aceita agregado íntegro e compatível, preserva uma
  única order/item/payment e não emite `OrderPaidEvent`. Pendente de aprovação também espelha zero
  e preserva status no metadata. Matrícula manual externa não cria este espelho; reconciliação e
  espelho pós-aprovação seguem pendentes.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/student/checkout` | Calcula preço, gera Order + sessão do gateway | Student |
| GET | `/api/v1/student/orders` | Histórico financeiro | Student (own) |
| GET | `/api/v1/student/orders/{id}` | Detalhe | Student (own) |
| POST | `/api/v1/admin/orders/{id}/confirm-manual-payment` | Confirma pagamento offline e registra `OrderPaidEvent` no outbox | Admin |
| GET | `/api/v1/admin/payment-gateways` | Lista gateways e publica schema de configuração | `financial.payment-gateways.list` |
| PUT | `/api/v1/admin/payment-gateways/{plugin}` | Valida/persiste config; habilitação exclusiva atômica, segredos redigidos | `financial.payment-gateways.update` |

> `GET` + `PUT` são exceção inseparável ao limite de ≤1 endpoint por slice: `GET` publica schema consumido por `PUT` para validar e persistir configuração.

## Permissions

`financial.orders.*` (via plugin/role) e `financial.payment-gateways.list` / `financial.payment-gateways.update`. Ecosystem é dono técnico do store/endpoints; Financial fornece contratos, schemas e resolução.

## Notes

- Cart, CartItem e Coupon são plugins; Cart é free e incluído por padrão.
