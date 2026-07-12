# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-12 — **Fundação de gateway de pagamento completa nos dois âmbitos + módulo Ecosystem
  (5 commits: `215ea19`, `acf315d`, `d31e185`, `4546f62`, `da35f1f`).**
  - **Contrato agnóstico de ledger:** `PaymentGatewayInterface` (`identifier/label/charge/validateConfiguration`)
    + DTOs `ChargeIntent`/`ChargeResult`/`ResolvedGateway` + `PaymentGatewayManager` (registro de
    adaptadores). Adaptador stateless recebe `charge(array $credentials, ChargeIntent)` — nunca o model
    do ledger — servindo os dois ledgers (ADR-003).
  - **Módulo Ecosystem (ADR-005):** plugin = capability do core gated por flag + config por tenant
    (não código dinâmico; `app/Plugins/` adiado); **gateway é plugin**. `Plugin` (catálogo normalizado,
    `capability_key`), `PluginActivation` (entitlement), `TenantPluginConfig` (config de instância
    genérica, `encrypted:array` + `$hidden`). **Scaffold pré-spec descartado** (4 migrations `2026_02_21_1830xx`).
  - **Resolução tenant→aluno:** `TenantGatewayResolver` (Financial) consome `Ecosystem\Contracts\TenantGatewayProvider`
    (fronteira invariante 11), casa `slug→adaptador`, valida config, devolve `ResolvedGateway` atômico,
    honra entitlement, isola por tenant.
  - **Gateway da plataforma (Mzrt):** `PlatformPaymentGateway` (global, dedicado, `encrypted:array` +
    `$hidden`, `makeDefault()` transacional) + `PlatformGatewayResolver`.
  - **Review externo:** findings fechados → 1 (segredos fora da serialização), 2 (adapter+cred atômico),
    4 (default atômico, escopo plataforma); parcial → 3 (validação: feita na resolução, falta na persistência).
  - **Qualidade:** full suite **381 passed**, larastan `[OK]` (`--memory-limit=1G`; default estoura 128M),
    Architecture 16/16, pint. Tudo commitado.
- 2026-07-11 — MVP comercial pago selado; fix cert (`aa88717`), ADR-004 tenant scoping (`faf11c5`),
  RBAC own de instructor (`7adab03`). Detalhe nos `tasks.md`/git.

## Próximos passos (1-3)

1. **Fechar o fluxo de checkout (MVP pago).** Decisão pendente do 1º adaptador:
   **A)** `MockGateway` (sem dep — bate com a tela "Simulação de Pagamento / Mock Gateway"; destrava
   `POST /financial/checkout` → webhook simulado → `OrderPaidEvent` E2E); **B)** `StripeGateway` via
   `stripe/stripe-php` (**precisa aprovar dep**); **C)** Stripe via HTTP puro (sem dep, assina webhook
   na mão). Recomendado: **A** agora, Stripe ao ligar de verdade. Depois: `POST /financial/checkout`,
   webhook cego `POST /financial/webhooks/gateway/{slug}` + `ProcessPaymentWebhookJob`.
2. **Situação 1 (Mzrt→tenant) + config de plugin:** ledger `PlatformOrder*` (ADR-003) + compra/ativação
   de plugin; endpoints de ativação e config de plugin (`TenantPluginConfig` via engrenagem), com
   **validação na persistência** (finding 3a) e schema de config declarado em código.
3. **Dívidas:** LGPD-03 (uniques tenant-scoped cpf/email) + P2 auditoria; robustez de emissão de
   certificado (retry/`ShouldQueue`, reemissão pós-revoke).

## Decisões abertas

- **1º adaptador de gateway:** MockGateway (A) × StripeGateway SDK (B, dep) × HTTP puro (C).
- Política de reembolso, modelo de reconciliação (MVP pago).
- `validate-on-persist` de config (finding 3a) e marcador de **default gateway por tenant** (finding 4,
  escopo tenant) — entram com o endpoint de config.
- Convergência de spec: `TenantPaymentGateway`/`TenantIntegration`/`PluginSetting` → `TenantPluginConfig`
  (Entities das specs Financial/Ecosystem ainda desatualizadas; código vence — ADR-005).
- Reemissão de certificado pós-revoke; dívidas transversais (teto RBAC, LGPD operacional).

## Último commit

- `da35f1f` (feat financial: platform payment gateway + resolver). Solo dev, commits diretos na main.
