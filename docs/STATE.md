# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-13 — **`security:audit-deps` endurecido no scan lock↔vendor.** O serviço agora compara
  metadata do `composer.lock` com `vendor/composer/installed.json` (`version`, `source.*`,
  `dist.*`, incluindo `dist.shasum` quando presente) e abre finding de drift explícito.
- **Rollout do gate decidido para não quebrar o repo por passivo pré-existente:** em vez de tornar
  o `qa:gate` bloqueante imediatamente, foi criado `composer qa:deps` e o workflow
  `.github/workflows/qa-gate.yml` o executa em modo **report-only** (`continue-on-error: true`).
- **Cobertura adicionada:** fixtures clean/suspicious atualizadas para metadata drift; testes de
  console cobrem scan-vendor limpo, drift em installed metadata e wiring de `qa:deps` no CI.
- **Validação:** `SecurityAuditDepsCommandTest` verde (6 testes / 36 asserts), `composer validate`
  verde, Pint rodado.

## Próximos passos (1-3)

1. **Tratar o passivo revelado por `composer qa:deps`.** Há findings high do scanner heurístico e
   advisories reais do `composer audit --locked`; decidir o que vira baseline/allowlist, regex a
   refinar e quais upgrades de pacotes entram primeiro.
2. **Fechar os gaps restantes da spec do auditor**, se priorizado: scan de providers resolvidos,
   symlinks/binários inesperados e `replace`/`provide`.
3. Retomar a trilha principal do produto (áreas/Financial/Learning) quando a janela de segurança
   estiver suficiente para não bloquear trabalho não relacionado.

## Para depois (parqueado — não é o foco agora)

- **Auditor de supply chain `security:audit-deps`** — **ENTREGUE e endurecido localmente** (base:
  commit `1996c01`; working tree atual adiciona drift lock↔installed metadata + `qa:deps`
  report-only no CI). Hooks seguem **opt-in**: `git config core.hooksPath .githooks`.
- **Upgrade Laravel 13 / PHP 8.5** — task dedicada; hoje bloqueado por deps em `^12`
  (scribe/boost/sanctum/larastan/spatie). Ver `ROADMAP.md` §"Meta de stack".

## Decisões abertas

- **Qual severidade/ruído aceitável para o futuro gate bloqueante de deps?** Hoje `qa:deps` é só
  report-only porque o repo ainda reprova no estado atual.
- **Sub-decisão (C)** de `areas-surfaces.md`: estratégia anti-repetição de Resource (base
  compartilhada + subclasses por área vs independentes). Decide ao implementar a 2ª área.
- Dívidas pré-existentes: allowlist `ModuleBoundaryTest` → Events/Contracts; phpstan level 5
  (~156 erros); findings/advisories de dependências pendentes.

## Último commit

- `c2ed39e` — Branch `harness/specs-foundation` no momento do checkpoint.
- Últimos commits relevantes: `1996c01` (`feat(security): supply-chain dependency auditor`) e
  `48968fa` (`feat(learning): area-first guard + re-slot GET /admin/courses/{id}`).
- Working tree **não está limpo**: há mudanças locais desta task ainda não commitadas/pushadas.
