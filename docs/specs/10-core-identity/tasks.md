---
domain: core-identity
last-updated: 2026-07-28
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
- [x] `POST /api/v1/core/auth/password/forgot` (público, tenant-scoped, throttle; resposta genérica anti-enumeração; notifica por e-mail com token opaco só-hash).
- [x] `POST /api/v1/core/auth/password/reset` (público; token-driven, expiry, uso único, falha genérica `password_reset_invalid`; revoga todas as sessões Sanctum do usuário).
- [x] ~~`POST /api/v1/core/users` (registro público)~~ **removido** — substituído por onboarding invite-only.
- [x] `POST /api/v1/core/invitations` (admin emite convite tenant-bound; token opaco 1x, rate limit 60/min).
- [x] `POST /api/v1/core/invitations/accept` (público; cria usuário+papel com tenant/email/role fixos; uso único, expiry, falha genérica; rate limit 10/min).
- [x] `GET /api/v1/core/users` (tenant-scoped; developer vê todos).
- [x] `GET /api/v1/core/users/{id}`.
- [x] `PATCH /api/v1/core/users/me`.
- [x] `PATCH /api/v1/core/users/me/password`.
- [x] Models `User`, `Tenant`, `TenantCustomization`, `TenantIntegration` (+ relacionamentos).
- [x] Command `tenant:provision` — bootstrap idempotente de tenant + primeiro admin (semeia RBAC;
  provisiona preset gratuito `cash` de confirmação manual via contrato Ecosystem; não duplica nem
  sobrescreve senha, ativação ou config existente; gera senha forte se omitida). Runbook do onboarding invite-only.

## In Progress

- _(nenhuma)_

## Pending

### RBAC
- [ ] Implementar "teto" de permissions baseado em UserType (clamp das permissions efetivas ao UserType).
- [ ] Verificação de plugin permissions em runtime (`hasActivePluginPermission`).
- [ ] CRUD de roles de tenant (criar/editar/excluir roles com `scope = 'tenant'`, só tenant_admin).
- [ ] Remover código Spatie não utilizado.

### Core
- [x] Migration: trocar `unique` global de `cpf`/`email` por compostos `(tenant_id, cpf)` e `(tenant_id, email)` — alinha ao modelo tenant-scoped. Login agora é tenant-scoped (fallback developer global); usuário de tenant sem `X-Tenant-ID` → 401 genérico.
- [x] Padronizar permission de `GET /users/{id}` para `core.users.view` (canônico); `core.users.show` removido do gate/controller (nunca existiu em `config/permissions.php`).
- [x] `PATCH /api/v1/core/users/me/password`: revoga as **outras** sessões na troca, preservando a atual (reset por token revoga todas).
- [x] Rate limiters nomeados e separados por rota (login/forgot/reset/invitation-accept/invitation-create) — corrige bucket `domínio|IP` compartilhado do throttle padrão (SEC-001).
- [x] `E2eRunCommand` endurecido: gate de ambiente (local|testing|e2e), timeout por request, circuit breaker no 5xx inesperado (`--continue-on-error`), cleanup surfacing e sanitização de token no output (AUD-001, escopo proporcional).
- [x] Isolamento E2E: gate de DB descartável (recusa dev salvo `--force-db`), canário servidor↔DB antes de mutar, `--fresh`, `.env.e2e.example` + DB `ead2026_e2e` provisionado. Fecha o gate "stack E2E dedicada" da auditoria.
- [ ] `PATCH /api/v1/core/users/{id}` (update por admin).
- [ ] `DELETE /api/v1/core/users/{id}`.
- [ ] `GET /api/v1/core/tenant/config` (público, white-label).
- [ ] `PATCH /api/v1/core/tenant/config` (tenant_admin).
- [ ] Model `SystemSetting` + endpoints (developer only).
- [ ] Endpoints completos de `TenantCustomization` / `TenantIntegration`.
- [ ] Impersonação segura (tokens Sanctum com ability `impersonating`).

## Needs Review

- _(nenhuma)_ — padronização de `GET /users/{id}` para `core.users.view` virou task em *Pending*.

## Open Questions

- _(nenhuma)_ — identidade **resolvida** (2026-06-10): modelo **tenant-scoped**, `unique(tenant_id, cpf)`
  + `unique(tenant_id, email)`; sem pool global (diferido em `docs/ROADMAP.md`). Ver
  [`subspecs/users.md`](subspecs/users.md).
