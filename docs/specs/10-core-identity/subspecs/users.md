---
domain: core-identity
parent: ../spec.md
resource: users
last-reviewed: 2026-06-10
---

# Users

## Model / Schema

```
users
- id
- tenant_id          // FK, nullable (null para developer/landlord)
- user_type          // ENUM: developer | admin | instructor | student
- name
- email              // login; único POR tenant — unique(tenant_id, email)
- cpf                // identificador da pessoa no tenant; único POR tenant — unique(tenant_id, cpf)
- password
- is_active
- email_verified_at
- headline
- bio
- avatar
- linkedin           // social, opcional
- twitter            // social, opcional
- created_at
- updated_at
```

PII e tratamento de dados sensíveis em
[`../../00-architecture/security-privacy-lgpd.md`](../../00-architecture/security-privacy-lgpd.md).

## Rules

### Regra de CPF / identidade (resolvido 2026-06-10 — tenant-scoped)

Modelo de identidade **tenant-scoped** (isolamento total; prioriza segurança/LGPD/escalabilidade).
Cada usuário pertence a um tenant; a mesma pessoa em duas escolas = dois registros independentes
(controladores de dados distintos).

- **Unicidade por tenant:** `unique(tenant_id, cpf)` e `unique(tenant_id, email)`. CPF e email
  **podem repetir** entre tenants distintos.
- **Login via email**, dentro do contexto do tenant resolvido (`X-Tenant-ID`/host).
- Ao registrar/matricular, buscar **dentro do tenant**:
  - CPF já existe **no tenant** → atualiza dados.
  - CPF não existe no tenant → cria novo usuário (ainda que o CPF exista em outro tenant).
- **Sem identidade global compartilhada.** A visão "pessoa universal" (marketplace — tabela
  `people` ligando por CPF) fica **diferida** em `docs/ROADMAP.md`, sem precludir.

> **Dívida de schema (corrigir):** hoje as migrations aplicam `unique` **global** em `cpf` e `email`
> — contradiz o modelo. Trocar por uniques compostos `(tenant_id, cpf)` e `(tenant_id, email)`.

### Quem pode criar/editar cada UserType

| Type | Quem pode criar | Quem pode editar |
|------|-----------------|------------------|
| developer | Apenas developer | Apenas developer |
| admin | Developer ou admin | Apenas developer |
| instructor | Developer ou admin (via convite) | Apenas developer |
| student | Developer ou admin (via convite) | Apenas developer |

> **Onboarding invite-only:** não há mais auto-registro público. `instructor`/`student` entram
> por convite tenant-bound (ver [`../spec.md`](../spec.md) e a tabela de endpoints abaixo).

UserType é imutável exceto por developer (define o teto — ver
[`../../00-architecture/rbac.md`](../../00-architecture/rbac.md)).

### Convenção de query (tenant scope)

Filtrar por tenant via scope/trait, não `where('tenant_id', ...)` espalhado. Ver
[`../../00-architecture/multi-tenancy.md`](../../00-architecture/multi-tenancy.md).

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/core/invitations` | Emitir convite (`student`\|`instructor`); token 1x | `core.invitations.create` |
| POST | `/api/v1/core/invitations/accept` | Aceitar convite → cria usuário + papel | público (token) |
| GET | `/api/v1/core/users` | Listar (tenant-scoped; developer vê todos) | `core.users.list` |
| GET | `/api/v1/core/users/{id}` | Ver usuário | `core.users.view` |
| PATCH | `/api/v1/core/users/{id}` | Atualizar usuário | `core.users.update` |
| DELETE | `/api/v1/core/users/{id}` | Deletar usuário | `core.users.delete` |
| PATCH | `/api/v1/core/users/me` | Atualizar próprio perfil (nome, bio, avatar, cpf) | `core.users.update-self` |
| PATCH | `/api/v1/core/users/me/password` | Alterar própria senha | `core.users.update-password` |

## Permissions

```
core.users.list · core.users.create · core.users.view · core.users.update
core.users.delete · core.users.update-self · core.users.update-password
```

Matriz por UserType em [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §4 (Core).

## Notes

- Fluxo de acesso: convite (`POST /invitations` por um admin) → aceite (`POST /invitations/accept`
  com token+nome+senha) → login (`POST /auth/login` com email+senha+tenant via header) → uso com
  `Authorization: Bearer {token}` + `X-Tenant-ID` (exceto developer).
- `GET /users/{id}`: a permission canônica é `core.users.view` (o documento legado usava
  `core.users.show` em alguns pontos — usar `view`).
