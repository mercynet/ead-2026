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
- email              // login; pode repetir entre tenants
- cpf                // identificador universal, ÚNICO cross-tenant
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

### Regra de CPF (crítica)

- **CPF é único entre todos os tenants** — é o identificador universal da pessoa.
- **Login é via email** (email pode se repetir entre tenants distintos).
- Ao matricular/registrar, **buscar por CPF primeiro**:
  - CPF já existe em **outro** tenant → erro (CPF em uso) ou reaproveitamento conforme política de pool.
  - CPF já existe no **mesmo** tenant → atualiza dados.
  - CPF não existe → cria novo usuário.

### Quem pode criar/editar cada UserType

| Type | Quem pode criar | Quem pode editar |
|------|-----------------|------------------|
| developer | Apenas developer | Apenas developer |
| admin | Developer ou admin | Apenas developer |
| instructor | Developer ou admin | Apenas developer |
| student | Developer, admin, ou self-register | Apenas developer |

UserType é imutável exceto por developer (define o teto — ver
[`../../00-architecture/rbac.md`](../../00-architecture/rbac.md)).

### Convenção de query (tenant scope)

Filtrar por tenant via scope/trait, não `where('tenant_id', ...)` espalhado. Ver
[`../../00-architecture/multi-tenancy.md`](../../00-architecture/multi-tenancy.md).

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/core/users` | Criar usuário (registro; atrela a `student`) | aberto |
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

- Fluxo de acesso: registro (`POST /users`) → login (`POST /auth/login` com email+senha+tenant via
  header) → uso com `Authorization: Bearer {token}` + `X-Tenant-ID` (exceto developer).
- `GET /users/{id}`: a permission canônica é `core.users.view` (o documento legado usava
  `core.users.show` em alguns pontos — usar `view`).
