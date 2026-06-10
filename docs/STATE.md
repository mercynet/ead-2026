# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — Harness fechado (exceto `ModuleBoundaryTest`): 8 invariantes em `tests/Architecture`
  (7 pass + 2 todos + 3 skipped:debt), skills `spec-task-planning`/`vertical-slice`/`pest-api-tests`
  (rascunho Haiku, revisadas), regra de **economia de modelo** no `AGENTS.md` (barato rascunha,
  caro revisa). Avaliados repos ECC e graphify: ROI baixo, não adotar (já cobertos pelo harness).

## Próximos passos (1-3)

1. Corrigir drift do error-envelope (ver Decisões abertas) — destrava os 2 `->todo()` do
   `ErrorEnvelopeShapeTest`.
2. Migração modular `app/` → `app/Modules/*` (item B) — destrava `ModuleBoundaryTest` e a
   extração das Actions dos Learning controllers (`ControllerLeannessTest`).
3. Slices TDD (item C) via skills `vertical-slice` + `pest-api-tests`. RFCs por último.

## Decisões abertas

- **Drift do error-envelope:** só as 4 exceptions custom (bootstrap/app.php) emitem `{data,errors}`.
  Sanctum 401, Gate `AuthorizationException` 403 e `findOrFail` 404 vazam JSON default do Laravel.
  Decidir: registrar render handlers para `AuthenticationException`/`AuthorizationException`/
  `ModelNotFoundException` e padronizar `findOrFail` → `ResourceNotFoundException`.
- Sem outras bloqueantes.

## Último commit

- `cc8bd5b` — `docs(harness): add model-economy rule (cheap drafts, expensive reviews)` — branch
  `harness/specs-foundation` (local, não pushed; 6 commits à frente da última referência pushada).
  Architecture: 7 pass + 2 todos + 3 skipped (24 asserts).
