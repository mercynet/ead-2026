# ADR-005: Plugins como capability gated no core (first-party); gateway é plugin; gateway da plataforma dedicado

- **Data**: 2026-07-12
- **Status**: Aceito
- **Decisores**: Paulo, Claude

## Contexto e problema

O ead2026 (API-first, monólito modular) precisa vender módulos opcionais (plugins) e deixar cada
tenant compor sua plataforma — ativar/desativar features, configurar cada plugin com um schema
próprio (gateway tem chaves; carrinho tem toggles; assinaturas tem trial/retries) e cobrar por eles.
Ao mesmo tempo há **dois gateways distintos**: o das vendas da plataforma (Mzrt→tenant, compra de
plugin) e o das vendas do tenant (tenant→aluno). Faltava fixar **o que é um plugin tecnicamente**,
**onde mora a config** de cada âmbito e **como o gateway** encaixa nisso — sem importar o design
antigo (Filament, discovery por filesystem, `laravel/cashier`).

## Drivers da decisão

- **First-party only:** só nós escrevemos plugins (sem upload de terceiros).
- Monólito modular: todo o código já viaja no app; não há terceiros injetando código.
- Multi-gateway real (Stripe, Mercado Pago, PagSeguro, PIX) — não travar num PSP.
- Segurança de credenciais e validação de config na borda.

## Opções consideradas

- **Plugin = capability do core, gated por flag + config por tenant** ✅ escolhida
- Plugin = código carregável dinamicamente (`app/Plugins/<Dir>` com provider/migrations próprias,
  boot via DB) — flexível, mas paga versionamento/isolamento/boot dinâmico que só compensam com
  terceiros; rejeitada para o MVP (porta fica aberta: `directory_name` nullable).
- Plugin = flag pura sem config estruturada — não cobre os schemas por plugin das telas.

## Decisão

1. **Plugin é uma capability do core.** `Plugin` (catálogo, âmbito Mzrt) carrega uma `capability_key`
   (ex.: `quiz.advanced`, `gateway.stripe`, `cart`). O core pergunta "capability ativa pro tenant?"
   via resolver cacheado. Feature avançada (ex.: quiz avançado) é **código já no módulo**, destravado
   pela flag — não código carregado em runtime.
2. **Gateway é um plugin** (`capability_key = gateway.<slug>`); o `slug` casa com o `identifier()` do
   adaptador registrado no `PaymentGatewayManager`. A config/credencial do gateway do tenant é
   **config de instância de plugin genérica** (`TenantPluginConfig`, blob `encrypted:array`, fora da
   serialização), não um model financeiro dedicado.
3. **Gateway das vendas da plataforma é dedicado:** `PlatformPaymentGateway` (landlord global, driver
   único ativo) — o Mzrt não "compra" o próprio gateway, então não usa ativação/entitlement de plugin.
   Ambos os âmbitos alimentam o **mesmo** registro de adaptadores + contrato agnóstico (ADR-003).
4. **Schema de config declarado em código** pelo plugin (campos + validação); a API expõe pro front
   montar o modal e o backend **valida na persistência** (`PaymentGatewayInterface::validateConfiguration`
   é o caso do gateway).
5. **Sem `laravel/cashier`:** cada gateway é um adaptador de `PaymentGatewayInterface` (SDK/HTTP
   direto). Isto **supersede** o ponto "StripeGateway via Cashier" do ADR-001.

O scaffold pré-spec (`2026_02_21_1830xx`: `plugins` PK=`name`, design filesystem) é órfão e será
**reescrito in-place** para o schema normalizado, slice a slice.

## Consequências

- ✅ Flexibilidade das telas (ativar/desativar por tenant, config por plugin, tiers, dependências)
  sem custo de boot dinâmico/versionamento.
- ✅ Um só contrato/registro de adaptadores serve os dois ledgers e os dois âmbitos de gateway.
- ✅ Credenciais cifradas + validadas na borda; segredos fora da serialização.
- ❌ Extensão 100% externa exige, no futuro, ativar o caminho `app/Plugins/` (adiado, não gratuito).
- ❌ Dois stores de gateway (`PlatformPaymentGateway` × `TenantPluginConfig`) — aceito: âmbitos
  genuinamente distintos.
- ❌ `capability_key` acopla catálogo (Ecosystem) ↔ features do core; exige registro/allowlist.

## Links

- Supersede parcialmente: [`001-reuso-eadia-pacotes-billing.md`](001-reuso-eadia-pacotes-billing.md) (ponto do `laravel/cashier`).
- Relacionado: [`002-categorias-tabela-unica-pivot-dedicado.md`](002-categorias-tabela-unica-pivot-dedicado.md), [`003-billing-dois-ledgers-itemable-seam.md`](003-billing-dois-ledgers-itemable-seam.md).
- Specs afetadas: [`../../40-financial/spec.md`](../../40-financial/spec.md), [`../../50-ecosystem-plugins/spec.md`](../../50-ecosystem-plugins/spec.md) (config de instância = `TenantPluginConfig`, supersede `PluginSetting`/`TenantIntegration` split).
