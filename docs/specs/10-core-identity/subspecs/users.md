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
- **Login via email**, dentro do contexto do tenant resolvido (`X-Tenant-ID`/host). O lookup é
  tenant-scoped: procura o usuário no tenant resolvido e, se não houver, cai para o developer
  global (`tenant_id` null). Sem contexto de tenant, só o developer é localizável — usuário de
  tenant sem `X-Tenant-ID` recebe 401 genérico (não revela que o email existe em algum tenant).
- Ao registrar/matricular, buscar **dentro do tenant**:
  - CPF já existe **no tenant** → atualiza dados.
  - CPF não existe no tenant → cria novo usuário (ainda que o CPF exista em outro tenant).
- **Sem identidade global compartilhada.** A visão "pessoa universal" (marketplace — tabela
  `people` ligando por CPF) fica **diferida** em `docs/ROADMAP.md`, sem precludir.

> **Dívida de schema (resolvida 2026-07-16):** o `unique` global de `cpf`/`email` foi trocado por
> compostos `(tenant_id, cpf)` e `(tenant_id, email)` (migration
> `2026_07_16_130000_tenant_scope_user_unique_constraints`). NULLs em `tenant_id`
> (developer/landlord) não colidem.

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

**Administração pelo tenant admin (resolvido 2026-08-03).** A coluna "quem pode editar" acima trata
de **mutação de `user_type`**, não do registro inteiro. Na prática, a área Admin
(`PATCH`/`DELETE /api/v1/admin/users/{id}`) administra **instructor e student do próprio tenant**:

- Campos editáveis: `name`, `headline`, `bio`, `avatar`, `linkedin_url`, `twitter_url`.
- Proibidos no payload: `user_type` (é o teto de permissions), `email`, `cpf` e `password`
  (identidade e credencial — mudam pelo próprio usuário ou por reset).
- Outro **admin do mesmo tenant** é 403 (admin editando admin é escalada lateral) e **developer** é
  404 (nem existência é revelada); usuário de outro tenant também é 404.

### Exclusão (soft delete)

- `DELETE` faz **soft delete** (`deleted_at`) e revoga todas as sessões Sanctum do alvo na mesma
  transação — usuário excluído com token vivo continuaria autenticando.
- Histórico (matrículas, orders, auditoria) permanece íntegro apontando para o registro.
- O `unique` de e-mail é sobre a linha, não sobre a linha ativa: **o e-mail de um usuário excluído
  segue reservado no tenant**. Afrouxar o índice para reaproveitar o e-mail abriria brecha de
  colisão entre usuários ativos. Restore/reaproveitamento fica diferido.

### Convenção de query (tenant scope)

Filtrar por tenant via scope/trait, não `where('tenant_id', ...)` espalhado. Ver
[`../../00-architecture/multi-tenancy.md`](../../00-architecture/multi-tenancy.md).

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/admin/invitations` | Emitir convite (`student`\|`instructor`) pela superfície Admin; token 1x | `core.invitations.create` |
| POST | `/api/v1/core/invitations` | Compatibilidade legacy para emissão de convite | `core.invitations.create` |
| POST | `/api/v1/core/invitations/accept` | Aceitar convite → cria usuário + papel | público (token) |
| GET | `/api/v1/admin/users` | Admin lista usuários do próprio tenant | `core.users.list` |
| GET | `/api/v1/admin/users/{id}` | Admin vê usuário do próprio tenant | `core.users.view` |
| GET | `/api/v1/core/users` | Compatibilidade legacy; tenant-scoped, developer vê todos | `core.users.list` |
| GET | `/api/v1/core/users/{id}` | Compatibilidade legacy para consulta de usuário | `core.users.view` |
| PATCH | `/api/v1/admin/users/{id}` | Atualizar instructor/student do tenant (área Admin) | `core.users.update` |
| DELETE | `/api/v1/admin/users/{id}` | Excluir instructor/student do tenant — soft delete (área Admin) | `core.users.delete` |
| PATCH | `/api/v1/core/users/me` | Atualizar próprio perfil (nome, bio, avatar, cpf) | `core.users.update-self` |
| PATCH | `/api/v1/core/users/me/password` | Alterar própria senha | `core.users.update-password` |

## Permissions

```
core.users.list · core.users.create · core.users.view · core.users.update
core.users.delete · core.users.update-self · core.users.update-password
```

Matriz por UserType em [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §4 (Core).

## Notes

- Fluxo de acesso: convite (`POST /api/v1/admin/invitations` por um admin; `/core/invitations` permanece
  compatibility legacy) → aceite (`POST /invitations/accept`
  com token+nome+senha) → login (`POST /auth/login` com email+senha+tenant via header) → uso com
  `Authorization: Bearer {token}` + `X-Tenant-ID` (exceto developer).
- `GET /users/{id}`: a permission canônica é `core.users.view` (o documento legado usava
  `core.users.show` em alguns pontos — usar `view`).
