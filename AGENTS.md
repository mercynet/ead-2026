# AGENTS.md

Compact repo guidance for OpenCode agents. Prefer executable config over prose when anything conflicts.

## Stack & docs

- Laravel 12 API app; local Boost reports PHP 8.4.1, Pest 4, PHPUnit 12, Pint 1, Sanctum 4, Tailwind CSS 4.
- Use Laravel Boost MCP tools for Laravel/package docs and app inspection; `opencode.json` starts it with `php artisan boost:mcp`.
- Domain specs live in `docs/specs/` and are the business source of truth. Start at `docs/specs/README.md` (canonical index). Cross-cutting rules in `00-architecture/`; each domain folder (`10-core-identity`, `20-catalog-learning`, `30-assessment`, `40-financial`, `50-ecosystem-plugins`) has `spec.md` (durable contract — no status) + `tasks.md` (mutable status) + `subspecs/`. Cross-domain roadmap in `docs/ROADMAP.md`; current session in `docs/STATE.md`.
- `README.md` is mostly upstream Laravel boilerplate; only its QA gate note is project-specific.

## Commands

- Full gate: `composer qa:gate` (or `./vendor/bin/sail composer qa:gate`). It runs strict composer validation, clears config, `migrate:fresh --env=testing`, Pint, `git diff --exit-code`, PHPStan, PHP Insights, then compact tests.
- Because the QA gate runs Pint before `git diff --exit-code`, it fails if formatting changed files; inspect/keep those changes rather than rerunning blindly.
- Focused tests: `php artisan test --compact tests/Feature/Path/Test.php` or `php artisan test --compact --filter=testName`.
- Normal test script: `composer test` clears config then runs `php artisan test`.
- Static/style shortcuts: `composer analyse`, `composer insights`, `vendor/bin/pint --dirty --format agent` for modified PHP files.
- Frontend: `npm run dev`, `npm run build`; all-in-one dev loop is `composer dev` (server, queue listener, pail logs, Vite via concurrently).
- API docs: `composer docs` / `php artisan scribe:generate`.

## Test environment gotchas

- `phpunit.xml` sets `DB_DATABASE=testing` but does not override `DB_CONNECTION`; `.env.example` uses `mariadb`, so local focused tests may require an existing `testing` database.
- Testing uses array cache/session/mail, sync queue, and disables Pulse/Telescope/Nightwatch.
- Tests are Pest feature-heavy under `tests/Feature/Api/...`; activate the `pest-testing` skill before writing or changing tests.

## Architecture patterns to preserve

- API routing is registered from `routes/api.php`; route prefixes there start with `v1/...`, which Laravel exposes as `/api/v1/...`.
- Middleware aliases, exception rendering, and routing are configured in `bootstrap/app.php` (Laravel 12 structure; no `app/Http/Kernel.php`).
- Tenant/request context is central: routes usually combine `resolve.tenant.optional`, `api.context`, `tenant.required.unless.developer`, `auth:sanctum`, and `tenant.access`.
- Controllers under `app/Http/Controllers/Api/V1/...` should stay thin: authorize with Gates/Policies, accept FormRequests and `App\Http\Context\ApiContext`, call `app/Actions/...`, return API Resources or small JSON envelopes.
- Business logic belongs in domain actions under `app/Actions/{Core,Learning,Assessment,...}`; follow sibling action names (`List*`, `Show*`, `Store*`, `Update*`, `Delete*`).
- Models currently live flat in `app/Models/` (not domain subdirectories). Policies are in `app/Policies/`.
- API success payloads generally use Laravel Resources (`app/Http/Resources/...`) and therefore wrap in `data`; small command responses may return `new JsonResponse(['data' => ...])`.
- Custom exception responses from `bootstrap/app.php` use `{ "data": null, "errors": [{ "code": "...", "message": "..." }] }` for 401/403/404/422; do not use the older `{ error: ... }` shape.

## Frontend

- Vite only builds `resources/css/app.css` and `resources/js/app.js`; Tailwind is wired through `@tailwindcss/vite`.
- Activate `tailwindcss-development` before styling/Tailwind work.
