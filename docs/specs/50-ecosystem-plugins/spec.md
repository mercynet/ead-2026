---
domain: ecosystem-plugins
maturity: draft
last-reviewed: 2026-06-10
owners: [paulo]
related:
  - ../00-architecture/rbac.md
  - ../00-architecture/performance-scalability.md
  - ../00-architecture/api-conventions.md
  - subspecs/marketplace.md
  - subspecs/subscriptions-billing.md
---

# Ecosystem & Plugins

## Intent / Why

Modelo de negócio SaaS sobre a plataforma. A Landlord (master/developer) vende módulos opcionais
(plugins) à base multi-tenant como assinaturas recorrentes. O objetivo é monetizar features além
do core e permitir que cada tenant componha sua plataforma — ativando só o que precisa (forum,
gamificação, mídia avançada, analytics, gateways extras) — com billing automático e ativação
dinâmica de capabilities.

## Overview

Rastreia a vitrine (marketplace), as cobranças dos tenants ao master e o status das assinaturas
dinâmicas. Padrões transversais em
[`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md). Cron de billing,
rate-limit por tier e invalidação de cache de features em
[`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md).
Semântica de permission de plugin em
[`../00-architecture/rbac.md`](../00-architecture/rbac.md) §3.

Recursos detalhados nas subspecs:

- [`subspecs/marketplace.md`](subspecs/marketplace.md) — catálogo de plugins (entidades, vitrine).
- [`subspecs/subscriptions-billing.md`](subspecs/subscriptions-billing.md) — consumo, assinatura, billing recorrente.

## Entities

| Model | Papel |
|-------|-------|
| `Plugin` | Raiz de marketplace (name, slug, descrições, logo, screenshots). |
| `PluginCategory` / `PluginSubgroup` | Agrupamento (Pagamentos, Mídia, Analytics, Pedagógico). |
| `PluginVersion` | Versões + changelog. |
| `PluginPricing` | Preços recorrentes/avulsos (tier: free, basic, premium). |
| `PluginFeature` | Lista de capabilities para a vitrine. |
| `PluginInstallation` / `PluginActivation` | Instalação e histórico de ativação por tenant. |
| `PluginSubscription` | Assinatura SaaS (status, `next_billing_date`, trial). |
| `PluginBilling` | Extrato recorrente; lógica de retry (`retry_count`, `next_retry_at`). |
| `PluginUsageLog` / `PluginLicense` / `PluginSetting` | Quota, licença e config por tenant. |
| `TenantIntegration` | Tokens externos do tenant (`encrypted:json`), injetados via evento. |
| `PluginCoupon` | Cupom fixo/percentual para a cobrança SaaS. |

## Business Rules

- **First-party only:** sem upload de terceiros. Plugins residem em `app/Plugins/` e são
  desenvolvidos só pela master.
- **Billing recorrente:** diferente do Financial (compra spot), usa `PluginSubscription` +
  `PluginBilling` com Cron/Scheduler. Cobrança master→tenant em gateway master (ex.: Stripe
  Subscriptions); o tenant opera o próprio carrinho via plugin Cart.
- **Ativação dinâmica:** perda de validade financeira ou desinstalação emite evento que desliga
  as features daquele tenant (e invalida o cache de features — ver performance-scalability.md).
- **Landlord vs. Tenant:** `developer` vê o catálogo completo e edita preços; `tenant_admin` vê a
  vitrine, adere (pagamento ou ativação de free) e usa. Tenant pode desabilitar plugins free
  (ex.: desligar o Cart default).

## Domain Boundaries

- **Emite:** eventos de ativação/expiração de plugin → invalidam cache de features do tenant;
  registro de campos de auth do tenant via evento (`TenantIntegration`).
- **Consome:** intenção de assinatura mapeia um `Order` do Financial (`origin_type: Subscription`).

## Authorization

`developer` (landlord) gerencia catálogo/preços; `tenant_admin` consome a vitrine. Permissions de
cada plugin (`<plugin>.*`) são concedidas ao tenant via `PluginSubscription`. Ver
[`../00-architecture/rbac.md`](../00-architecture/rbac.md) §3.

## Events

- `PluginActivated` / `PluginDeactivated` — liga/desliga features e invalida cache do tenant.
- `PluginSubscriptionSuspended` — downgrade por inadimplência (via Cron).

## Quick Reference

| Recurso | Endpoint | Acesso |
|---------|----------|--------|
| Vitrine de plugins | `GET /api/v1/ecosystem/marketplace/plugins` | tenant_admin |
| Store page do plugin | `GET /api/v1/ecosystem/marketplace/plugins/{slug}` | tenant_admin |
| Assinar plugin | `POST /api/v1/ecosystem/marketplace/subscriptions` | tenant_admin |
| Cadastrar plugin | `POST /api/v1/ecosystem/admin/plugins` | developer |
| Liberar/depreciar plugin | `PATCH /api/v1/ecosystem/admin/plugins/{id}` | developer |
| Dashboard de assinaturas | `GET /api/v1/ecosystem/admin/subscriptions` | developer |
