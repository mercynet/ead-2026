<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. You MUST follow them closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below:

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

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain:

- `pest-testing` — Tests applications using the Pest 4 PHP framework.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities.

## Conventions

- You must follow all existing code conventions used in this application.
- Use descriptive names for variables and methods.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

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

## Searching Documentation

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel ecosystem packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

## Enums

- Typically, keys in an Enum should be TitleCase.

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

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.).
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input.

## Database

- Always use proper Eloquent relationship methods with return type hints.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers.
- Tenant preconditions must be validated in FormRequest / middleware, not with inline `if` blocks in controller methods.
- Reuse shared API context trait methods for tenant/user context access instead of manual request parsing.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files.

## Testing

- When creating models for tests, use the factories for the models.
- When creating tests, make use of `php artisan make:test --pest {name}` to create a feature test.

## Vite Error

- If you receive a Vite error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php`.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column.
- Laravel 12 allows limiting eagerly loaded records natively.

### Models

- Casts can and should be set in a `casts()` method on a model rather than the `$casts` property.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- DO NOT delete tests without approval.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.

=== project/architecture rules ===

# Project Architecture

This is a multi-tenant EAD (E-learning) platform with specific architectural patterns that MUST be followed.

## REGRAS DE NEGÓCIO FUNDAMENTAIS

### Multi-Tenancy e Usuários

- **Developers** (equipe): acesso total, CRUD completo para todos os tenants
- **Tenant Admins**: mesmo que developer, mas isolado em seu tenant, sem saber da existência de outros
- **Instructors**: criam ambiente pedagógico (cursos, módulos, aulas, questionários)
- **Students**: consomem cursos, vêem tudo que é seu
- **REGRA CRÍTICA**: Usuários são identificados por CPF único, mas login é por email
- Ao matricular: buscar por CPF primeiro, se existir em outro tenant, reutilizar
- Instrutor só vê alunos matrículados em seus cursos

### Categorias

- **Sistema** (globais): criadas/editadas apenas por developers, todos tenants podem usar
- **Custom** (do tenant): CRUD livre pelo tenant
- Categoria de sistema: nunca pode ter nome duplicado por tenant
- Categorias custom podem ser duplicadas entre tenants diferentes

### Questionários e Questões

- Questões são banco independente, reaproveitáveis em múltiplos questionários
- Questões podem ter múltiplas categorias (opcional)
- Uma questão usada em tentativa **não pode mais ser editada** (gera snapshot)
- Cada tentativa gera snapshot de todas as questões respondidas

### Certificados

- Por curso, não por aula
- Critérios configuráveis na tabela courses:
  - certificate_enabled
  - certificate_min_progress (%)
  - certificate_requires_quiz
  - certificate_min_score (%)

### Reassistir Aulas

- Aula concluída pode ser reassistida
- Não muda status de "concluída"
- Cada visualização gera registro em lesson_views (para estatísticas)

### Eventos para Estatísticas

- Todos os eventos significativos devem ser Disparados via Laravel Events
- Processados por Queue (RabbitMQ) para MariaDB de estatísticas
- Dados históricos nunca se perdem

## Specs

Read `docs/specs/*.md` for detailed domain specifications before implementing features.

## ApiContext Pattern

All controllers and actions MUST use `ApiContext` for accessing user and tenant data. NEVER access request attributes directly.

```php
// CORRECT - Inject ApiContext
public function index(ApiContext $context): JsonResponse
{
    Gate::forUser($context->user)->authorize('permission', [$context->tenant]);
    return Resource::collection($this->action->handle($context))->toResponse(request());
}
```

## Middleware Order

The middleware order matters for ApiContext to work correctly:
1. `resolve.tenant.optional` - Resolves tenant from header/host FIRST
2. `api.context` - Injects ApiContext with resolved tenant and user

## Response Pattern

- Use `->toResponse(request())` for Resources
- For manual payloads (login, logout), use `new JsonResponse(['data' => ...])`.
- Do NOT use `->resolve()` on Resources.
- Do not return empty `meta` blocks (`'meta' => []`).

## Actions

Actions receive `ApiContext` as parameter, not individual User/Tenant objects.

## Exceptions

Domain exceptions are rendered centrally in `bootstrap/app.php`. Throw exceptions, do NOT return error payloads inline.

## Artisan Commands

This project uses Laravel Sail. Always use `sail artisan` instead of `php artisan`.

## Directory Structure

- Controllers: `app/Http/Controllers/Api/V1/<Domain>/...`
- Actions: `app/Actions/<Domain>/<Resource>/...`
- Resources: `app/Http/Resources/<Domain>/...`
- Requests: `app/Http/Requests/<Domain>/...`
- Policies: `app/Policies/...`
- Exceptions: `app/Exceptions/...`
- Events: `app/Events/...`
- Context: `app/Http/Context/ApiContext.php`
- Middleware: `app/Http/Middleware/...`
- Specs: `docs/specs/*.md`

## Authentication

- Login uses rate limiting: `throttle:5,1` (5 attempts per minute)
- Token names include device type
- Inactive tenants cannot authenticate

</laravel-boost-guidelines>
