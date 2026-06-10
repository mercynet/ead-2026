# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — Harness completo (exceto `ModuleBoundaryTest`): 8 invariantes em `tests/Architecture`
  (11 pass + 3 skipped:debt), skills `spec-task-planning`/`vertical-slice`/`pest-api-tests`, regra
  de economia de modelo no `AGENTS.md`. **Drift do error-envelope corrigido** (`b9a5e72`): 401/403/404
  framework agora emitem `{data,errors}` em api/* — handlers miram as classes Symfony (Handler
  converte Illuminate antes dos callbacks). Suíte completa: 107 passed, 3 skipped.
  Repos ECC e graphify avaliados a pedido: **ROI baixo, não adotar** (harness próprio já cobre).

## Próximos passos (1-3)

1. **Migração modular** `app/` → `app/Modules/*` (item B do plano; ver
   `docs/specs/00-architecture/backend-patterns.md`) — task grande, começar com contexto limpo.
   Destrava `ModuleBoundaryTest` e a extração das Actions dos Learning controllers
   (`ControllerLeannessTest`, hoje skip:debt).
2. Sanar dívidas marcadas: `ControllerLeannessTest` (Learning controllers gordos),
   `ScribeAuthAnnotationMatchesMiddlewareTest` (auditar `@unauthenticated`), `PiiAuditTest`
   (criar `config/lgpd.php` + `LogsActivity` no User).
3. Slices TDD (item C) via skills `vertical-slice` + `pest-api-tests`. RFCs por último.

## Decisões abertas

- Nenhuma bloqueante. (Error-envelope resolvido em `b9a5e72`.)

## Observações operacionais

- Banco `testing` corrompeu uma vez (migrations table sumida) — fix:
  `docker exec ead2026-laravel.test-1 php artisan migrate:fresh --database=mysql --env=testing --force`.

## Último commit

- `90bcbc1` (topo; último substantivo: `b9a5e72` — envelope fix) — branch `harness/specs-foundation`,
  local, ahead 7+ do origin; push só quando o usuário pedir.
  Architecture: 11 pass + 3 skipped. Suíte completa: 107 passed, 3 skipped (365 asserts).
