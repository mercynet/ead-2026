---
domain: ecosystem-plugins
parent: ../spec.md
resource: subscriptions-billing
last-reviewed: 2026-06-10
---

# Subscriptions & Billing

## Model / Schema

```
plugin_installations     // registro de instalação no tenant
plugin_activations        // histórico de ativações/desativações

plugin_subscriptions
- id, tenant_id, plugin_id
- status                 // active | suspended | cancelled
- next_billing_date
- is_trial, trial_ends_at
- metadata               // limites

plugin_billings
- id, plugin_subscription_id
- amount_cents, due_date
- retry_count, next_retry_at
- status

plugin_usage_logs        // quota tracking (recursos premium)
plugin_licenses          // licenças de uso por plugin
plugin_settings          // config do plugin por tenant
plugin_coupons           // cupom fixo/percentual na cobrança SaaS

tenant_integrations
- tenant_id, integration_type
- credentials            // encrypted:json
- is_active
```

## Rules

- **Assinatura SaaS:** `PluginSubscription` (status, `next_billing_date`, trial). Plugins **free**
  têm bypass direto — ativação imediata sem onerar o cart.
- **Assinar plugin pago** mapeia um `Order` do Financial (`origin_type: Subscription`) e gera a
  `PluginSubscription` atrelada ao tenant.
- **Billing recorrente master→tenant:** `PluginBilling` com retry (`retry_count`, `next_retry_at`).
  Cron diário (`SuspendOverduePluginSubscriptionsAction`) suspende/faz downgrade de inadimplentes.
  Ver [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
- **Ativação dinâmica:** ativar/desativar plugin invalida o cache de features/config do tenant.
- **TenantIntegration** injetada via evento (ex.: plugin Vimeo registra seus campos de auth);
  credenciais `encrypted:json`.
- **Rate-limit por tier:** middleware lê o tier (`basic`/`premium`) e aloca o Rate Limiter
  (ex.: 100/h vs. 5000/h).

## Endpoints

| Método | Path | Descrição | Acesso |
|--------|------|-----------|--------|
| POST | `/api/v1/ecosystem/marketplace/subscriptions` | Assinar plugin (free = bypass; pago = gera Order) | tenant_admin |
| GET | `/api/v1/ecosystem/admin/subscriptions` | Dashboard de assinaturas de toda a base | developer |

## Permissions

`tenant_admin` assina; `developer` vê o dashboard global. Permissions efetivas concedidas ao
tenant ao assinar — ver [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §3.

## Notes

- Suspensão/downgrade é assíncrona (Cron) — ver performance-scalability.md.
