---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-07-11
---

# Multi-Tenancy

## Resolução de Tenant

O tenant é resolvido por middleware, na seguinte ordem de prioridade:

1. **Header `X-Tenant-ID`** — padrão usado pelos frontends (SPA principal, apps mobile).
2. **Header `X-Tenant-Domain`** — alternativa por domínio explícito.
3. **Host da requisição** — o middleware extrai o host e busca na tabela `tenants`
   (padrão para white-labels finais com DNS customizado).

Apenas tenants com `is_active = true` são resolvidos. Tenant inativo não resolve.

Tecnicamente usa-se `spatie/laravel-multitenancy`, atrelando a connection/database conforme o
escopo, ou escopando no banco único via `tenant_id`.

## Isolamento

`tenant_id` é a âncora de isolamento. Identidade é **tenant-scoped**: um `student` registrado no
Tenant A não existe no Tenant B — a mesma pessoa em dois tenants são registros independentes (sem
pool global de usuários). Ver [`../10-core-identity/subspecs/users.md`](../10-core-identity/subspecs/users.md).

| UserType | Vê outros tenants? |
|----------|--------------------|
| developer | Sim (todos) |
| admin | Não (só o próprio) |
| instructor | Não (só o próprio) |
| student | Não (só o próprio) |

Exceção: `developer`/landlord tem `tenant_id` nulo e enxerga todos os tenants.

## Escopo em Queries

Filtragem por tenant é **explícita** em cada query — `where('tenant_id', ...)` no call site.
Não usamos global scope (trait `BelongsToTenant`): o tenant vive no `ApiContext` da request,
e developer/jobs/console operam sem tenant — bypass implícito seria falha silenciosa.
Decisão registrada em [ADR-004](decisions/004-tenant-scoping-where-explicito.md).

```php
Course::query()->where('tenant_id', $context->tenant->id)->get();
```

Enforcement executável: `tests/Architecture/TenantScopingTest.php` (scan estático — arquivo
que consulta model tenant-scoped precisa referenciar `tenant_id`, allowlist para exceções)
+ `tests/Architecture/TenantIsolationSmokeTest.php` (probe HTTP end-to-end).

## Stack de Middleware

Ordem típica aplicada nas rotas (ver `routes/api.php`):

| Middleware | Papel |
|------------|-------|
| `resolve.tenant` / `resolve.tenant.optional` | Resolve tenant (obrigatório / aceita null) |
| `api.context` | Injeta o Value Object `ApiContext` ($user + $tenant) |
| `tenant.required.unless.developer` | Exige tenant resolvido, exceto para `developer` |
| `auth:sanctum` | Autenticação por token opaco |
| `tenant.access` | Verifica que o usuário pertence/tem acesso ao tenant resolvido |

Rotas públicas (ex.: `GET /tenant/config`, verificação de certificado) ficam fora de
`auth:sanctum`. Login fica antes de `auth:sanctum` mas dentro de `resolve.tenant.optional`.
