---
domain: ecosystem-plugins
parent: ../spec.md
resource: subscriptions-billing
last-reviewed: 2026-07-28
---

# Subscriptions & Billing

## Model / Schema

```
plugin_installations     // instalação no tenant (free e pago)
plugin_activations       // entitlement por tenant

plugin_subscriptions
- id, tenant_id, plugin_id, status, next_billing_date, is_trial, trial_ends_at, metadata

plugin_billings
- id, plugin_subscription_id, amount_cents, due_date, retry_count, next_retry_at, status

plugin_grants
- id, tenant_id, plugin_id, price_override_cents, starts_at, ends_at, reason, granted_by

plugin_usage_logs
plugin_licenses

tenant_plugin_configs    // configuração canônica de instância por tenant+plugin
- id, tenant_id, plugin_id
- config                 // encrypted:array; opções e segredos
- enabled

plugin_coupons           // deferred
```

> Ledger Plataforma (`platform_orders`/`platform_payments`/`platform_payment_gateways`) vive no Financial.

## Rules

- Catálogo/default do dev vive em `Plugin`/`PluginPricing`; instância vive em `TenantPluginConfig.config` (`encrypted:array` + `$hidden`), incluindo opções e segredos. Schema declarado em código; APIs nunca retornam segredos.
- `PluginSubscription` controla status, billing e trial. Free tem bypass de cobrança e ativação imediata.
- Plugin pago gera `PlatformOrder` do Financial e `PluginSubscription`; nunca usa `Order` tenant-scoped. Free gera `PlatformOrder` $0 + instalação + activation quando entidades existirem.
- `PluginGrant` altera preço/free por janela sem tocar `PluginPricing` global.
- Billing recorrente usa retry; cron suspende/downgrade inadimplentes.
- Ativação/desativação invalida cache de features/configuração.
- Desativar mantém `TenantPluginConfig`, incluindo segredos, para reativação; purge somente por solicitação LGPD.
- Dashboard Mzrt minimiza dados ao agregar uso free/pago.
- Rate-limit por tier permanece pendente.

## Endpoints

| Método | Path | Descrição | Acesso |
|--------|------|-----------|--------|
| POST | `/api/v1/ecosystem/marketplace/subscriptions` | Assinar plugin | tenant_admin |
| DELETE | `/api/v1/ecosystem/marketplace/subscriptions/{id}` | Desativar plugin | tenant_admin |
| GET | `/api/v1/ecosystem/admin/subscriptions` | Dashboard global | developer |
| POST | `/api/v1/ecosystem/admin/grants` | Comp por tenant | developer |

## Permissions

`tenant_admin` assina; `developer` vê dashboard global. Permissions efetivas seguem entitlement.

## Notes

- Suspensão/downgrade é assíncrona por Cron.
- `TenantPluginConfig` é retido no desativar para evitar reconfiguração; purge segue LGPD.
