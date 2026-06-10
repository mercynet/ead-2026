# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — Meta-validação da auditoria externa + **fix do permission drift** (bug latente real):
  controllers usavam nomes fora do config (`learning.enrollment.view`, `learning.lesson.view`,
  `learning.lesson.progress`) que só funcionavam porque os testes semeavam as permissions na mão.
  Corrigido: controllers usam nomes canônicos; `learning.progress.update` criado no config;
  `learning.lessons.view` agora inclui `student`; **`RolesSeeder` deriva de `user_types` do config**
  (antes: listas hard-coded drifted — student não tinha `learning.enrollments.view`);
  `PermissionDriftTest` agora captura `->authorize('...')` e aceita abilities `Gate::define`d
  (scan do `AppServiceProvider`). Testes de Enrollment/Lesson migrados para helpers
  (`actingAsUserType`/`seedRbac`). `DbgTest` removido. `AGENTS.md` atualizado (permissions.php e
  `tests/Architecture` existem). Skills `pest-api-tests` (401/403 exemplos) e `vertical-slice`
  (claim do drift test + `requiredUser()`) corrigidas. Commit `1ee716d` (pushed).
- 2026-06-10 — **Dívida LGPD sanada**: `config/lgpd.php` criado (inventário PII canônico),
  `User` usa `LogsActivity` (logOnly derivado do config, logOnlyDirty), `PiiAuditTest`
  des-skipado e verde. Suíte completa: 107 passed, 2 skipped (367 asserts).

## Próximos passos (1-3)

1. **Migração modular** `app/` → `app/Modules/*` (item B; ver
   `docs/specs/00-architecture/backend-patterns.md`) — destrava `ModuleBoundaryTest` e a extração
   das Actions dos Learning controllers (`ControllerLeannessTest`, hoje skip:debt).
2. Sanar dívidas skip restantes: `ControllerLeannessTest` (depende da migração modular),
   `ScribeAuthAnnotationMatchesMiddlewareTest` (auditar `@unauthenticated`).
3. Slices TDD (item C) via skills `vertical-slice` + `pest-api-tests`.

## Decisões abertas

- **Nomenclatura policy-backed**: `core.users.show` e `learning.catalog.courses.{list,show}` são
  abilities via `Gate::define` com nomes fora do config — drift test as aceita via scan do provider.
  Unificar com o config é dívida; **cuidado**: renomear para um nome que alguma role tem como
  permission spatie faz o `Gate::before` dar bypass na policy (furo de tenant isolation).

## Observações operacionais

- Banco `testing` corrompeu uma vez (migrations table sumida) — fix:
  `docker exec ead2026-laravel.test-1 php artisan migrate:fresh --database=mysql --env=testing --force`.
- Derivar o seeder mudou conjuntos de roles: instructor ganhou categories CRUD, student ganhou
  enrollments.view/attempts/certificates e perdeu `core.users.view` — tudo conforme `user_types`
  do config (canônico); suíte verde confirma.

## Último commit

- `1ee716d` (âncora = commit anterior a este refresh; o commit do pacote LGPD inclui este STATE) —
  branch `harness/specs-foundation`, sincronizada com origin após push.
  Architecture: 12 pass + 2 skipped. Suíte completa: 107 passed, 2 skipped (367 asserts).
