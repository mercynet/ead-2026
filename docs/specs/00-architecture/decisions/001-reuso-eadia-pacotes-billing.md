# ADR-001: Reaproveitar pacotes e design do eadIA; billing via abstração de gateway

- **Data**: 2026-06-13
- **Status**: Aceito
- **Decisores**: Paulo, Claude

## Contexto e problema

O ead2026 (API-first, modular monolith) reescreve o produto do projeto anterior `../eadIA`
(Laravel + Filament). O eadIA tem modelo de domínio, sistema de plugins e financeiro maduros, mas
seu plano de **áreas/painéis está errado** (ver [`../areas-surfaces.md`](../areas-surfaces.md)).
Precisávamos decidir o que reaproveitar para encurtar a curva **sem** importar dívida nem travar a
arquitetura — em especial no billing, onde gateways brasileiros (PIX, Mercado Pago, PagSeguro) e
expansão internacional estão em jogo.

## Drivers da decisão

- Encurtar desenvolvimento reusando design/modelo testado, com responsabilidade.
- Não reinventar o que já é pacote no ead2026.
- Não travar billing num único gateway (mercado BR + expansão global).
- Manter API-first e modular (`app/Modules/<Domínio>`), não Filament.

## Opções consideradas

- **Reuso seletivo de design + baseline de pacotes existente** ✅ escolhida
- Portar o eadIA quase inteiro (inclui plano de áreas errado, código Filament/flat) ❌
- Construir tudo do zero (ignora pacotes já instalados e design validado) ❌
- Fundar billing no `laravel/cashier` (Stripe-only) ❌ — risco de lock-in para PSPs BR

## Decisão

**Pacotes — o ead2026 já tem e está ligado** (não reinventar): `spatie/laravel-multitenancy`
(tenant via `RequestTenantFinder`), `laravel/sanctum`, `spatie/laravel-permission`,
`spatie/laravel-activitylog` (audit/LGPD). **Instalado e a usar**: `spatie/laravel-medialibrary`
para materiais/uploads/PDF (não rolar storage à mão). Também `spatie/laravel-query-builder` e
`staudenmeir/eloquent-has-many-deep`.

**Trazer (estagiado, por fase, nunca antes da fiação+teste)**: `laravel/telescope` (dev),
`laravel/cashier` (na task do adapter Stripe), `lab404/laravel-impersonate` (na feature de
impersonação, hoje diferida).

**Billing fundado em abstração de gateway** (`PaymentGatewayInterface` + `TenantPaymentGateway` por
tenant, portados do eadIA), com `Order`/`OrderItem` polimórfico para venda avulsa + comissão de
instrutor. `StripeGateway` (via Cashier) é o **primeiro adaptador** — cobre cartão/PIX/boleto BR +
global. Cashier é **um adaptador, não a fundação** — foi por isso que "fundar no Cashier" perdeu.

**Gateways adicionais entram como plugins financeiros** (Mercado Pago, PagSeguro, PIX-nativo,
Asaas…), cada um implementando `PaymentGatewayInterface`. O tenant **escolhe/ativa** o gateway via
sistema de plugins ([`50-ecosystem-plugins`](../../50-ecosystem-plugins/spec.md)) — as taxas variam
muito por tamanho da escola, então a escolha é do tenant. Não travar em Stripe.

## Consequências

- ✅ Tenant, RBAC, audit, mídia = prontos por pacote; financeiro reusa design do eadIA.
- ✅ Billing nunca trava num PSP; Stripe entrega já, BR-native depois sem reescrever.
- ✅ Três camadas de billing explícitas (plataforma→tenant, tenant→aluno, comissão→instrutor).
- ❌ Porte flat→modular custa ajuste de namespace/trait por model (não é copy-paste cego).
- ❌ Manter abstração de gateway é mais código que usar Cashier puro — preço de não travar.

## Links

- Spec de áreas: [`../areas-surfaces.md`](../areas-surfaces.md)
- Domínios afetados: `docs/specs/40-financial/`, `docs/specs/50-ecosystem-plugins/`,
  `docs/specs/20-catalog-learning/`
- Origem do design reusado: projeto `../eadIA` (referência de domínio/plugins/financeiro)
