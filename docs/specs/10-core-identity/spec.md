---
domain: core-identity
maturity: stable
last-reviewed: 2026-06-10
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
- [`subspecs/tenant-config.md`](subspecs/tenant-config.md) — white-label, integrações.

## Entities

| Model | Tabela | Invariantes |
|-------|--------|-------------|
| `User` | `users` | `tenant_id` nulo só para developer/landlord; `cpf`/`email` únicos **por tenant** (`unique(tenant_id, …)`); `user_type` (enum) define o teto de acesso. Roles/permissions via spatie/laravel-permission. |
| `Tenant` | `tenants` (landlord) | `slug`/`domain` resolvem o tenant; só resolve se `is_active`. |
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
- **UserType define o teto de acesso** e é imutável exceto por developer. Ver
  [`../00-architecture/rbac.md`](../00-architecture/rbac.md).
- **White-label inicializável sem login**: a SPA chama `GET /tenant/config` via host header para
  carregar marca/cores/links antes do login.
- **Impersonação segura** (diferida): tokens Sanctum com ability `impersonating`. Ver
  [`../00-architecture/security-privacy-lgpd.md`](../00-architecture/security-privacy-lgpd.md).

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
```

## Events

- (Diferido) `UserRegistered` — para onboarding/notificações.

## Quick Reference

| Recurso | Endpoint | Permission |
|---------|----------|------------|
| Login | `POST /api/v1/core/auth/login` | público (rate limit 5/min) |
| Logout | `POST /api/v1/core/auth/logout` | autenticado |
| Usuário atual | `GET /api/v1/core/auth/me` | autenticado |
| Criar usuário | `POST /api/v1/core/users` | aberto (registro) |
| Listar usuários | `GET /api/v1/core/users` | `core.users.list` |
| Ver usuário | `GET /api/v1/core/users/{id}` | `core.users.view` |
| Atualizar usuário | `PATCH /api/v1/core/users/{id}` | `core.users.update` |
| Deletar usuário | `DELETE /api/v1/core/users/{id}` | `core.users.delete` |
| Atualizar próprio perfil | `PATCH /api/v1/core/users/me` | `core.users.update-self` |
| Alterar própria senha | `PATCH /api/v1/core/users/me/password` | `core.users.update-password` |
| Config pública do tenant | `GET /api/v1/core/tenant/config` | público |
| Editar config do tenant | `PATCH /api/v1/core/tenant/config` | tenant_admin |
