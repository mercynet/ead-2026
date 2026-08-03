---
name: api-area-routing
description: Coloca um endpoint na ÁREA certa com o guard, a stack de middleware e a superfície pública corretos (mzrt|admin|instructor|student|home). Ativa ao criar/mover endpoint, escolher prefixo de URL, mexer em arquivo de rota de módulo, tornar rota pública, ou quando AreaRouteGuardTest / RouteSecuritySurfaceTest / ScribeAuthAnnotationMatchesMiddlewareTest ficar vermelho. Não é a skill de implementar a fatia inteira (isso é vertical-slice) — é a de acertar a superfície.
---

# API Area Routing

Área = **audiência** da superfície (`UserType` que alcança). RBAC = **ação** dentro dela.
Errar a área não dá erro de sintaxe: dá persona vazando ou 3 invariantes vermelhas.

Contrato: `AGENTS.md` → *Áreas de API* + invariante 13. Semântica longa:
`docs/specs/00-architecture/areas-surfaces.md`. Aqui fica o **como fazer sem errar**.

## Quando usar

- Endpoint novo de produto (qualquer método).
- Mover escrita/leitura entre áreas (re-slot área-first).
- Tornar rota pública ou mexer em throttle de rota anônima.
- Debugar 403 `area_forbidden`, 401 inesperado, ou `tenant_not_resolved` (422) em rota nova.

## Passo 1 — decidir a área

`app/Modules/Core/Enums/Area.php` é a fonte:

| Área | `UserType` permitido | Auth | Contexto de tenant |
|------|----------------------|------|--------------------|
| `mzrt` | `developer` | obrigatória | **nenhum** (recurso da plataforma) |
| `admin` | `admin` | obrigatória | tenant do usuário |
| `instructor` | `instructor` | obrigatória | tenant do usuário |
| `student` | `student` | obrigatória | tenant do usuário |
| `home` | qualquer/anônimo | opcional | opcional |

Regras de decisão:

- **Quem é a audiência primária?** Não é "quem também poderia" — `developer` **não** consome payload
  de Admin por herança. Superfície de developer = área `mzrt`, com payload próprio.
- **O recurso é do tenant ou da plataforma?** Categoria de sistema → `mzrt`. Categoria do tenant → `admin`.
  Mesmo recurso pode ter as duas escritas, em áreas distintas (é o caso real de `categories`).
- **Leitura e escrita podem morar em áreas diferentes** — `GET` no catálogo, escrita em `admin`/`mzrt`.
- `instructor` ainda não tem rota nenhuma no repo; primeira rota da área cria o arquivo/grupo.

## Passo 2 — onde o arquivo vive

Módulo **dono do recurso**, um arquivo por área: `app/Modules/<M>/Routes/{api,admin,mzrt}.php`,
todos registrados no `Providers/<M>ServiceProvider::registerRoutes()` com
`Route::middleware('api')->prefix('api')->group(...)`. Arquivo novo = adicionar a linha lá, senão a
rota simplesmente não existe.

Duas formas equivalentes de nomear o prefixo (as duas passam o invariante, que lê a **URI final**):

- `Route::prefix('v1/admin')` — Core/Learning.
- `Route::prefix('v1')` + segmento no path (`/admin/orders/{id}/...`) — Financial/Ecosystem.

Prefira a primeira em arquivo dedicado de área; a segunda só quando o módulo tem poucas rotas de
áreas diferentes num único `api.php`.

## Passo 3 — copiar a stack certa

**Área tenant-scoped** (`admin`, `instructor`, `student`):

```php
Route::prefix('v1/admin')
    ->middleware([
        'resolve.tenant.optional',
        'api.context',
        'auth:sanctum',
        'area.guard:admin',
        'tenant.required.unless.developer',
        'tenant.access',
    ])
```

**Área `mzrt`** (global, sem tenant — não exige nem aceita contexto de tenant):

```php
Route::prefix('v1/mzrt')
    ->middleware(['auth:sanctum', 'area.guard:mzrt', 'api.context'])
```

