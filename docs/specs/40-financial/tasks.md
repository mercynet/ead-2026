---
domain: financial
last-updated: 2026-07-12
---

# Tasks — Financial

Domínio **em fundação** (ledger interno `Order*` + contrato de gateway prontos; sem endpoints ainda).
Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

- [x] Fundação mínima interna do ledger tenant→aluno: módulo `Financial` registrado, models +
  migrations + factories de `Order`/`OrderItem`/`Payment`, `OrderPaidEvent` com payload primitivo e
  guard monetário ampliado para migrations modulares.
- [x] `OrderPaidEvent` + listener no Learning (`EnrollService`).
- [x] **Gateway das vendas da plataforma (Mzrt).** `PlatformPaymentGateway` (global, `configuration`
  `encrypted:array` + `$hidden`, `is_active`/`is_default`, `makeDefault()` transacional) + factory +
  `PlatformGatewayResolver` (devolve `ResolvedGateway` atômico, valida config, via registro de
  adaptadores). 6 testes (`PlatformGatewayResolutionTest`: encriptação+`$hidden`, resolve+charge, sem
  ativo, adaptador ausente, makeDefault atômico). Fecha o finding 4 do review no escopo plataforma.
- [x] **Resolução de gateway por tenant (cross-module via contrato).** `TenantGatewayResolver`
  (Financial) + DTO `ResolvedGateway {adapter, credentials}` + exceção `GatewayResolutionException`.
  Consome `Ecosystem\Contracts\TenantGatewayProvider` (impl no Ecosystem lê `Plugin`/`PluginActivation`/
  `TenantPluginConfig`) — respeita a fronteira de módulos (só `Contracts`). Resolve adaptador + credencial
  **atomicamente**, valida config na resolução, honra entitlement (ativação + config enabled), isola por
  tenant; multi-gateway ativo logado. 7 testes (`TenantGatewayResolutionTest`). Fecha os findings 1/2/3b
  do review externo (ver Needs Review).
- [x] **Fundação de gateway sem lock-in, agnóstica de ledger (ADR-001 + ADR-003):** contrato
  `PaymentGatewayInterface` (`identifier/label/charge/validateConfiguration`) + DTOs `ChargeIntent`
  (intenção neutra: `amount_cents/currency/reference/description/metadata`) e `ChargeResult` +
  `PaymentGatewayManager` (registro de adaptadores: `register/get/has/all`; singleton no
  `FinancialServiceProvider`). O adaptador é **stateless e agnóstico de ledger**:
  `charge(array $credentials, ChargeIntent $intent)` — recebe credenciais decifradas + intenção
  neutra, **nunca o model do ledger** — para um mesmo adaptador (ex.: StripeGateway) servir o plano
  Venda (tenant→aluno) e o plano Plataforma (Mozart→tenant) sem duplicação. Testes em
  `tests/Unit/Financial/PaymentGatewayManagerTest.php` (registro + `charge` via adaptador + singleton).
  **Escopo deliberadamente reduzido:** a RESOLUÇÃO por tenant (credenciais, default, entitlement) e o
  store de config **não** entram aqui — decidido (2026-07-12) que a config de instância do gateway é
  **config de plugin genérica** (âmbito Ecosystem, ainda não implementado). Por isso o
  `TenantPaymentGateway`/`forTenant` do rascunho inicial foi **descartado**; ver Needs Review.
  `parseWebhook` também fica na task do webhook.

## In Progress

- [ ] Registro financeiro espelho para matrículas gratuitas (auditoria/LTV).

## Pending

- [ ] Completar a fundação financeira restante: `PriceHistory`.
- [ ] `POST /financial/checkout` (calcula preço no servidor, gera Order + sessão do gateway).
- [ ] `GET /financial/orders` e `GET /financial/orders/{id}`.
- [ ] `POST /financial/webhooks/gateway/{gateway_slug}` (rota cega) + `ProcessPaymentWebhookJob`.
- [ ] Tradução PT-BR de exceções de gateway.

