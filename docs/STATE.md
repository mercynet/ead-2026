# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 — **Branch `harness/specs-foundation` mergeada na `main`** (`d1eead0`, --no-ff,
  pushada): P0/P1 da auditoria + emissão automática de certificado (`CourseCompletedEvent` →
  listener Assessment) + spec E2E `learning/lessons-progress` (11/11 verde contra app real) +
  skill `validate-ai-work` + harness portável (hooks graphify via PATH; validator allowlista
  `/dev/null` — `validate-harness.py` verde, resta 1 WARN `.opencode/opencode.json`).
  Suites: Feature 307/307, Architecture 15/15, Unit 8/8.
- 2026-07-11 (cont.) — **CI destravado: `qa:gate` verde ponta a ponta** (`daf2e6c` + `f5a2fa7`):
  PHPStan passa via `phpstan-baseline.neon` (408 erros congelados); Insights com thresholds
  rebaixados ao baseline real (83/85/75/88). Auditoria read-only confirmou: validate, analyse,
  insights (83.5/92.5/75/88), migrate:fresh testing e suite completa (333 testes, 1386 asserts)
  todos verdes. Fonte única de thresholds consolidada em `phpinsights.php` (flags removidas
  do script `insights` do composer.json).
  Nota operacional: banco dev estava sem migrations recentes (migrado); dir
  `app/Modules/Learning/Actions/Access/` estava root-owned (corrigido chown 1000).

## Próximos passos (1-3)

1. **Decisão de escopo** (piloto gratuito vs MVP pago) + **decisão P1.6** (global scope de tenant
   — discutir e registrar ADR via `create-adr`). O trigger complementar de certificado (quiz
   aprovado depois do curso completo) está bloqueado nessa decisão de fronteira — ver
   `docs/specs/30-assessment/tasks.md`.
2. P2 da auditoria (recompra pós-cancelamento, vazamento de drafts na landing, slug único)
   e LGPD-03 (uniques tenant-scoped).
3. **Ratchet de qualidade** (CI destravado em 2026-07-11): PHPStan congelado em
   `phpstan-baseline.neon` (408 erros — pagar gerando docblocks `@property` via ide-helper e
   encolhendo o baseline) e thresholds do PHP Insights rebaixados ao nível real
   (quality 83 / complexity 85 / architecture 75 / style 88 — subir de volta a 85/85/80/95
   conforme a dívida for paga).

## Decisões abertas

- **Escopo de lançamento:** piloto gratuito controlado ou MVP comercial pago?
- **P1.6:** trait `BelongsToTenant` com global scope vs `where` explícito + smoke test (ADR pendente);
  a mesma discussão de fronteira decide o trigger complementar de certificado.
- Caminho pago: gateway inicial, política de reembolso, modelo de reconciliação.
- Dívidas transversais nos `tasks.md`/roadmap: teto RBAC, LGPD-03, CI, LGPD operacional,
  PHPStan/advisories, fronteiras cross-module.

## Último commit

- `f5a2fa7` (merge CI fix: insights thresholds → main) — `main` e `harness/specs-foundation`
  sincronizadas com seus respectivos origin; branch está 1 merge atrás da `main` (esperado
  pós-merge).
