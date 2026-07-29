---
domain: financial
last-updated: 2026-07-29
---

# Tasks — Financial

Domínio **em fundação** (ledger interno `Order*` + contrato de gateway prontos). Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

- [x] Fundação mínima interna do ledger tenant→aluno: módulo `Financial`, models, migrations, factories de `Order`/`OrderItem`/`Payment`, `OrderPaidEvent` e guard monetário.
- [x] `OrderPaidEvent` + listener no Learning (`EnrollService`).
- [x] Gateway da plataforma: `PlatformPaymentGateway` (`configuration` `encrypted:array` + `$hidden`, `is_active`/`is_default`, `makeDefault()` transacional), `PlatformGatewayResolver` e testes.
- [x] Resolução de gateway por tenant: `TenantGatewayResolver` + `ResolvedGateway`, consumindo `Ecosystem\Contracts\TenantGatewayProvider`; resolução atômica, entitlement e isolamento por tenant.
- [x] Fundação de gateway agnóstica de ledger: `PaymentGatewayInterface`, `ChargeIntent`, `ChargeResult` e `PaymentGatewayManager`; adaptadores stateless recebem credenciais decifradas e intenção neutra.
- [x] Gateway `cash` first-party + preset de onboarding: cobrança `pending` e provisionamento idempotente de catálogo, activation e config habilitada.
- [x] **Admin gateway do tenant (implementação canônica: Ecosystem).** `GET` lista gateways e schemas; `PUT` atualiza ativação/configuração em `/api/v1/admin/payment-gateways/{plugin}`. `GatewayConfigurationRegistry` em `Financial\Contracts` publica o schema; persistência valida configuração, trata adaptador indisponível e mantém no máximo um gateway habilitado por tenant por troca atômica. Segredos são cifrados, write-only e redigidos na resposta. Slice coberto por testes e gates; Financial consome contrato/resolução.
- [x] `POST /api/v1/admin/orders/{id}/confirm-manual-payment` para `cash`: classificação autoritativa, confirmação transacional/idempotente, auditoria de status e gravação de outbox durável na transação. Publicação após commit é best-effort; drainer recupera pendências.
- [x] `POST /api/v1/student/checkout`: preço e snapshot autoritativos, idempotência, cobrança automática/manual e replay. Máquina de claim/replay cobre interleaving, token perdedor, gateway histórico após rotação/desabilitação e outbox do vencedor. Oracle Gate 2 PASS.

## In Progress

_Nenhuma task em progresso._

## Pending

- [ ] Completar fundação financeira: `PriceHistory`.
- [ ] `GET /api/v1/student/orders` e `GET /api/v1/student/orders/{id}`.
- [ ] `POST /api/v1/webhooks/gateways/{gateway_slug}` + `ProcessPaymentWebhookJob`.
- [ ] Tradução PT-BR de exceções de gateway.
- [ ] Adaptadores first-party Stripe, Mercado Pago, PagSeguro, PIX-nativo e Asaas via `PaymentGatewayInterface`.
- [ ] Gateways adicionais como plugins financeiros; contrato atual já prevê.
- [ ] Comissão de instrutor: `commission_rate` + `CommissionLog`.
- [ ] Portar/revisar `Order`, `OrderItem`, `Payment` e `OrderOriginType`, mantendo cents inteiros.

## Needs Review

- **Config de instância de gateway = config de plugin genérica — ADR-005.** Gateway é plugin (`kind='gateway'`, `capability_key='gateway.<slug>'`); credencial/configuração do tenant vive em `TenantPluginConfig` do **Ecosystem**. Gateway da plataforma = `PlatformPaymentGateway`. Segredos são blob `encrypted:array`.
- **Requisitos de review externo (2026-07-12):**
  1. ✅ Segredos fora da serialização: `TenantPluginConfig.config` usa `encrypted:array` + `$hidden`.
  2. ✅ Adaptador + credencial atômicos: `TenantGatewayResolver` devolve `ResolvedGateway` sem cruzar tenant/gateway.
  3. ✅ Validação: `validateConfiguration` roda na resolução e na persistência pelo endpoint Admin.
  4. ✅ Seleção habilitada atômica: tenant tem no máximo um gateway habilitado; `PUT` troca atomicamente, sem estado duplicado de seleção. Plataforma mantém `PlatformPaymentGateway::makeDefault()` transacional em escopo próprio.

## Open Questions

- _(resolvido)_ `cash` é preset gratuito de confirmação manual; demais adaptadores são plugins financeiros tenant-selecionáveis, sem Cashier.
