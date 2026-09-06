---
domain: core-identity
maturity: stable
last-reviewed: 2026-07-29
owners: [paulo]
related:
  - ../00-architecture/rbac.md
  - ../00-architecture/multi-tenancy.md
  - ../00-architecture/api-conventions.md
  - ../00-architecture/security-privacy-lgpd.md
  - subspecs/auth.md
  - subspecs/users.md
  - subspecs/tenant-config.md
---

# Core & Identity

## Intent / Why

Este é o alicerce da plataforma: garante que usuários sejam identificados, autenticados,
autorizados e roteados para o tenant correto. Sem ele, nenhum outro domínio funciona — todo
acesso depende de um `ApiContext` resolvido (quem é o usuário, qual o tenant). O objetivo de
produto é permitir que cada cliente (tenant) opere uma instância isolada e white-label da
plataforma, com seus próprios usuários, marca e integrações.

## Overview

Responsável por Autenticação, Identidade do Usuário, Controle de Acesso (RBAC) e Multi-Tenancy.
Padrões transversais (ApiContext, Response, FormRequest, error envelope) estão em
[`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md); resolução e
isolamento de tenant em [`../00-architecture/multi-tenancy.md`](../00-architecture/multi-tenancy.md);
RBAC em [`../00-architecture/rbac.md`](../00-architecture/rbac.md).

Recursos detalhados nas subspecs:

- [`subspecs/auth.md`](subspecs/auth.md) — login, logout, me.
- [`subspecs/users.md`](subspecs/users.md) — usuários, perfil, senha, regra de CPF.
- [`subspecs/tenant-config.md`](subspecs/tenant-config.md) — white-label, integrações e gestão MZRT de tenants.

## Entities

| Model | Tabela | Invariantes |
|-------|--------|-------------|
| `User` | `users` | `tenant_id` nulo só para developer/landlord; `cpf`/`email` únicos **por tenant** (`unique(tenant_id, …)`); `user_type` (enum) define o teto de acesso. Roles/permissions via spatie/laravel-permission. |
| `Invitation` | `invitations` | Onboarding tenant-bound. `tenant_id`/`email`/`role` fixos na emissão; só o `token_hash` (SHA-256) é persistido; `expires_at` obrigatório; `accepted_at`/`accepted_by` marcam uso único. `role` ∈ {student, instructor} (nunca admin/developer). |
| `Tenant` | `tenants` (landlord) | `name`, `domain`, `database` nullable, `description` nullable, `is_active` e timestamps. `domain` resolve o tenant; somente tenants ativos resolvem. `is_active` é exposto pela API como `status` (`active` ou `suspended`). |
| `TenantCustomization` | `tenant_customizations` | Brand/white-label do tenant; lido por `GET /tenant/config` (público). |
| `TenantIntegration` | `tenant_integrations` | `credentials` sempre `encrypted:json`. |
| `SystemSetting` | `system_settings` | Config global da plataforma; só `developer` lê/escreve (`tenant_id` null = global). |

Detalhe colunar de cada entidade nas subspecs.

## Business Rules

- **Isolamento total por tenant.** `tenant_id` é a âncora. Ver
  [`../00-architecture/multi-tenancy.md`](../00-architecture/multi-tenancy.md).
- **Identidade tenant-scoped:** `cpf` e `email` únicos **por tenant**; login por email no contexto
  do tenant. Mesma pessoa em tenants distintos = registros independentes (sem pool global). Ao
  matricular, busca-se por CPF **dentro do tenant**. Detalhe em [`subspecs/users.md`](subspecs/users.md).
- **Onboarding é invite-only.** Não há auto-registro público. Um admin emite um convite
  tenant-bound (`POST /invitations`) para `student` ou `instructor`; o convidado o aceita
  (`POST /invitations/accept`) informando token + nome + senha. O usuário nasce com o tenant,
  email e papel **fixados no convite** — o corpo do aceite não os altera. Token opaco (só o hash
  é guardado), expira, uso único; token inexistente, adulterado, expirado ou já usado falha
  genericamente (`invitation_invalid`), sem enumerar convites. Admin só convida no próprio tenant
  (`tenant.access`).
- **UserType define o teto de acesso** e é imutável exceto por developer. Ver
  [`../00-architecture/rbac.md`](../00-architecture/rbac.md).
- **White-label inicializável sem login**: a SPA chama `GET /tenant/config` via host header para
  carregar marca/cores/links antes do login.
- **Impersonação segura** (diferida): tokens Sanctum com ability `impersonating`. Ver
  [`../00-architecture/security-privacy-lgpd.md`](../00-architecture/security-privacy-lgpd.md).
- **Gestão MZRT de status.** `PATCH /api/v1/mzrt/tenants/{tenant}/status` recebe
  `status ∈ {active, suspended}` e mapeia-o para `Tenant.is_active`; repetir estado atual é
  idempotente. O Resource expõe somente `status`, nunca `is_active`. Suspensão bloqueia novo login
  e uso de token tenant-bound daquele tenant, sem afetar outro tenant; reativação restaura ambos.
- **Provisionamento MZRT.** `POST /api/v1/mzrt/tenants` requer `developer` autenticado por
  `auth:sanctum` + `area.guard:mzrt`, sem contexto ou header de tenant. Recebe identidade do tenant
  e primeiro admin aninhado com senha obrigatória; cria ambos atomicamente, com rollback integral.
  Senha nunca integra resposta. A regra é compartilhada com `tenant:provision`. Domínio duplicado
  responde `409 tenant_already_exists`; não há chave de idempotência. Entitlements pertencem a
  Ecosystem; ver spec e tasks daquele domínio.

## Domain Boundaries

- **Emite**: contexto de autenticação/tenant consumido por todos os domínios via `ApiContext`.
- **Consome**: nenhum evento de outro domínio (é a base).
- `OrderPaidEvent` (Financial) pode resultar em criação/atualização de usuário durante matrícula
  automática, mas a identidade do usuário é resolvida por CPF (ver regra de CPF).

## Authorization

Regras e matriz completas em [`../00-architecture/rbac.md`](../00-architecture/rbac.md).
Permissions do domínio:

```
core.users.list · core.users.create · core.users.view · core.users.update
core.users.delete · core.users.update-self · core.users.update-password
core.invitations.create
core.tenants.create · core.tenants.update-status
```

## Events

- (Diferido) `UserRegistered` — para onboarding/notificações.

## Quick Reference

### Estado do contrato de autenticação

- **`TARGET_CANONICAL`:** `/api/v1/auth/*` — superfície pública canônica implementada no WS1.
- **`CURRENT_IMPLEMENTED` + `LEGACY_COMPATIBILITY`:** `/api/v1/core/auth/*` — permanece disponível
  durante a v1. Remoção futura exige inventário de consumidores e decisão explícita.

| Recurso | Endpoint | Permission |
|---------|----------|------------|
| Login | `POST /api/v1/auth/login` | público (rate limit 5/min) |
| Logout | `POST /api/v1/auth/logout` | autenticado |
| Usuário atual | `GET /api/v1/auth/me` | autenticado |
| Criar convite | `POST /api/v1/core/invitations` | `core.invitations.create` (rate limit 60/min) |
| Aceitar convite | `POST /api/v1/core/invitations/accept` | público (token; rate limit 10/min) |
| Listar usuários | `GET /api/v1/core/users` | `core.users.list` |
| Ver usuário | `GET /api/v1/core/users/{id}` | `core.users.view` |
| Atualizar usuário | `PATCH /api/v1/core/users/{id}` | `core.users.update` |
| Deletar usuário | `DELETE /api/v1/core/users/{id}` | `core.users.delete` |
| Atualizar próprio perfil | `PATCH /api/v1/core/users/me` | `core.users.update-self` |
| Alterar própria senha | `PATCH /api/v1/core/users/me/password` | `core.users.update-password` |
| Config pública do tenant | `GET /api/v1/core/tenant/config` | público |
| Editar config do tenant | `PATCH /api/v1/core/tenant/config` | tenant_admin |
| Alterar status de tenant | `PATCH /api/v1/mzrt/tenants/{tenant}/status` | `core.tenants.update-status` (developer; `auth:sanctum` + `area.guard:mzrt`) |
| Criar tenant | `POST /api/v1/mzrt/tenants` | `core.tenants.create` (developer; `auth:sanctum` + `area.guard:mzrt`) |
