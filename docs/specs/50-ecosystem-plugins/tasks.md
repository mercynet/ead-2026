---
domain: ecosystem-plugins
last-updated: 2026-07-12
---

# Tasks — Ecosystem & Plugins

Domínio **em fundação** (catálogo `Plugin`). Modelo de plugin decidido em **ADR-005** (capability do
core gated por flag + config por tenant; gateway é plugin). Cada task = 1 slice fino
(≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

- [x] **Módulo Ecosystem + catálogo `Plugin` normalizado (ADR-005).** Model/migration/factory de
  `Plugin` (âmbito Mzrt, global): `slug`/`capability_key` únicos, `kind` (feature|gateway), `status`,
  `visibility` (public|internal), `tier`, `is_curated`, `directory_name` (nullable, reservado a código
  futuro em `app/Plugins/`), descrições/logo/locale/URLs. Scopes `published`/`visibleToTenants`;
  helpers `isGateway`/`gatewaySlug` (gateway casa com adaptador do `PaymentGatewayManager`). Provider
  registrado em `bootstrap/providers.php`. **Scaffold pré-spec descartado** (4 migrations
  `2026_02_21_1830xx`: `plugins` PK=`name` + design filesystem + billing no plugin — órfão, contra
  ADR-003). 4 testes (`PluginCatalogTest`). Categorias (pivô `category_plugin`),
  `PluginVersion`/`PluginPricing`/`PluginFeature` seguem em slices próprios.
- [x] **`PluginActivation` (entitlement por tenant).** Model/migration/factory: `tenant_id`+`plugin_id`
  (único), `status` (active|inactive|suspended), `activated_at`/`deactivated_at`/`activated_by`, scope
  `active`, `isActive()`. Gate de disponibilidade da capability. 3 testes (unicidade, active scope,
  isolamento por tenant).
- [x] **`TenantPluginConfig` (config de instância genérica, ADR-005).** Model/migration/factory: um por
  tenant+plugin (único), `config` **`encrypted:array` + `$hidden`** (segredos fora de serialização —
  fecha o finding Alta do review), `enabled`, `credentials()`/`get()`. 3 testes (encriptação em repouso
  + `$hidden`, unicidade, scope enabled). Falta: contrato de schema em código + validação na persistência.
- [x] **Fronteira `Contracts\TenantGatewayProvider` (+ DTO `ActiveGateway`) + impl `EcosystemTenantGatewayProvider`.**
  Resolve o gateway ativo/configurado do tenant (join `Plugin` gateway+published × `PluginActivation` ativa
  × `TenantPluginConfig` enabled) sem vazar models; consumido pelo `TenantGatewayResolver` do Financial.
  Bind no `EcosystemServiceProvider`.

## In Progress

- _(nenhuma)_

## Pending

### Catálogo (marketplace)
- [x] `Plugin` (catálogo normalizado + `capability_key` + `is_curated`) — ver Done.
- [ ] Models + migrations restantes do catálogo: `PluginVersion`, `PluginPricing`, `PluginFeature`, `PluginPermission`.
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
- [x] **`PluginActivation`** (entitlement por tenant: `status`/`activated_at`/`deactivated_at`/`activated_by`; único por tenant+plugin; scope `active`) — ver Done.
- [x] **`TenantPluginConfig` genérico** (um store por tenant+plugin; `config` blob `encrypted:array` + `$hidden`; `enabled`; `credentials()`/`get()`) — ver Done. Falta o **contrato de schema em código + validação na persistência** (slice próprio).
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

- **Modelo de plugin fixado em [ADR-005]:** capability do core gated por flag; gateway é plugin;
  config de instância = `TenantPluginConfig` genérico (blob encriptado, schema-em-código); gateway da
  plataforma dedicado (`PlatformPaymentGateway`); sem `laravel/cashier`. A `spec.md` (Entities) ainda
  lista `PluginSetting`/`TenantIntegration` e discovery filesystem — **convergir para `TenantPluginConfig`
  + capability gating** nos slices de config (código vence prosa).

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
