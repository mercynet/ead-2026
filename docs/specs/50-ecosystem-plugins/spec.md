---
domain: ecosystem-plugins
maturity: draft
last-reviewed: 2026-07-28
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

> **Modelo de plugin fixado em [ADR-005](../00-architecture/decisions/005-plugins-capability-gated-gateway-como-plugin.md):**
> plugin = capability do core gated por flag + config por tenant (não código carregado em runtime;
> `app/Plugins/` reservado a extensão externa futura). Config de instância = **`TenantPluginConfig`
> genérico** (blob `encrypted:array`, schema declarado em código), que **supersede** o split
> `PluginSetting`×`TenantIntegration` abaixo. Gateway é plugin (`kind='gateway'`); gateway da
> plataforma é dedicado (`PlatformPaymentGateway`, no Financial). Todo tenant novo recebe o preset
> gratuito curado `cash` (`gateway.cash`) ativo e habilitado para confirmação manual; Admin pode
> ativar/configurar gateways adicionais gratuitos ou pagos. Tabela abaixo será convergida.

| Model | Papel |
|-------|-------|
| `Plugin` | Raiz de marketplace (name, slug, descrições, logo, screenshots). |
| `categories` (compartilhada) + `category_plugin` (pivô) | Plugin **reusa a tabela `categories`** (`is_system=true`, taxonomia global do Mzrt) via pivô dedicado `category_plugin` — **mesmo molde de cursos** (ADR-002), **não** tabela `plugin_categories` própria. |
| `PluginVersion` | Versões + changelog. |
| `PluginPricing` | **Config do dev**: preço fixo recorrente/avulso (tier: free, basic, premium). Promoções/janela = futuro. |
| `PluginFeature` | Lista de capabilities para a vitrine. |
| `PluginRating` / `PluginRatingAggregate` | Avaliação de plugin pelo tenant (1-5 + moderação) e rollup — alimenta **featured** e ordenação da vitrine. |
| `PluginInstallation` / `PluginActivation` | Instalação e histórico de ativação por tenant — **criados para free e pago** (Mzrt enxerga ambos). |
| `PluginSubscription` | Assinatura SaaS (status, `next_billing_date`, trial). |
| `PluginBilling` | Extrato recorrente; lógica de retry (`retry_count`, `next_retry_at`). |
| `PluginGrant` | **Comp por tenant** (Mzrt): override de preço / free por janela (`starts_at`/`ends_at`, `reason`) sem mexer no pricing global. |
| `PluginUsageLog` / `PluginLicense` | Quota e licença por tenant. |
| `PluginSetting` | **Config de instância do tenant** (limites, opções não-secretas). Segredos vão em `TenantIntegration`. |
| `TenantIntegration` | Credenciais externas do tenant (`encrypted:json`) — ex.: chaves Mercado Pago, prod/sandbox; injetadas via evento. |
| `PluginCoupon` | Cupom fixo/percentual na cobrança SaaS (**deferred** — promoção é fase futura). |

## Business Rules

- **First-party only:** sem upload de terceiros. Plugins são capabilities já presentes no core e
  registrados por **DB**, não discovery de filesystem.
- **Preset de gateway:** o provisionamento do tenant cria, se ausentes, catálogo global e entitlement
  ativo + config habilitada do `cash` (Dinheiro, `gateway.cash`, free/curado), sem sobrescrever uma
  escolha/configuração existente. O recebimento exige confirmação manual; Admin pode ativar e
  configurar gateways adicionais gratuitos ou pagos.
- **Provisionamento exclusivo do Mzrt:** apenas `developer` (área Mzrt) cria, edita, **ativa,
  desativa ou deprecia** plugins no catálogo. Ninguém mais — nem `tenant_admin`. Ver
  [`../00-architecture/areas-surfaces.md`](../00-architecture/areas-surfaces.md) §Mzrt.
- **Duas camadas de config (dev × tenant):** cada plugin (free ou pago) tem **config de catálogo**
  definida pelo dev (título, descrição, limites, pricing — `Plugin`/`PluginPricing`/`PluginSetting`
  default) e **config de instância** por tenant ao ativar (`PluginSetting` do tenant). **Segredos
  do tenant** (chaves Mercado Pago, prod/sandbox, parcelas) **nunca** em `PluginSetting` em claro —
  vão em `TenantIntegration` (`encrypted:json`).
- **Free → associação ao tenant:** ativar um free faz **bypass de cobrança** mas **gera
  `PluginInstallation` + `PluginActivation`** (e um registro financeiro espelho de `amount_cents=0`
  no plano Plataforma — ver §"Billing — onde a venda de plugin entra") para o Mzrt contabilizar quem usa free.
  Desativar volta ao estado anterior; retenção de config no desativar segue LGPD (ver Notes).
  Exceção transitória explícita: o preset de onboarding `cash` já cria `PluginActivation` +
  `TenantPluginConfig`; o espelho `PluginInstallation`/`PlatformOrder` será retropreenchido quando
  essas entidades do marketplace existirem, sem bloquear o primeiro tenant.
- **Rating de plugin:** tenant avalia plugin que usa (`PluginRating`, 1-5 + moderação); o rollup
  (`PluginRatingAggregate`) alimenta o filtro **Featured** (stat de uso + rating) da vitrine.
- **Comp por tenant:** Mzrt pode dar preço diferente ou **free por um período** a um tenant
  específico via `PluginGrant` (janela + `reason`, auditável), sem alterar o pricing global.
- **Billing recorrente (plano Plataforma):** Mzrt→tenant usa `PluginSubscription` + `PluginBilling`
  com Cron/Scheduler, liquidado no **ledger próprio do Mzrt** (`platform_orders` + gateway do
  Mozart — ver §"Billing — onde a venda de plugin entra" e Financial). **Não** reusa o `orders` do tenant.
- **Ativação dinâmica:** perda de validade financeira ou desinstalação emite evento que desliga
  as features daquele tenant (e invalida o cache de features — ver performance-scalability.md).
- **Landlord vs. Tenant:** `developer` vê o catálogo completo e edita preços; `tenant_admin` vê a
  vitrine, adere (pagamento ou ativação de free) e usa **dentro do range liberado pelo Mzrt**.
  Tenant pode desabilitar plugins free (ex.: desligar o Cart default).

## Billing — onde a venda de plugin entra

Assinar plugin pago gera um **`PlatformOrder`** (plano Plataforma, gateway do Mozart) — **não** o
`Order` tenant-scoped. Free gera `PlatformOrder` de `amount_cents=0`. `plugin_purchases` legado é
descartado. **Conceito e schema não se repetem aqui** — canônico em
[`../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md`](../00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md)
e [`../40-financial/subspecs/orders-payments.md`](../40-financial/subspecs/orders-payments.md).

## Domain Boundaries

- **Emite:** eventos de ativação/expiração de plugin → invalidam cache de features do tenant;
  registro de campos de auth do tenant via evento (`TenantIntegration`).
- **Consome:** assinar plugin pago mapeia um **`PlatformOrder`** do Financial (plano Plataforma,
  gateway do Mozart) — **não** o `Order` tenant-scoped. Free gera `PlatformOrder` de `amount_cents=0`.

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
