---
domain: core-identity
parent: ../spec.md
resource: tenant-config
last-reviewed: 2026-06-10
---

# Tenant Config (White-Label & Integrações)

## Model / Schema

```
tenants
- id
- name
- slug
- domain             // subdomínio ou domínio customizado
- database           // nome do banco (se isolamento físico)
- is_active
- data / settings    // JSON de configuração customizada
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

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/core/tenant/config` | Config pública (white-label) do tenant resolvido | público |
| PATCH | `/api/v1/core/tenant/config` | Editar personalizações do tenant | tenant_admin |

## Permissions

A definir no seeder de RBAC (ex.: `core.tenant.config.update`). Hoje gated por `tenant_admin`.

## Notes

- `TenantCustomization`, `TenantIntegration` e `SystemSetting` ainda não têm endpoints completos
  implementados — ver [`../tasks.md`](../tasks.md).
- Resolução de tenant (header → host) em
  [`../../00-architecture/multi-tenancy.md`](../../00-architecture/multi-tenancy.md).
