# AGENTS.md — Contrato Canônico

Fonte única de verdade para **qualquer agente** (Claude Code, OpenCode, etc.) trabalhando neste
repositório. Tool-agnóstico de propósito: outros agentes validam o trabalho lendo este contrato e
rodando os invariantes. Prefira config executável a prosa quando houver conflito.

## Princípio central: 100% API-first

> Este repositório é **somente backend** — uma API REST JSON. **Não existe frontend de produto
> aqui** (nada de Blade/SPA do produto). Qualquer consumidor — SPA, app mobile, integração de
> terceiro — constrói o próprio frontend como quiser. **A superfície do produto é a API e o seu
> contrato**: versionado em `/api/v1`, documentado via Scribe. Toda decisão favorece o consumidor de
> API — respostas consistentes (envelope/Resource), versionamento estável, contrato sempre documentado.

## Fontes de verdade (nesta ordem)

1. Este arquivo — contrato do projeto.
2. `docs/specs/` — regras de negócio por domínio. Comece em `docs/specs/README.md`.
   Cross-cutting em `docs/specs/00-architecture/`; cada domínio tem `spec.md` (contrato, sem
   status) + `tasks.md` (status) + `subspecs/`.
3. Código + `bootstrap/app.php` + `routes/api.php`. **Código vence prosa**; se conflitar, corrija a spec.

`docs/ROADMAP.md` = fases cross-domain. `docs/STATE.md` = sessão atual / próximos passos.
Idioma: specs/discussão em **PT-BR**; identificadores, permissions, código em **inglês**.

## Stack

- PHP 8.4 + Laravel 12 (estrutura streamlined; middleware/exceptions/routing em `bootstrap/app.php`, sem `app/Http/Kernel.php`).
- Sanctum 4 (token opaco), spatie: permission / multitenancy / medialibrary / activitylog / query-builder, staudenmeir/eloquent-has-many-deep.
- Pest 4, Pint, Larastan, PHP Insights, Scribe.
- MySQL 8 (+ MariaDB stats / Redis cache / RabbitMQ filas — planejados).
- **Upgrade diferido**: Laravel 13 + PHP 8.5 + pacotes quando o ecossistema suportar `^13` (hoje todas as deps travam `^12`). Ver `docs/ROADMAP.md`.

## Execução (Docker)

App roda no container `ead2026-laravel.test-1` (`/var/www/html`). Comandos via `docker exec`:

```bash
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Feature --compact --filter=<nome>
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact
docker exec ead2026-laravel.test-1 vendor/bin/pint --dirty --format agent   # antes de finalizar PHP
docker exec ead2026-laravel.test-1 composer analyse        # phpstan/larastan
docker exec ead2026-laravel.test-1 composer insights       # thresholds mínimos
docker exec ead2026-laravel.test-1 composer qa:gate        # gate completo
docker exec ead2026-laravel.test-1 php artisan scribe:generate
```

`qa:gate` roda Pint **antes** de `git diff --exit-code` → código não-formatado falha o gate
(formate antes; não re-rode às cegas). Banco de teste: `testing` / conexão `mysql` (fixado em `phpunit.xml`).

## Arquitetura

**Modular monolith por bounded context** + **ports/adapters seletivo**. Detalhe em
`docs/specs/00-architecture/backend-patterns.md`. Resumo:

- Código por módulo em `app/Modules/{Core,Learning,Assessment,Financial,Ecosystem}`; `app/Plugins/`
  (first-party), `app/Shared/`, `app/Support/Ports/` (+ adapters).
- Fluxo: `Route → Controller (fino) → Action → Model → Resource`.
- Ports/adapters **só** em 3 costuras: `PaymentGateway`, `MediaProvider`, `Plugin`. Resto: Eloquent direto.

**Estado atual:** o código vive em `app/Modules/{Core,Learning,Assessment}` + `app/Shared`;
cada módulo registra gates e rotas no seu `Providers/<M>ServiceProvider` (não há `routes/api.php`
global). `config/permissions.php`, `config/lgpd.php` e a suite `tests/Architecture` existem e
rodam **sem skips** — incluindo `ModuleBoundaryTest` (fronteira + shared kernel `Core\Models|Enums`;
dívida Eloquent cross-module congelada em allowlist) e `ControllerLeannessTest`. Ainda **alvo a
construir**: `app/Plugins/`, `app/Support/Ports/` (+ adapters) e os módulos `Financial`/`Ecosystem`.

## Invariantes não-negociáveis

1. **Controller fino**: injeta `App\...\ApiContext`, autoriza, chama Action, devolve Resource.
   Sem query, sem `where('tenant_id')`, sem regra, sem `try/catch` morto, sem FQCN inline.
