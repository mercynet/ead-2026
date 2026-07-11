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
- [ ] Models + migrations: `Plugin` (+`is_curated`), `PluginVersion`, `PluginPricing`, `PluginFeature`, `PluginPermission`.
- [ ] Categorias: **reusar tabela `categories`** (`is_system`) + pivô `category_plugin` (igual cursos, ADR-002). **Sem** `plugin_categories`/`PluginSubgroup`.
- [ ] Models + migrations: `PluginRating` + `PluginRatingAggregate` (rating de plugin → alimenta Featured).
- [ ] `GET /ecosystem/marketplace/plugins` (vitrine: categorias recursivas + filtros Instalados/Disponíveis/Free/Premium/Novos/Featured/Recomendados/Escolhidos-por-mim).
- [ ] `GET /ecosystem/marketplace/plugins/{slug}` (store page).
- [ ] `POST /ecosystem/marketplace/plugins/{slug}/ratings` (tenant avalia).
- [ ] `POST /ecosystem/admin/plugins` (developer cria).
- [ ] `PATCH /ecosystem/admin/plugins/{id}` (developer: liberar/desativar/depreciar + curadoria).

### Consumo / billing
- [ ] Models + migrations: `PluginInstallation`, `PluginActivation`, `PluginSubscription`, `PluginBilling`, `PluginGrant` (comp por tenant), `PluginUsageLog`, `PluginLicense`, `PluginSetting`, `PluginCoupon` (deferred).
- [ ] **Ledger do plano Plataforma** (no Financial): `PlatformOrder`/`PlatformOrderItem`/`PlatformPayment`/`PlatformPaymentGateway`. **Descartar `plugin_purchases` legado.**
- [ ] `POST /ecosystem/marketplace/subscriptions` (free → `PlatformOrder` $0 + activation; pago → `PlatformOrder` no gateway do Mozart).
- [ ] `DELETE /ecosystem/marketplace/subscriptions/{id}` (desativar; retém config/segredos por padrão).
- [ ] `GET /ecosystem/admin/subscriptions` (dashboard developer: uso free + pago, LGPD).
- [ ] `POST /ecosystem/admin/grants` (comp por tenant: override de preço / free por janela).
- [ ] Config 2 camadas: `PluginSetting` (não-secreto) × `TenantIntegration` (`encrypted:json`, segredos).
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
- _(resolvido 2026-06-13)_ Overlap `plugin_purchases × orders`: **dois ledgers irmãos** —
  `PlatformOrder*` (Mzrt→tenant, gateway Mozart) ≠ `Order*` (tenant→aluno). `plugin_purchases`
  descartado. Ver **ADR-003** + Financial `orders-payments.md`.
- _(resolvido 2026-06-13)_ Categorias = **tabela `categories` compartilhada** (`is_system`) + pivô
  `category_plugin`, igual cursos (**ADR-002**); sem tabela própria.
- _(resolvido 2026-06-13)_ Comp por tenant = `PluginGrant` (override/free por janela, auditável).
- Modelo de quota/usage tracking por tier.
