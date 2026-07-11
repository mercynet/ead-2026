# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 — **P0.1 e P0.2 fechados** (os dois itens críticos de
  [`docs/auditoria-correcoes-2026-07-11-pending.md`](auditoria-correcoes-2026-07-11-pending.md),
  ambos anotados ✅ no doc):
  - **P0.1** (nota forjável): `questions_snapshot` congelado no servidor em `StartAttemptAction`
    (migration nova no módulo Assessment); `PATCH /attempts/{id}` aceita só `question_id` +
    `selected_options`; scoring 100% server-side; `maxPoints=0` morto; Resources sem gabarito;
    fix bônus em `QuizAttemptPolicy::create` (checava `attempts.view`). `AttemptApiTest` reescrito.
  - **P0.2** (cross-tenant via `file_path` de material): `StoreCourseMaterialRequest` com
    `starts_with:tenants/{tenant_id}/` + anti-traversal; `GenerateCourseMaterialDownloadUrlAction`
    revalida path persistido + allowlist de disk antes de assinar (422, sem registrar download).
    Datasets negativos em `CourseMaterialApiTest`/`CourseMaterialDownloadApiTest`.
  - Suites: Feature 291/291, Architecture 15/15, Unit 8/8, Pint ok. Specs + tasks.md atualizados.
  - **Sem commit** — tudo no working tree da branch `harness/specs-foundation` (P0.1 e P0.2
    deveriam ir em commits separados, conforme o doc de auditoria).

## Próximos passos (1-3)

1. Commitar P0.1 e P0.2 em commits separados ("não misturar itens num mesmo commit/task").
2. Decisão de escopo de lançamento (piloto gratuito vs MVP pago) — bloqueia a ordem do resto.
3. Se piloto: atacar P1 de Assessment (emissão de certificado + relação `Certificate::course()`
   com coluna certa) + LGPD-03 (uniques tenant-scoped) + `LessonPolicy`/`QuizAttemptPolicy` restantes.

## Decisões abertas

- **Escopo de lançamento:** piloto gratuito controlado ou MVP comercial pago?
- Para o caminho pago: gateway inicial, política de reembolso, modelo de reconciliação.
- Dívidas transversais nos `tasks.md`/roadmap: teto RBAC, uniques tenant-scoped (LGPD-03), CI,
  LGPD operacional (LGPD-01/02/05/07/08), PHPStan/advisories, fronteiras cross-module,
  isolamento de tenant por convenção manual.

## Último commit

- `ca8f9fb` (P0.1 + P0.2 ainda não commitados — working tree).
- Branch `harness/specs-foundation`.
