---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
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

`tenant_id` é a âncora de isolamento. Um `student` registrado no Tenant A não existe no Tenant B
(a menos que as instâncias compartilhem o mesmo pool de usuários base).

| UserType | Vê outros tenants? |
|----------|--------------------|
| developer | Sim (todos) |
| admin | Não (só o próprio) |
| instructor | Não (só o próprio) |
| student | Não (só o próprio) |

Exceção: `developer`/landlord tem `tenant_id` nulo e enxerga todos os tenants.

## Escopo em Queries

Filtragem por tenant deve usar scope/trait, não `where('tenant_id', ...)` espalhado:

```php
// preferir
User::query()->tenant($tenant)->get();

// evitar repetição manual
User::query()->where('tenant_id', $tenant->id);
```

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
