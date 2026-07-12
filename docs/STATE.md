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

## PIVOT 2026-07-12 — MVP = B2B2B, Admin-first (não B2C checkout)

Decisão do dono após revisão estratégica de ROI (análise em `.slim/deepwork/mvp-roi-prioritization.md`):
**modelo é B2B2B** (parceiro → associação → membros). **Associação paga licença** (cobrança manual
PIX/boleto/NF no início); **membro não paga curso avulso**. Logo **checkout tenant→aluno sai do MVP**
(STATE #1 anterior = MockGateway + `POST /financial/checkout` **cancelado/adiado**).

A fundação de gateway construída **não é desperdício**: `PlatformPaymentGateway` = plataforma cobra
tenant = cobrança de licença B2B (mesmo manual no início, abstração pronta).

Prioridade nova (análise, confirmada): **1) Admin tenant mínimo (ativação) → 2) Student endurecido →
3) Mzrt operacional manual → 4) Instructor → 5) Home (já era última no ROADMAP)**. Não "finalizar área
inteira"; fatia comercial ponta a ponta. Fase 0 = vender antes de ampliar (associação piloto + contrato).

## Próximos passos (1-3)

1. **Fatia white-label: config de branding do tenant.** Storage **já existe** (`TenantCustomization`:
   `draft_settings`/`published_settings` + publish workflow). Falta: (a) endpoint **público** de leitura
   por domínio (subset de branding de `published_settings`, **sem secrets**); (b) endpoint admin de
   escrita `draft` + publish; (c) **catálogo fechado (allowlist)** de chaves de branding declarado em
   código (nome/logo/cores/termos/privacidade/suporte). Requisito técnico do white-label, não manualizável.
2. **Endurecer criação de usuário / membros (Admin).** `POST /api/v1/core/users` hoje é **sem auth**
   (`RegisterUserRequest::authorize()=true`, sem role no payload) e **sem throttle** → spam + enumeração
   de e-mail. Model-independente: adicionar throttle já. Model-dependente: sob B2B2B a associação controla
   entrada → mover pra fluxo de convite/import por admin (`invite_only` está em Diferidos no ROADMAP).
3. **Student endurecido (confiabilidade, não features):** convite + recuperação de senha, e-mail entregue,
   mídia real, tenant inativo / host desconhecido com fallback seguro, E2E no domínio do cliente.

## Decisões abertas

- **Entry point da fatia Admin:** (A) branding público+admin [recomendado, greenfield de endpoint sobre
  storage pronto] × (B) membros/convite/import × (C) provisioning do 1º admin (runbook Mzrt manual).
- **Política de convite/registro:** manter `POST /users` aberto (open enrollment, MVP atual) × fechar
  para invite-only sob B2B2B (associação controla membros). Liga com risco #1 da análise.
- Reembolso/reconciliação: **fora do MVP** enquanto cobrança de licença for manual.
- Convergência de spec: `TenantPaymentGateway`/`TenantIntegration`/`PluginSetting` → `TenantPluginConfig`
  (Entities Financial/Ecosystem desatualizadas; código vence — ADR-005).
- Certificado pós-revoke; dívidas transversais (teto RBAC, LGPD operacional, custos de mídia/margem).

## Último commit

- `da35f1f` (feat financial: platform payment gateway + resolver). Solo dev, commits diretos na main.
