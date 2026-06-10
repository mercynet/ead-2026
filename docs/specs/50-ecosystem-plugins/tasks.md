---
domain: ecosystem-plugins
last-updated: 2026-06-10
---

# Tasks — Ecosystem & Plugins

Domínio **não iniciado**. Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model).
Critério de aceite = teste.

## Done

- _(nenhuma)_

## In Progress

- _(nenhuma)_

## Pending

### Catálogo (marketplace)
- [ ] Models + migrations: `Plugin`, `PluginCategory`, `PluginSubgroup`, `PluginVersion`, `PluginPricing`, `PluginFeature`, `PluginPermission`.
- [ ] `GET /ecosystem/marketplace/plugins` (vitrine com clusters + filtros).
- [ ] `GET /ecosystem/marketplace/plugins/{slug}` (store page).
- [ ] `POST /ecosystem/admin/plugins` (developer).
- [ ] `PATCH /ecosystem/admin/plugins/{id}` (developer).

### Consumo / billing
- [ ] Models + migrations: `PluginInstallation`, `PluginActivation`, `PluginSubscription`, `PluginBilling`, `PluginUsageLog`, `PluginLicense`, `PluginSetting`, `PluginCoupon`.
- [ ] `POST /ecosystem/marketplace/subscriptions` (free bypass; pago → Order Financial).
- [ ] `GET /ecosystem/admin/subscriptions` (dashboard developer).
- [ ] Cron `SuspendOverduePluginSubscriptionsAction`.
- [ ] Invalidação de cache de features por tenant ao ativar/desativar plugin.
- [ ] Rate-limit dinâmico por tier de subscription.
- [ ] Injeção event-driven de `TenantIntegration` (credenciais `encrypted:json`).

### Estágio de implementação dos plugins first-party
- [ ] Stripe (funcional), PixPayments (parcial).
- [ ] Cart (funcional, free default), DiscountCoupons / Subscriptions / Affiliates (estrutura).
- [ ] Comments / Community / CourseReviews / CustomCertificates (estrutura).
- [ ] EmailMarketing (estrutura), SalesIntelligence (parcial), PerformanceReportsEnterprise (vazio).
- [ ] GamificationRewards (vazio).

## Needs Review

- _(nenhuma)_

## Open Questions

- Qual gateway master para a cobrança SaaS (Stripe Subscriptions?).
- Modelo de quota/usage tracking por tier.
