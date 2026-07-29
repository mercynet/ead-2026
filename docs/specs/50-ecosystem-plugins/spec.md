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

Modelo SaaS: Landlord vende capabilities opcionais à base multi-tenant. Tenant compõe plataforma ativando somente recursos necessários, incluindo gateways extras.

## Overview

Rastreia marketplace, cobrança Mzrt→tenant e assinaturas dinâmicas. Padrões em `api-conventions.md`, performance em `performance-scalability.md`, permissions em `rbac.md`.

## Entities

> ADR-005: plugin é capability do core gated por flag + config por tenant; não código carregado em runtime. `app/Plugins/` fica reservado para extensão externa futura. Gateway é plugin; gateway da plataforma é `PlatformPaymentGateway` no Financial.

| Model | Papel |
|-------|-------|
| `Plugin` | Catálogo global: slug, capability, kind, status, visibilidade e metadados. |
| `categories` + `category_plugin` | Taxonomia global reutilizada. |
| `PluginVersion`, `PluginPricing`, `PluginFeature` | Versão, preço e capabilities de catálogo. |
| `PluginRating` / `PluginRatingAggregate` | Avaliação e rollup para vitrine. |
| `PluginInstallation` / `PluginActivation` | Instalação e entitlement por tenant. |
| `TenantPluginConfig` | Store canônico por tenant+plugin: `config` `encrypted:array` e `$hidden`, `enabled`; schema declarado em código; inclui opções e segredos. |
| `PluginSubscription`, `PluginBilling`, `PluginGrant` | Assinatura, cobrança recorrente e comp. |
| `PluginUsageLog` / `PluginLicense` | Quota e licença. |
| `PluginCoupon` | Deferred. |

## Business Rules

- First-party only: capabilities registradas por DB, sem upload de terceiros.
- Provisionamento cria idempotentemente `cash` (`gateway.cash`) publicado, curado, ativo e habilitado, sem sobrescrever escolha do Admin.
- Só `developer` gerencia catálogo. `tenant_admin` consome vitrine dentro de entitlement liberado.
- Catálogo/default do dev vive em `Plugin`/`PluginPricing`; instância do tenant vive em `TenantPluginConfig`. `config` é `encrypted:array`, `$hidden` e segue schema em código; opções e segredos nunca aparecem em resposta API.
- **Admin gateways:** Ecosystem é dono técnico de `GET /api/v1/admin/payment-gateways` e `PUT /api/v1/admin/payment-gateways/{plugin}`. `GET` publica schemas via `Financial\Contracts\GatewayConfigurationRegistry`, consumidos por `PUT`; par é exceção inseparável ao limite de ≤1 endpoint por slice. Escrita valida persistência, trata adaptador indisponível e habilita no máximo um gateway por tenant em troca atômica.
- Free gera entitlement; billing de marketplace segue pendente onde entidades ainda não existem.
- Assinatura paga usa ledger `PlatformOrder` do Financial, nunca `Order` tenant-scoped.
- Ativação/expiração invalida cache de features.

## Domain Boundaries

- **Emite:** eventos de ativação/expiração para invalidar cache de features.
- **Possui:** `TenantPluginConfig` e endpoints Admin de gateway.
- **Consome:** `PlatformOrder` do Financial para assinatura paga; Financial fornece registry de schema, adaptadores e contrato de resolução, sem receber modelos do Ecosystem.

## Authorization

`developer` gerencia catálogo/preços; `tenant_admin` consome vitrine. Permissions efetivas por plugin seguem entitlement.

## Events

- `PluginActivated` / `PluginDeactivated`.
- `PluginSubscriptionSuspended`.

## Quick Reference

| Recurso | Endpoint | Acesso |
|---------|----------|--------|
| Vitrine | `GET /api/v1/ecosystem/marketplace/plugins` | tenant_admin |
| Store page | `GET /api/v1/ecosystem/marketplace/plugins/{slug}` | tenant_admin |
| Assinar | `POST /api/v1/ecosystem/marketplace/subscriptions` | tenant_admin |
| Cadastrar | `POST /api/v1/ecosystem/admin/plugins` | developer |
| Liberar/depreciar | `PATCH /api/v1/ecosystem/admin/plugins/{id}` | developer |
| Dashboard | `GET /api/v1/ecosystem/admin/subscriptions` | developer |
| Listar schemas/gateways | `GET /api/v1/admin/payment-gateways` | `financial.payment-gateways.list` |
| Atualizar gateway | `PUT /api/v1/admin/payment-gateways/{plugin}` | `financial.payment-gateways.update` |
