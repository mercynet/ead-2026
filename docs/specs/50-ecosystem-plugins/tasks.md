---
domain: ecosystem-plugins
last-updated: 2026-07-29
---

# Tasks — Ecosystem & Plugins

Domínio em fundação. ADR-005: capability gated por flag + config por tenant; gateway é plugin. Cada task = 1 slice fino; aceite = teste.

## Done

- [x] Módulo Ecosystem + catálogo `Plugin` normalizado: `slug`/`capability_key` únicos, kind, status, visibilidade, tier, curadoria e metadados; provider e testes.
- [x] `PluginActivation`: entitlement por tenant, unicidade tenant+plugin, status, datas, ator, scope `active` e testes.
- [x] `TenantPluginConfig`: store por tenant+plugin, `config` `encrypted:array` + `$hidden`, `enabled`, helpers e testes.
- [x] Identidade histórica de gateway: `configuration_version` e revisões imutáveis cifradas de `TenantPluginConfig`, consultáveis por identidade exata e vinculadas ao tenant. Seleção ativa atual permanece separada.
- [x] `Contracts\TenantGatewayProvider` + `ActiveGateway` + `EcosystemTenantGatewayProvider`, consumido por Financial sem vazar models.
- [x] Preset `cash`: provisionamento idempotente de catálogo, activation e config habilitada, preservando escolha/configuração do Admin.
- [x] **`MZRT-SKELETON-CASH`** Provisionamento obrigatório de `cash` por contrato neutro em
  `Shared`, executado sincronamente dentro da transação de `ProvisionTenantAction`; Core não importa
  internals de Ecosystem e falha do participante reverte tenant, admin, role e artefatos do preset.
  `Journey: MZRT-SKELETON | Area: mzrt | Depends on: MZRT-SKELETON-CREATE`
- [x] **`MZRT-SKELETON-ENTITLEMENTS`** `GET /api/v1/mzrt/tenants/{tenant}/entitlements` com
  `ecosystem.entitlements.list`, exclusivo de `developer` em `area.guard:mzrt`, sem contexto/header
  de tenant; lista cursor-paginada expõe somente `capability` e `status` de cada activation.
  `Journey: MZRT-SKELETON | Area: mzrt | Depends on: MZRT-SKELETON-CASH`
- [x] **Admin gateways do tenant (dono canônico).** `GatewayConfigurationRegistry` em `Financial\Contracts` publica schema no `GET /api/v1/admin/payment-gateways`; `PUT /api/v1/admin/payment-gateways/{plugin}` valida antes de persistir `TenantPluginConfig`, trata adaptador indisponível, mantém no máximo um gateway habilitado por tenant por troca atômica e redige segredos nas respostas. Testes e gates cobrem slice. Par `GET`+`PUT` é exceção inseparável ao ≤1 endpoint: GET publica schema consumido por PUT.

## In Progress

- _(nenhuma)_

## Pending

### MZRT-SKELETON

- _(nenhuma)_

### Catálogo (marketplace)
- [ ] Models + migrations: `PluginVersion`, `PluginPricing`, `PluginFeature`, `PluginPermission`.
- [ ] Categorias: reusar `categories` (`is_system`) + pivô `category_plugin`; sem tabela própria.
- [ ] `PluginRating` + `PluginRatingAggregate`.
- [ ] `GET /ecosystem/marketplace/plugins`.
- [ ] `GET /ecosystem/marketplace/plugins/{slug}`.
- [ ] `POST /ecosystem/marketplace/plugins/{slug}/ratings`.
- [ ] `POST /ecosystem/admin/plugins`.
- [ ] `PATCH /ecosystem/admin/plugins/{id}`.

### Consumo / billing
- [ ] Models + migrations: `PluginInstallation`, `PluginSubscription`, `PluginBilling`, `PluginGrant`, `PluginUsageLog`, `PluginLicense`, `PluginCoupon` (deferred).
- [ ] Ledger do plano Plataforma no Financial: `PlatformOrder`/`PlatformOrderItem`/`PlatformPayment`/`PlatformPaymentGateway`; descartar `plugin_purchases`.
- [ ] Retropreencher `PluginInstallation` + `PlatformOrder` $0 para presets `cash` anteriores.
- [ ] `POST /ecosystem/marketplace/subscriptions`.
- [ ] `DELETE /ecosystem/marketplace/subscriptions/{id}`; retém config/segredos por padrão.
- [ ] `GET /ecosystem/admin/subscriptions`.
- [ ] `POST /ecosystem/admin/grants`.
- [ ] Cron `SuspendOverduePluginSubscriptionsAction`.
- [ ] Invalidação de cache de features ao ativar/desativar.
- [ ] Rate-limit dinâmico por tier.

### Plugins first-party
- [ ] Stripe, PixPayments e demais adaptadores conforme prioridade.
- [ ] Cart, DiscountCoupons, Subscriptions e Affiliates.
- [ ] Comments, Community, CourseReviews, CustomCertificates, EmailMarketing, SalesIntelligence, PerformanceReportsEnterprise e GamificationRewards.

### Reuso eadIA
- [ ] Plugins financeiros: gateways implementam `PaymentGatewayInterface`; `cash` é preset free de confirmação manual.
- [ ] Revisar migrations eadIA: centavos inteiros, FKs e índices corretos.
- [ ] Adaptar `AbstractPlugin` + `PluginManager` de filesystem para catálogo DB.

## Open Questions

- _(resolvido)_ Cobrança SaaS Mzrt→tenant usa camada própria.
- _(resolvido)_ Dois ledgers irmãos: `PlatformOrder*` ≠ `Order*`; `plugin_purchases` descartado.
- _(resolvido)_ Categorias reutilizam `categories` + `category_plugin`.
- _(resolvido)_ Comp por tenant usa `PluginGrant`.
- Modelo de quota/usage tracking por tier.
