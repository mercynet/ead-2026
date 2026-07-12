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
- [ ] **Resolução do gateway ativo por tenant** — sobre a config-de-plugin acima: escolhe adaptador + credenciais atomicamente, valida config, honra entitlement (plugin ativo). Substitui o `forTenant`/`TenantPaymentGateway` descartado.
- [ ] **`PlatformPaymentGateway` (global, credenciais do Mozart) + resolução do lado Plataforma** — situação dev/admin→plataforma. Mesmo contrato/adaptadores; store de credenciais global. Ledger `PlatformOrder*` segue em task própria (ADR-003).
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
- **Requisitos carregados de review externo (2026-07-12), a amarrar na config-de-plugin do Ecosystem
  (não valem pra fundação atual porque o alvo foi descartado, mas não podem se perder):**
  1. **Segredos fora da serialização:** cast encriptado protege só o banco; o blob de config precisa de
     `$hidden` + teste garantindo ausência em `toArray`/JSON/log.
  2. **Adaptador + credencial atômicos na resolução:** resolver adaptador e credenciais como unidade
     (value object ou `chargeForTenant()`), pra não casar adaptador com config de outro tenant/gateway.
  3. **Validar config na persistência e na resolução** (`validateConfiguration`) — config incompleta não
     pode chegar à cobrança.
  4. **Troca de gateway default atômica:** transação + lock por tenant (ou unique parcial), senão corrida
     deixa múltiplos defaults ou nenhum.

## Open Questions

- _(resolvido)_ Gateways: **Stripe no MVP** (via Cashier); demais como **plugins financeiros**
  tenant-selecionáveis (taxas variam por tamanho da escola). Ver ADR-001.
