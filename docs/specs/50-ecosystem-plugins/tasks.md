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

### Reuso eadIA (ver ADR-001)
- [ ] **Plugins financeiros = gateways de pagamento**: cada gateway (Mercado Pago, PagSeguro, PIX-nativo, Asaas) é um plugin que implementa `PaymentGatewayInterface` (do Financial). Tenant ativa conforme taxa/tamanho. Stripe fica no core do Financial; demais entram aqui.
- [ ] **Modelo de dados pronto no eadIA** (migrations `2025_11_30_*`): `plugin_installations/activations/purchases/licenses/audit_financials/settings/attachments/logs`. Portar **revisando** (não copiar cego): `tenant_id` deve ser FK inteiro (eadIA usa string em alguns), centavos inteiros, índices.
- [ ] `AbstractPlugin` + `PluginManager`: adaptar discovery de filesystem (`plugin.json`) → **DB** (plugins são só nossos, registrados no banco).

## Needs Review

- _(nenhuma)_

## Open Questions

- _(resolvido)_ Cobrança SaaS (Mzrt→tenant) e gateways: ver ADR-001. Gateways são plugins
  financeiros; cobrança da assinatura de plugin via camada Mzrt→tenant.
- Modelo de quota/usage tracking por tier.
