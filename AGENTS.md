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

**Estado atual vs alvo (honesto):** o código hoje é flat (`app/Models/*`, `app/Actions/<Domain>`,
`app/Http/Controllers/Api/V1/<Domain>`) e **ainda não cumpre todas as invariantes**. São **alvo a
construir** (não existem ainda): a estrutura `app/Modules/*`, `config/permissions.php`,
`config/lgpd.php` e a suite `tests/Architecture`. As invariantes abaixo são o **contrato vinculante**;
o código é realinhado por slice e a dívida fica rastreada nos invariantes (como `todo`/`skip`).

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
- **Skills sob demanda** (planejar / construir / testar) — não sempre-ligadas.
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
