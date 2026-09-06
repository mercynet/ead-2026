---
domain: core-identity
last-updated: 2026-09-05
---

# Tasks — Core & Identity (inclui RBAC)

Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

> `[x]` registra slice entregue; não é promoção automática para `RUNTIME_VERIFIED`. O delta aberto
> fica exclusivamente em `Pending`.

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
- [x] Guard universal de área com correspondência exata de persona, cobertura arquitetural de todas
  as rotas área-first existentes e remoção do bypass implícito de `developer`.
  `Journey: FOUNDATION-0 | Area: neutral | Depends on: none`
- [x] Teto efetivo de permissions por `UserType`, derivado da matriz canônica, aplicado a grants de
  role/direct permission, fail-closed para permissions não registradas e protegido por invariantes.
  `Journey: FOUNDATION-0 | Area: neutral | Depends on: none`

### Core endpoints
- **Superfície canônica WS1:** os endpoints de autenticação pública usam `/api/v1/auth/*`.
  `/api/v1/core/auth/*` permanece como compatibilidade legacy durante a v1, com comportamento,
  middleware e throttling equivalentes.
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
- [x] **`MZRT-SKELETON-STATUS`** `PATCH /api/v1/mzrt/tenants/{tenant}/status`: `core.tenants.update-status`; somente
  `developer` via `auth:sanctum` + `area.guard:mzrt`, sem `tenant.required`, `tenant.access` ou
  header de tenant. Aceita `status` (`active|suspended`), persiste em `is_active`, é idempotente e
  Resource expõe `status`, nunca `is_active`. Cobrir 403 por persona/permission, 422 inválido, 404
  inexistente e envelopes; suspensão bloqueia novo login e token tenant-bound sem afetar outro
  tenant, reativação restaura acesso; incluir auditoria, Scribe e Feature tests. E2E fica no
  fechamento da jornada.
  `Journey: MZRT-SKELETON | Area: mzrt | Depends on: FOUNDATION-0`
- [x] **`MZRT-SKELETON-CREATE`** `POST /api/v1/mzrt/tenants`: cria tenant, primeiro admin e role
  e preset `cash` atomicamente via `ProvisionTenantAction` compartilhada com `tenant:provision`;
  senha obrigatória não retorna; domínio duplicado, inclusive corrida controlada, retorna
  `409 tenant_already_exists`. Rollback após falha de admin, role ou participante Ecosystem coberto;
  colisão única do participante propaga sem retry. Evidência: TenantCreateApiTest 11/109,
  TenantProvisionCommandTest 13/72, Architecture 20/688 e Larastan 370 arquivos sem erros.
  `Journey: MZRT-SKELETON | Area: mzrt | Depends on: MZRT-SKELETON-STATUS`
- [x] **`MZRT-SKELETON-E2E`** E2E HTTP real completo: create → `cash`/entitlements → admin login
  → suspend → login/token negados → reactivate → login/token restaurados. Runner suporta método/path
  por caso, captura segura e requests sem header automático; teardown remove fixtures e auditoria
  sem tocar registros alheios. Evidência histórica: 9/9 casos e zero resíduos no DB `ead2026_e2e`.
  Evidência runtime atual (2026-09-05): receipt `docs/reports/MZRT-CLOSURE-SLICE-2-2026-09-05.md`,
  9/9 casos contra app viva em ambiente `e2e`, com auth canônica `/api/v1/auth/*`, side effects,
  redaction, auditoria de status e zero resíduos confirmados.
  `Journey: MZRT-SKELETON | Area: mzrt | Depends on: MZRT-SKELETON-ENTITLEMENTS`

### Slices entregues posteriormente

- [x] Migration: trocar `unique` global de `cpf`/`email` por compostos `(tenant_id, cpf)` e `(tenant_id, email)` — alinha ao modelo tenant-scoped. Login agora é tenant-scoped (fallback developer global); usuário de tenant sem `X-Tenant-ID` → 401 genérico.
- [x] Padronizar permission de `GET /users/{id}` para `core.users.view` (canônico); `core.users.show` removido do gate/controller (nunca existiu em `config/permissions.php`).
- [x] `PATCH /api/v1/core/users/me/password`: revoga as **outras** sessões na troca, preservando a atual (reset por token revoga todas).
- [x] Rate limiters nomeados e separados por rota (login/forgot/reset/invitation-accept/invitation-create) — corrige bucket `domínio|IP` compartilhado do throttle padrão (SEC-001).
- [x] `E2eRunCommand` endurecido: gate de ambiente (local|testing|e2e), timeout por request, circuit breaker no 5xx inesperado (`--continue-on-error`), cleanup surfacing e sanitização de token no output (AUD-001, escopo proporcional).
- [x] Isolamento E2E: gate de DB descartável (recusa dev salvo `--force-db`), canário servidor↔DB antes de mutar, `--fresh`, `.env.e2e.example` + DB `ead2026_e2e` provisionado. Fecha o gate "stack E2E dedicada" da auditoria.
- [x] **ADMIN Identity & Onboarding Surface** — `GET /api/v1/admin/users`, `GET /api/v1/admin/users/{id}` e `POST /api/v1/admin/invitations`; list/show/invite canônicos com guard Admin, tenant isolation, RBAC, compatibilidade legacy e E2E HTTP 16/16. `core.users.view-check` mantém a policy de detalhe ativa contra o short-circuit do Gate. Evidência: `RUNTIME_VERIFIED` para a jornada do slice; o guardrail automático do diff permanece pendente por falha ambiental do wrapper Sail. `Journey: ADMIN-OPS | Area: admin | Depends on: FOUNDATION-0`
- [x] `PATCH /api/v1/admin/users/{id}` — re-slotado área-first (era `/core/users/{id}`): admin administra instructor/student do próprio tenant; `user_type`/`email`/`cpf`/`password` proibidos no payload; admin par → 403, developer e cross-tenant → 404. `Journey: ADMIN-OPS | Area: admin | Depends on: FOUNDATION-0`
- [x] `DELETE /api/v1/admin/users/{id}` — soft delete (`deleted_at` em `users`) + revogação das sessões Sanctum na mesma transação; login e token do excluído passam a falhar. E-mail permanece reservado no tenant (unique é sobre a linha). `Journey: ADMIN-OPS | Area: admin | Depends on: PATCH /api/v1/admin/users/{id}`
## In Progress

- _(nenhuma)_

## Pending

> Somente deltas ainda abertos permanecem aqui. Histórico entregue não é repetido nesta seção.

### RBAC
- [ ] Verificação de plugin permissions em runtime (`hasActivePluginPermission`).
- [ ] CRUD de roles de tenant (criar/editar/excluir roles com `scope = 'tenant'`, só tenant_admin).
- [ ] Remover código Spatie não utilizado.

### Core
- [ ] `GET /api/v1/core/tenant/config` (público, white-label).
- [ ] `PATCH /api/v1/core/tenant/config` (tenant_admin).
- [ ] Model `SystemSetting` + endpoints (developer only).
- [ ] Endpoints completos de `TenantCustomization` / `TenantIntegration`.
- [ ] Impersonação segura (tokens Sanctum com ability `impersonating`).

## Needs Review

- _(nenhuma)_ — slices históricos entregues foram movidos para *Done*; não há revisão pendente
  derivada desta reconciliação.

## Open Questions

- _(nenhuma)_ — identidade **resolvida** (2026-06-10): modelo **tenant-scoped**, `unique(tenant_id, cpf)`
  + `unique(tenant_id, email)`; sem pool global (diferido em `docs/ROADMAP.md`). Ver
  [`subspecs/users.md`](subspecs/users.md).