### Reuso eadIA + billing (ver ADR-001)
- [x] **`PaymentGatewayInterface`** (contrato + DTOs + registro de adaptadores) — portado/revisado do eadIA. Fundação que não trava em gateway. (ver Done)
- [ ] **Config de instância do gateway = config de plugin (Ecosystem).** A credencial/config do tenant por gateway vive no store genérico de config-de-plugin do Ecosystem (gateway é um plugin como os outros), não em model financeiro dedicado. Depende do módulo Ecosystem existir. Ver Needs Review.
- [x] **Resolução do gateway ativo por tenant** — `TenantGatewayResolver` (Financial) consome o contrato `Ecosystem\Contracts\TenantGatewayProvider` (fronteira, invariante 11), casa `slug→adaptador`, valida config, devolve `ResolvedGateway {adapter, credentials}` atômico; honra entitlement (`PluginActivation` ativa + `TenantPluginConfig` enabled). Substitui o `forTenant`/`TenantPaymentGateway` descartado. Ver Done.
- [x] **`PlatformPaymentGateway` (global, credenciais do Mozart) + `PlatformGatewayResolver`** — situação dev/admin→plataforma. Model (config `encrypted:array` + `$hidden`, `is_active`/`is_default`, `makeDefault()` **transacional** — fecha finding 4 no escopo plataforma) + resolver que devolve `ResolvedGateway` atômico via mesmo registro de adaptadores. Ledger `PlatformOrder*` segue em task própria (ADR-003). Ver Done.
- [ ] **`StripeGateway` via `laravel/cashier`** (1º adaptador; add Cashier nesta task — **precisa aprovar a dependência**). Cobre cartão/PIX/boleto BR + global. Registra-se no `PaymentGatewayManager` e serve os **dois** ledgers via contrato agnóstico.
- [ ] **Gateways adicionais como plugins financeiros** (Mercado Pago, PagSeguro, PIX-nativo, Asaas) — cada um implementa `PaymentGatewayInterface`; tenant ativa via `50-ecosystem-plugins`. NÃO no MVP, mas o contrato já prevê.
- [ ] **3ª camada — comissão de instrutor**: `commission_rate` + `CommissionLog` (repasse tenant/plataforma→instrutor). Não existia no eadIA — gap a modelar.
- [ ] **Portar (revisar, não copiar cego):** `Order`, `OrderItem` (polimórfico itemable: Course/SubscriptionPlan/Plugin + `item_snapshot`), `Payment` (`gateway_response` cru), enum `OrderOriginType`. Padronizar **centavos inteiros** (eadIA mistura decimal/cents — corrigir).

## Needs Review

- **Config de instância de gateway = config de plugin genérica — fixado em [ADR-005].** Gateway é um
  plugin (`kind='gateway'`, `capability_key='gateway.<slug>'`); a credencial/config do tenant vive no
  store genérico `TenantPluginConfig` do **Ecosystem** (catálogo `Plugin` já criado), **não** em model
  financeiro dedicado — por isso `TenantPaymentGateway` foi descartado. Gateway da plataforma (Mzrt) =
  `PlatformPaymentGateway` dedicado. Segredos = **blob inteiro encriptado** (`encrypted:array`).
  **Conflito de spec a convergir:** `40-financial/spec.md` (Entities) ainda lista `TenantPaymentGateway`
  e `50-ecosystem-plugins/spec.md` lista `TenantIntegration`/`PluginSetting` — colapsar em
  `TenantPluginConfig`/`PlatformPaymentGateway` nos slices de config.
- **Requisitos carregados de review externo (2026-07-12) — status:**
  1. ✅ **Segredos fora da serialização:** `TenantPluginConfig.config` = `encrypted:array` + `$hidden`
     (teste de encriptação em repouso + ausência em `toArray`).
  2. ✅ **Adaptador + credencial atômicos na resolução:** `ResolvedGateway {adapter, credentials}` +
     `TenantGatewayResolver` — nunca casa adaptador com config de outro tenant/gateway.
  3. 🔶 **Validar config:** feito **na resolução** (`validateConfiguration` no resolver); falta **na
     persistência** — entra com o endpoint de config do gateway.
  4. 🔶 **Troca de gateway default atômica:** feito no escopo **plataforma** (`PlatformPaymentGateway::makeDefault()`
     transacional). No escopo **tenant** ainda não há marcador de default; multi-gateway ativo é **logado**
     e resolve o mais recente — marcador + troca atômica quando a UI suportar múltiplos gateways por tenant.

## Open Questions

- _(resolvido)_ Gateways: **Stripe no MVP** (via Cashier); demais como **plugins financeiros**
  tenant-selecionáveis (taxas variam por tamanho da escola). Ver ADR-001.
