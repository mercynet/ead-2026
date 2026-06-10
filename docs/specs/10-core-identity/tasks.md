---
domain: core-identity
last-updated: 2026-06-10
---

# Tasks — Core & Identity (inclui RBAC)

Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

### Infraestrutura
- [x] `ApiContext` Value Object + injeção via middleware (`api.context`).
- [x] Middleware de tenant: `resolve.tenant`, `resolve.tenant.optional`, `tenant.required.unless.developer`, `tenant.access`.
- [x] Exceptions de domínio com render centralizado (`TenantContextRequiredException`, `InvalidCredentialsException`, `ResourceNotFoundException`).
- [x] Response Pattern padronizado (`->toResponse(request())`, `JsonResource`).
- [x] Policies base (`UserPolicy`, `CategoryPolicy`).
- [x] Seeders: Roles, Permissions, usuários de desenvolvimento.

### RBAC
- [x] `UserType` enum (`developer|admin|instructor|student`) — `app/Enums/UserType.php`.
- [x] Migration: coluna `user_type` em `users`.
- [x] Migration: colunas `tenant_id` e `scope` em `roles`.
- [x] Métodos `isDeveloper()/isAdmin()/isInstructor()/isStudent()` no `User`.
- [x] Gate/Policy que verifica UserType.
- [x] RolesSeeder atualizado com UserTypes.

### Core endpoints
- [x] `POST /api/v1/core/auth/login` (rate limit 5/min, token com device type).
- [x] `POST /api/v1/core/auth/logout`.
- [x] `GET /api/v1/core/auth/me` (usuário + roles + permissions).
- [x] `POST /api/v1/core/users` (registro).
- [x] `GET /api/v1/core/users` (tenant-scoped; developer vê todos).
- [x] `GET /api/v1/core/users/{id}`.
- [x] `PATCH /api/v1/core/users/me`.
- [x] `PATCH /api/v1/core/users/me/password`.
- [x] Models `User`, `Tenant`, `TenantCustomization`, `TenantIntegration` (+ relacionamentos).

## In Progress

- _(nenhuma)_

## Pending

### RBAC
- [ ] Implementar "teto" de permissions baseado em UserType (clamp das permissions efetivas ao UserType).
- [ ] Verificação de plugin permissions em runtime (`hasActivePluginPermission`).
- [ ] CRUD de roles de tenant (criar/editar/excluir roles com `scope = 'tenant'`, só tenant_admin).
- [ ] Remover código Spatie não utilizado.

### Core
- [ ] Migration: trocar `unique` global de `cpf`/`email` por compostos `(tenant_id, cpf)` e `(tenant_id, email)` — alinha ao modelo tenant-scoped (corrige dívida de schema).
- [ ] `PATCH /api/v1/core/users/{id}` (update por admin).
- [ ] `DELETE /api/v1/core/users/{id}`.
- [ ] `GET /api/v1/core/tenant/config` (público, white-label).
- [ ] `PATCH /api/v1/core/tenant/config` (tenant_admin).
- [ ] Model `SystemSetting` + endpoints (developer only).
- [ ] Endpoints completos de `TenantCustomization` / `TenantIntegration`.
- [ ] Impersonação segura (tokens Sanctum com ability `impersonating`).

## Needs Review

- [ ] Permission de `GET /users/{id}`: padronizar para `core.users.view` (legado usava `core.users.show`).

## Open Questions

- _(nenhuma)_ — identidade **resolvida** (2026-06-10): modelo **tenant-scoped**, `unique(tenant_id, cpf)`
  + `unique(tenant_id, email)`; sem pool global (diferido em `docs/ROADMAP.md`). Ver
  [`subspecs/users.md`](subspecs/users.md).
