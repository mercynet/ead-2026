---
domain: ecosystem-plugins
parent: ../spec.md
resource: subscriptions-billing
last-reviewed: 2026-06-10
---

# Subscriptions & Billing

## Model / Schema

```
plugin_installations     // registro de instalação no tenant (free E pago)
plugin_activations        // histórico de ativações/desativações (free E pago)

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

plugin_grants            // comp por tenant (Mzrt): override de preço / free por janela
- id, tenant_id, plugin_id
- price_override_cents    // null = grátis
- starts_at, ends_at
- reason, granted_by      // auditável

plugin_usage_logs        // quota tracking (recursos premium)
plugin_licenses          // licenças de uso por plugin

plugin_settings          // CONFIG DE INSTÂNCIA do tenant (limites, opções NÃO-secretas)
- id, tenant_id, plugin_id, key, value_json, scope

plugin_coupons           // cupom fixo/percentual na cobrança SaaS (DEFERRED — promoção é futuro)

tenant_integrations      // SEGREDOS do tenant (chaves MP, prod/sandbox) — nunca em plugin_settings
- tenant_id, integration_type
- credentials            // encrypted:json
- is_active
```

> O ledger financeiro do plano Plataforma (`platform_orders`/`platform_payments`/
> `platform_payment_gateways`) vive no **Financial** — schema em
> [`../../40-financial/subspecs/orders-payments.md`](../../40-financial/subspecs/orders-payments.md),
> *porquê* em [`../../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md`](../../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md).

## Rules

- **Duas camadas de config:** `PluginSetting` guarda config de instância **não-secreta** (limites,
  flags); **segredos** (chaves Mercado Pago, prod/sandbox, parcelas) **só** em `TenantIntegration`
  (`encrypted:json`). Config de catálogo (default do dev) vive no `Plugin`/`PluginPricing`.
- **Assinatura SaaS:** `PluginSubscription` (status, `next_billing_date`, trial). Plugins **free**
  têm bypass de cobrança — ativação imediata.
- **Assinar plugin pago** gera um **`PlatformOrder`** do Financial (plano Plataforma, gateway do
  **Mozart**) + a `PluginSubscription` atrelada ao tenant. **Não** usa o `Order` tenant-scoped nem
  o `TenantPaymentGateway`. Free gera `PlatformOrder` de `amount_cents=0` + `PluginInstallation` +
  `PluginActivation` (Mzrt contabiliza free e pago no mesmo ledger).
- **Comp por tenant (`PluginGrant`):** Mzrt aplica preço diferente ou free por janela a um tenant
  específico, sem tocar o `PluginPricing` global. A cobrança recorrente respeita o grant vigente.
- **Billing recorrente Mozart→tenant:** `PluginBilling` com retry (`retry_count`, `next_retry_at`).
  Cron diário (`SuspendOverduePluginSubscriptionsAction`) suspende/faz downgrade de inadimplentes.
  Ver [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
- **Ativação dinâmica:** ativar/desativar plugin invalida o cache de features/config do tenant.
- **Desativar volta ao estado anterior:** features desligam; config/segredos do tenant são
  **retidos** por padrão (reativar não re-pede chaves), com purge sob solicitação LGPD (ver Notes).
- **Visibilidade Mzrt (LGPD):** dashboard developer agrega quem usa cada plugin (free e pago) via
  `PluginInstallation`/`PluginActivation` + `platform_orders`, respeitando minimização de dados.
- **TenantIntegration** injetada via evento (ex.: plugin Vimeo registra seus campos de auth);
  credenciais `encrypted:json`.
- **Rate-limit por tier:** middleware lê o tier (`basic`/`premium`) e aloca o Rate Limiter
  (ex.: 100/h vs. 5000/h).

## Endpoints

| Método | Path | Descrição | Acesso |
|--------|------|-----------|--------|
| POST | `/api/v1/ecosystem/marketplace/subscriptions` | Assinar plugin (free = bypass; pago = gera `PlatformOrder`) | tenant_admin |
| DELETE | `/api/v1/ecosystem/marketplace/subscriptions/{id}` | Desativar plugin (volta ao estado anterior) | tenant_admin |
| GET | `/api/v1/ecosystem/admin/subscriptions` | Dashboard de uso (free + pago) de toda a base | developer |
| POST | `/api/v1/ecosystem/admin/grants` | Comp por tenant (override de preço / free por janela) | developer |

## Permissions

`tenant_admin` assina; `developer` vê o dashboard global. Permissions efetivas concedidas ao
tenant ao assinar — ver [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §3.

## Notes

- Suspensão/downgrade é assíncrona (Cron) — ver performance-scalability.md.
- **Retenção no desativar:** `PluginSetting`/`TenantIntegration` do tenant são retidos para reativar
  sem re-configurar; purge só por solicitação LGPD (titular/tenant) — ver `security-privacy-lgpd.md`.