2. **Um estilo de autz**: `Gate::forUser($ctx->requiredUser())->authorize($ability, ...)`. Nada de `Gate::check(){abort(403)}`.
3. **Action layer**: um `handle()`; `fill()`+`$fillable`; dependências injetadas (testável); sem facade estática em regra.
4. **API Resources sempre**; sucesso embrulha em `{data}`. Erro = `{"data":null,"errors":[{"code","message"}]}` (render central em `bootstrap/app.php`) para 401/403/404/422.
5. **Tenant scope** via `spatie/laravel-multitenancy` — nunca `where('tenant_id')` na mão.
6. **Permissions canônicas em `config/permissions.php`** (`permission => {label, user_types}`); seeder e Gates **derivam** dele. Nome: `domain.resource.action`.
7. **Dinheiro em cents inteiro, nunca float.**
8. **Listagens usam `cursorPaginate`**; eager-load contra N+1.
9. **PII/LGPD**: campos sensíveis (`cpf`, `email`, …) auditados via activitylog; registrar novo PII (`config/lgpd.php`). Ver `00-architecture/security-privacy-lgpd.md`.
10. **Scribe `@unauthenticated`** bate com o middleware real da rota.
11. **Cross-module só via Domain Events ou Contracts** — um módulo não importa interno de outro.
12. **API-first**: nenhuma rota de produto renderiza view/HTML; toda saída é JSON (Resource/envelope).
    O contrato `/api/v1` é versionado e documentado (Scribe) — frontend é externo, não vive neste repo.

Cada invariante tem (ou terá) um teste em `tests/Architecture` como árbitro executável.

## Testes (TDD)

Pirâmide unit/feature/e2e + architecture — detalhe e "onde cada nível cabe" em
`docs/specs/00-architecture/testing-strategy.md`. Teste antes da implementação; cada task ↔ ≥1 teste.
Helpers em `tests/Pest.php` (`actingAsUserType`, `tenantHeaders`, `assertApiErrorEnvelope`,
`assertTenantIsolation`) cortam o boilerplate. Suites: `Unit`, `Feature`, `E2E`, `Architecture`.

## Convenções de trabalho

- **Laravel Boost MCP** (`search-docs`, `tinker`, `database-schema`, …) para docs e inspeção; não chute API de pacote.
- `php artisan make:` para gerar arquivos; `--no-interaction`.
- FormRequest para validação **e** filtros de listagem (com `queryParameters()` p/ Scribe).
- **Skills sob demanda** (planejar / construir / testar / auditar) — não sempre-ligadas.
  Home canônico **tool-agnóstico**: `.agents/skills/<nome>/SKILL.md` (fonte única; o `description:`
  do frontmatter é o catálogo — **não** mantenha lista de skills aqui, evita drift). Cada ferramenta
  resolve a mesma dir via symlink: Claude Code (`.claude/skills/`), Codex (`.codex/skills/`),
  OpenCode (`.opencode/skills/`) → `.agents/skills/` (symlink **da dir inteira**, não por-skill).
  Ferramenta sem auto-descoberta: escaneie `.agents/skills/*/SKILL.md` e abra o relevante pelo
  `description:`. Skill nova: só criar `.agents/skills/<nome>/SKILL.md` — os três a herdam sozinhos,
  sem passo manual de symlink.
- **Economia de modelo (não gastar token à toa)**: tarefa **mecânica e bem especificada** vai para
  **subagente de modelo barato/rápido** (ex.: Haiku) — boilerplate (FormRequest, Resource, factory,
  seeder), rascunho de docs/skills, varreduras de arquivos, renomeações repetitivas. O modelo
  principal (caro) faz o que exige julgamento: arquitetura, revisão do rascunho contra o repo,
  decisões de domínio, debugging não-trivial. Regra prática: **barato rascunha, caro revisa** —
  nunca commitar saída de subagente barato sem revisão.
- **Ao fim de cada task**: rode a skill `context-checkpoint` — atualiza `docs/STATE.md` (handoff de retomada) e recomenda `continue | clear | waiting_for_user`. Após um `clear`, reconstrua o contexto lendo `AGENTS.md` + `docs/STATE.md`.
- `env()` só em arquivos de config; no código use `config(...)`.
- Commits: Conventional Commits, atômicos; branch antes de mexer; commit/push só quando pedido.

## Reporte de status (disciplina)

O git é o árbitro. Ao reportar progresso:

- Distinga sempre **committed** (no HEAD) / **staged** / **working tree** / **pushed**. Não descreva
  trabalho local como se estivesse consolidado.
- Não use "pronto / completo / done" sem evidência: existe no git **e** sem `TODO`/`Needs Review`
  pendente. "Funciona" exige teste verde citado.
- Diferencie **contrato-alvo** de **estado atual** quando o código ainda não cumpriu a regra.
- Em checkpoints (antes de commit/PR ou fim de stage), vale uma tabela selada
  (confirmado / parcial / não confirmado / enganoso) com `arquivo:linha` — não a cada mensagem.

## Frontend / clientes

**Não há frontend de produto neste repo** (ver *Princípio central*). Consumidores são externos e
independentes (SPA, mobile, terceiros). Vite + Tailwind são scaffolding vestigial do Laravel —
**não são superfície de produto; não invista neles**. O "frontend" que importa é o **contrato da
API**: mantenha Scribe atualizado (`composer docs`) e o versionamento `/api/v1` estável e consistente.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