O que cada peça faz (aliases em `bootstrap/app.php`):

- `resolve.tenant.optional` — resolve tenant do header `X-Tenant-ID` sem exigir.
- `api.context` — injeta `ApiContext` (user + tenant). **Sem ele o controller não tem contexto** e
  `requiredTenant()` estoura `tenant_not_resolved` (422).
- `area.guard:<área>` — `EnsureAreaAccess`; `AreaAccessDeniedException` → 403 `area_forbidden`.
- `tenant.required.unless.developer` / `tenant.access` — exigem e validam pertencimento ao tenant.

`EnsureAreaAccess` está em `prependToPriorityList(SubstituteBindings::class, ...)`: o **403 de área
vem antes** do 404 de route binding — persona errada não descobre se o id existe.

## Passo 4 — superfície pública (só quando for de propósito)

`RouteSecuritySurfaceTest` exige `auth:sanctum` em **toda** rota `api/v1`, exceto a allowlist
explícita **no próprio teste** — e o mesmo teste falha se a allowlist tiver entrada morta. Então:

- Rota pública nova = 1 linha na allowlist, **no mesmo commit**, com assinatura exata `MÉTODO uri`.
- Rota anônima que grava ou envia e-mail = rate limiter **nomeado** (`throttle:login`,
  `throttle:invitation-accept`, …), nunca o bucket anônimo compartilhado.
- Scribe: `@unauthenticated` só se a rota realmente não tem `auth:sanctum`
  (`ScribeAuthAnnotationMatchesMiddlewareTest` compara com o middleware real).

## Passo 5 — a área decide o escopo

Se a área já determina o escopo, o payload **não** pode redefini-lo: `is_system`, `tenant_id`,
`user_type` são campos **proibidos** no FormRequest da área (`prohibited`), não opcionais. Aceitar
"só pra flexibilidade" reabre por payload o que o guard fechou por rota.

## Checklist

- [ ] Área escolhida pela audiência primária, não por conveniência de reuso.
- [ ] Arquivo de rota da área existe e está no `registerRoutes()` do provider.
- [ ] Prefixo `v1/<área>` (ou segmento equivalente) bate com `area.guard:<área>` — exatamente um guard.
- [ ] Grupo pai **não** aplica outro `area.guard` (guard duplicado quebra o invariante).
- [ ] `api.context` presente; `mzrt` sem middleware de tenant; áreas de tenant com `tenant.access`.
- [ ] Rota pública → allowlist + throttle nomeado; anotação Scribe coerente.
- [ ] Campos que redefinem escopo marcados como proibidos no FormRequest.
- [ ] Permission da ação declarada e testada (skill `rbac-permission-wiring`).

## Verificar

```bash
./vendor/bin/sail artisan test --compact --testsuite=Architecture
./vendor/bin/sail artisan route:list --path=api/v1 --json   # conferir stack real da rota
```

Feature test mínimo da área: persona correta → 2xx; **cada** outra persona → 403 `area_forbidden`;
cross-tenant → 403/404 (`assertTenantIsolation`).

## Armadilhas vistas neste repo

- **Guard emprestado**: rota `/admin/...` dentro de grupo `area.guard:student` — passa nos testes de
  permissão e vaza a superfície. O invariante existe por isso.
- **Rota de produto em prefixo legado** (`v1/core`, `v1/learning`, `v1/assessment`): esses prefixos são
  domínio-first legado e **não** carregam guard de área — o invariante nem os cobre. Endpoint novo de
  produto não nasce lá; migração está no `ROADMAP.md`.
- **`mzrt` pedindo tenant**: adicionar `tenant.access`/`tenant.required...` numa rota `mzrt` transforma
  recurso de plataforma em recurso de tenant e passa a exigir `X-Tenant-ID`.
- **404 que denuncia existência**: negar por não-pertencimento tem que sair no envelope padrão de 404
  (`not_found`), igual a um 404 comum.
