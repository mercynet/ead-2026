# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — **Migração modular concluída (item B), AINDA NÃO COMMITADA** (working tree sobre
  `67626f8`): código movido para `app/Modules/{Core,Learning,Assessment}` + `app/Shared`
  (ApiContext, base Controller, exceptions); middleware de tenancy em `Core/Http/Middleware`.
  Gates e rotas por módulo em `Providers/<M>ServiceProvider` (registrados em
  `bootstrap/providers.php`); `routes/api.php` e `api.php.bak` removidos (`withRouting` sem `api:`).
  Factories: `protected $model` + `protected static string $factory` nos models (descoberta por
  convenção não cobre `App\Modules\*`). Learning controllers limpos (queries/abort/try-catch →
  Actions novas: `GetCourseAction`, `DeleteCourseAction`, `GetCategoryAction`,
  `DeleteCategoryAction`, `GetLessonAction::progressFor`); autorização unificada em
  `Gate::forUser()->authorize`. **`ControllerLeannessTest` destravado** (sem skip) e
  **`ModuleBoundaryTest` criado**: shared kernel = `Core\Models|Enums` importável por todos;
  Core não importa módulos; dívida Eloquent cross-module congelada em allowlist
  (`User→QuizAttempt/Certificate`, `Course→Certificate`, Assessment→Learning models).
  Spec `backend-patterns.md` e `AGENTS.md` atualizados (migração concluída + exceção shared kernel).
  `phpstan.neon`: ignores re-apontados para paths novos.
- 2026-06-10 (cont.) — **Migrations por módulo**: movidas para
  `app/Modules/<M>/Database/Migrations` (`loadMigrationsFrom` no provider); framework/pacotes e
  plugins (até existir `Ecosystem`) seguem em `database/migrations/`. `make:migration` de módulo
  exige `--path`. Decisões do Paulo: manter `Http/` aninhado; granularidade segue bounded context
  (`Learning`, não `Courses`). **Skills consolidadas**: canônicas em `.agents/skills/`,
  `.claude/skills/` é 100% symlink; adotadas do catálogo tech-leads-club (adaptadas):
  `create-adr` (saída em `docs/specs/00-architecture/decisions/`) e `coupling-analysis`
  (auditoria pontual; grafo de partida = allowlist do `ModuleBoundaryTest`).

## Próximos passos (1-3)

1. **Commitar a migração** (working tree completo; sugerir 1 commit `refactor(arch)` ou split
   move/extração) — nada foi commitado ainda.
2. Converter a dívida da allowlist do `ModuleBoundaryTest` em Events/Contracts (começar pelas
   relações inversas `User→QuizAttempt/Certificate` e `Course→Certificate`, que invertem o grafo).
3. Slices TDD (item C) via skills `vertical-slice` + `pest-api-tests`.

## Decisões abertas

- **Catálogo tech-leads-club/agent-skills**: parecer dado (adotar no máximo `coupling-analysis`
  one-off e `create-adr` adaptada para `docs/specs/00-architecture/`); aguarda decisão do Paulo.
- **Nomenclatura policy-backed** (`core.users.show`, `learning.catalog.courses.{list,show}` fora
  do config) segue dívida — cuidado com bypass do `Gate::before` ao renomear.

## Observações operacionais

- `composer analyse` **não era verde antes da migração**: HEAD tinha 161 erros phpstan
  (level 5, dívida de tipos pré-existente); pós-migração = 156. Não tratar como regressão.
- Suíte: **110 passed, 0 skipped (372 asserts)**; Architecture: 14 passed (zero skips).
- Pint formatou `bootstrap/cache/*.php` (gitignored, regenerável — irrelevante).

## Último commit

- `67626f8` (HEAD; **migração modular inteira está só no working tree**) —
  branch `harness/specs-foundation`, sincronizada com origin antes desta sessão.
