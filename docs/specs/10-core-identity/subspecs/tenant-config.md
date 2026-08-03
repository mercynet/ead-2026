---
domain: core-identity
parent: ../spec.md
resource: tenant-config
last-reviewed: 2026-07-29
---

# Tenant Config (White-Label & Integrações)

## Model / Schema

```
tenants
- id
- name
- domain             // subdomínio ou domínio customizado
- database nullable  // nome do banco (se isolamento físico)
- description nullable
- is_active
- created_at, updated_at

tenant_customizations
- tenant_id
- logo, banner
- primary_color, secondary_color
- custom_css
- terms_url, privacy_url
- support_email

tenant_integrations
- tenant_id
- integration_type
- credentials        // encrypted:json (NUNCA texto puro)
- is_active

system_settings
- key
- value
- tenant_id          // null = global (só developer)
```

## Rules

- `GET /tenant/config` retorna definições **públicas** do tenant resolvido (nome, marca, cores,
  links de termos), para a SPA renderizar o visual **antes do login**. Não requer autenticação.
- `PATCH /tenant/config` edita personalizações — apenas `tenant_admin`.
- Credenciais de integração sempre `encrypted:json`. Ver
  [`../../00-architecture/security-privacy-lgpd.md`](../../00-architecture/security-privacy-lgpd.md).
- `SystemSetting` global (`tenant_id = null`): leitura/escrita apenas por `developer`.
- Gestão MZRT usa `status` (`active` ou `suspended`) como contrato API e o mapeia para
  `tenants.is_active`; `is_active` não é exposto por Resource. A transição é idempotente.
  Suspensão bloqueia novo login e uso de token tenant-bound apenas no tenant suspenso; reativação
  restaura o acesso.
- `domain` resolve o tenant; tenant inativo não resolve.

### Contrato de provisionamento MZRT

- `POST /api/v1/mzrt/tenants` exige `auth:sanctum` + `area.guard:mzrt`, usuário `developer` e
  `core.tenants.create`. Não recebe contexto nem header de tenant.
- Corpo requer `name` e `domain`, campos de identidade do schema `tenants`; `database` e
  `description` permanecem opcionais. Requer também `admin` aninhado, primeiro administrador, com
  `name`, `email` e `password` obrigatório; `cpf` é opcional conforme schema de usuários.
- Tenant e primeiro admin são persistidos em uma única transação. Qualquer falha desfaz ambos.
  Senha nunca é retornada.
- A criação usa regra compartilhada com `tenant:provision`.
- `domain` já existente responde HTTP `409` com código `tenant_already_exists`. Não há chave de
  idempotência nem semântica idempotente para o POST.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/core/tenant/config` | Config pública (white-label) do tenant resolvido | público |
| PATCH | `/api/v1/core/tenant/config` | Editar personalizações do tenant | tenant_admin |
| PATCH | `/api/v1/mzrt/tenants/{tenant}/status` | Ativar ou suspender tenant; sem contexto de tenant | `core.tenants.update-status` (developer) |
| POST | `/api/v1/mzrt/tenants` | Criar tenant e primeiro admin; sem contexto de tenant; domínio duplicado: `409 tenant_already_exists` | `core.tenants.create` (developer) |

## Permissions

A definir no seeder de RBAC (ex.: `core.tenant.config.update`). Hoje gated por `tenant_admin`.

As rotas MZRT exigem `auth:sanctum` e `area.guard:mzrt`, somente para `developer`; não usam
`tenant.required`, `tenant.access` nem header de tenant. A alteração de status é auditada,
documentada no Scribe e responde envelopes de API para 403, 404 e 422.

## Notes

- `TenantCustomization`, `TenantIntegration` e `SystemSetting` ainda não têm endpoints completos
  implementados — ver [`../tasks.md`](../tasks.md).
- Resolução de tenant (header → host) em
  [`../../00-architecture/multi-tenancy.md`](../../00-architecture/multi-tenancy.md).
- Entitlements pertencem a Ecosystem; ver
  [`../../50-ecosystem-plugins/spec.md`](../../50-ecosystem-plugins/spec.md) e suas tasks.
