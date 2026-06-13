# ADR-003: Billing — dois ledgers de Order (Plataforma vs Venda); `itemable` polimórfico como costura de extensão

- **Data**: 2026-06-13
- **Status**: Aceito
- **Decisores**: Paulo, Claude

## Contexto e problema

`areas-surfaces.md` definiu **três camadas de billing** (Plataforma, Venda, Comissão). A spec de
Financial colapsou a camada Plataforma dentro do `orders` tenant-scoped via `origin_type:
Subscription` — gerando duas ambiguidades fatais para um agente:

1. **`Subscription` significava duas coisas**: tenant assinando plugin do Mozart (Plataforma) **e**
   aluno assinando plano do tenant (Venda). Planos diferentes, mesma palavra, mesma tabela.
2. **Overlap `plugin_purchases × orders`** (herdado do eadIA): duas fontes de verdade financeira.

Além disso, os plugins **Cart** e **Subscriptions** (certos no roadmap) vendem **pelo plano Venda** —
o `orders` precisa ser o ponto de extensão para o que esses plugins (e futuros) vendem, **sem**
virar um schema que tenta adivinhar todo vendável (YAGNI).

## Drivers da decisão

- **Pagador, gateway, escopo e LGPD divergem** entre os planos: Plataforma = Mozart cobra o tenant
  no **gateway do Mozart**, escopo global (área Mzrt); Venda = tenant cobra o aluno no
  **`TenantPaymentGateway`**, escopo `tenant_id` (áreas Admin/Student/Home).
- **`area.guard` declarativo** (areas-surfaces decisão B): misturar global e tenant na mesma tabela
  forçaria query-scope condicional e `if` de plano no controller — o oposto do que a spec de áreas
  comprou.
- **Extensão sem redesign**: novos vendáveis (plano, plugin, futuro) não podem exigir migration no
  `orders`. A costura é o **`itemable` polimórfico**, não colunas novas.
- **YAGNI**: modelar só o que vende agora; a polimorfia é o espaço de futuro, não tabelas especulativas.

## Opções consideradas

### Estrutura de ledger
- **Dois ledgers irmãos** (`orders` + `platform_orders`, mesmo padrão Order/Item/Payment) ✅ escolhida
  — escopo/gateway/LGPD limpos por construção; `area.guard` sem `if` de plano.
- `orders` único com discriminador `plane` (platform|sales) ❌ — mistura escopo global e tenant na
  mesma tabela, exige query-scope condicional, quebra a separação de área. É o `if`-de-plano que a
  spec de áreas evita.
- Manter `plugin_purchases` separado do eadIA ❌ — fonte de verdade financeira paralela, sem o padrão
  Order/Payment/webhook do Financial.

### Costura de vendável
- **`OrderItem.itemable` polimórfico via morph map (string)** ✅ escolhida — Financial **não importa**
  o model do plugin (`ModuleBoundaryTest` preservado: morph map é acoplamento por string, não por
  classe). Novo vendável = nova entrada no morph map, **zero migration** de `orders`.
- FK fixa por tipo (`course_id`, `plan_id`, ...) ❌ — é o acoplamento legado do eadIA
  (`order_items.course_id`) que a port-review rejeitou; incha o schema a cada vendável.

## Decisão

1. **Dois ledgers, mesmo padrão, nunca a mesma tabela:**
   - **Plano Venda** (tenant → aluno): `orders` / `order_items` / `payments` / `tenant_payment_gateways`.
     Escopo `tenant_id`. Já existente.
   - **Plano Plataforma** (Mozart → tenant): `platform_orders` / `platform_order_items` /
     `platform_payments` / `platform_payment_gateways`. Escopo global (área Mzrt); `tenant_id` é o
     **pagador**, não eixo de isolamento. Gateways = formas de pagamento **do Mozart**.
2. **`plugin_purchases` descartado.** Venda de plugin = `PlatformOrder` com
   `itemable ∈ {Plugin, PluginPricing}`. Free gera `PlatformOrder` de `amount_cents=0` (registro
   espelho — Mzrt contabiliza free e pago no mesmo ledger).
3. **`origin_type` desambiguado:**
   - `orders` (Venda): `Direct` (1 curso) · `Cart` (N itens, **plugin Cart**) · `Subscription`
     (**aluno→plano do tenant**, **plugin Subscriptions**) · `Renewal`.
   - `platform_orders` (Plataforma): `PluginSubscription` · `Renewal`.
4. **`itemable` é a costura YAGNI.** Modelados **agora**: `Course`, `Plan` (Venda); `Plugin`,
   `PluginPricing` (Plataforma). **Não** pré-modelar outros vendáveis — novo tipo entra como nova
   entrada no morph map + (se preciso) novo model, sem tocar o schema de orders.
5. **Eventos separados:** `OrderPaidEvent` (Venda → Learning matricula) e `PlatformOrderPaidEvent`
   (Plataforma → Ecosystem ativa `PluginSubscription`).

## Consequências

- **(+)** Escopo/gateway/LGPD corretos por construção; `area.guard` sem `if` de plano.
- **(+)** Novo vendável não toca `orders` (só morph map). `plugin_purchases` morto = uma fonte de
  verdade por plano.
- **(+)** `ModuleBoundaryTest` intacto: Financial não importa models de plugin (morph map por string).
- **(−)** Dois conjuntos de tabelas + dois fluxos de webhook/evento a manter (preço de não misturar
  escopos). Mitigado por compartilharem o **mesmo padrão** (Action/Resource/teste espelhados).
- **(−)** Morph map vira ponto de registro obrigatório: esquecer de registrar um `itemable_type`
  causa `RelationNotFoundException` — coberto por invariante de teste (morph map fechado).

## Links

- Conceito das 3 camadas: [`../areas-surfaces.md`](../areas-surfaces.md) §Três camadas de billing.
- Schema dos dois ledgers: [`../../40-financial/subspecs/orders-payments.md`](../../40-financial/subspecs/orders-payments.md).
- Venda de plugin / free / grant: [`../../50-ecosystem-plugins/subspecs/subscriptions-billing.md`](../../50-ecosystem-plugins/subspecs/subscriptions-billing.md).
- Reuso de pacotes/design e gateways como plugin: [`001-reuso-eadia-pacotes-billing.md`](001-reuso-eadia-pacotes-billing.md).
