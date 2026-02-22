# Core - Usuários, Autenticação e Tenants

> **Este documento contém todas as regras de negócio do domínio Core.**
> Para LLM: Leia este arquivo antes de implementar anything em Core.

---

## 1. Visão Geral

O módulo Core gerencia:
- **Users**: Usuários do sistema
- **Auth**: Autenticação e sessão
- **Tenants**: Multi-tenancy

---

## 2. Users

### Modelo

```
users
- id
- tenant_id          // FK (nullable para developers)
- user_type          // ENUM: developer|admin|instructor|student
- name
- email
- cpf               // único entre tenants (identificador)
- password
- is_active
- email_verified_at
- created_at
- updated_at
```

### Regras de CPF

- **CPF é único entre todos os tenants** (identificador universal)
- **Login é via email** (email pode se repetir entre tenants)
- Ao matricular: buscar por CPF primeiro, se existir, reutilizar

### UserType

| Type | Quem pode criar | Quem pode editar |
|------|-----------------|------------------|
| developer | Apenas developer | Apenas developer |
| admin | Developer ou admin | Apenas developer |
| instructor | Developer ou admin | Apenas developer |
| student | Developer, admin, ou self-register | Apenas developer |

### Endpoints

| Método | Endpoint | Descrição | Permissão |
|--------|----------|-----------|-----------|
| POST | `/api/v1/core/users` | Criar usuário | Aberto (sem auth) |
| GET | `/api/v1/core/users` | Listar usuários | `core.users.list` |
| GET | `/api/v1/core/users/{id}` | Ver usuário | `core.users.show` |
| PATCH | `/api/v1/core/users/{id}` | Atualizar usuário | `core.users.update` |
| DELETE | `/api/v1/core/users/{id}` | Deletar usuário | `core.users.delete` |
| PATCH | `/api/v1/core/users/me` | Atualizar próprio perfil | `core.users.update-self` |
| PATCH | `/api/v1/core/users/me/password` | Alterar própria senha | `core.users.update-password` |

### Fluxo de Criação

```
1. Usuário tenta se registrar (POST /users)
   - Se CPF já existe em OUTRO tenant → erro (CPF em uso)
   - Se CPF já existe no MESMO tenant → atualiza dados
   - Se CPF não existe → cria novo

2. Usuário faz login (POST /auth/login)
   - Email + senha + tenant_id (via header)
   - Gera token Sanctum

3. Usuário acessa recursos com token
   - Header: Authorization: Bearer {token}
   - Header: X-Tenant-ID: {id} (exceto developer)
```

---

## 3. Autenticação

### Login

```
POST /api/v1/core/auth/login
Body: { email, password }
Headers: X-Tenant-ID (não requerido para developer)
```

**Regras:**
- Rate limit: 5 tentativas por minuto
- Retorna token Sanctum se sucesso
- Retorna erro se credenciais inválidas

### Logout

```
POST /api/v1/core/auth/logout
Headers: Authorization: Bearer {token}
```

**Regras:**
- Invalida o token atual
- Requer autenticação

### Me (usuário atual)

```
GET /api/v1/core/auth/me
Headers: Authorization: Bearer {token}
```

**Regras:**
- Retorna dados do usuário autenticado
- Inclui tenant_id e user_type

---

## 4. Tenants

### Modelo

```
tenants
- id
- name
- domain          // subdomain ou domínio customizado
- database        // nome do banco (se diferente)
- is_active
- settings        // JSON (configurações customizadas)
- created_at
- updated_at
```

### Estrutura de Dados

Cada tenant pode ter:
- **Próprio subdomínio**: `tenant.minhaempresa.com`
- **Domínio próprio**: `minhaempresa.com`
- **Banco de dados próprio**: Isolamento total (opcional)

### Resolução de Tenant

```
1. Via header X-Tenant-ID (API)
2. Via subdomain (HTTP Host)
3. Via domínio customizado
```

### Middleware

- `resolve.tenant.optional`: Tenta resolver tenant, aceita null
- `tenant.required.unless.developer`: Exige tenant (exceto developer)
- `tenant.access`: Verifica acesso ao tenant

---

## 5. Multi-Tenancy

### Regras de Isolamento

| UserType | Pode ver outros tenants? |
|----------|------------------------|
| developer | ✅ Sim (todos) |
| admin | ❌ Não (só o próprio) |
| instructor | ❌ Não (só o próprio) |
| student | ❌ Não (só o próprio) |

### Queries com Tenant

```php
// Errado: sempre filtrar por tenant
User::query()->where('tenant_id', $tenant->id);

// Certo: usar scope ou trait
User::query()->tenant($tenant)->get();
```

---

## 6. Status de Implementação

### ✅ Feito

- [x] User model + factory
- [x] POST /users (criar)
- [x] GET /users (listar)
- [x] GET /users/{id}
- [x] PATCH /users/me
- [x] Auth login/logout/me
- [x] Middleware de tenant

### ⏳ Pendente

- [ ] PATCH /users/{id} (update)
- [ ] DELETE /users/{id}
- [ ] PATCH /users/me/password
- [ ] Adicionar coluna user_type na tabela users
- [ ] Impersonação (admin logar como outro user)

---

## 7. Permissions Necessárias

```
core.users.list
core.users.create
core.users.view
core.users.update
core.users.delete
core.users.update-self
core.users.update-password
```

### Por UserType

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:---------:|:-------:|
| core.users.list | ✅ | ✅ (tenant) | ❌ | ❌ |
| core.users.create | ✅ | ✅ | ❌ | ❌ |
| core.users.view | ✅ | ✅ | ✅ own | ❌ |
| core.users.update | ✅ | ✅ | ❌ | ❌ |
| core.users.delete | ✅ | ✅ | ❌ | ❌ |
| core.users.update-self | ✅ | ✅ | ✅ | ✅ |
| core.users.update-password | ✅ | ✅ | ✅ | ✅ |

---

## 8. Referência Rápida

| Recurso | Endpoint | Permissão |
|---------|----------|----------|
| Criar usuário | POST /users | - (aberto) |
| Listar usuários | GET /users | core.users.list |
| Ver usuário | GET /users/{id} | core.users.show |
| Atualizar usuário | PATCH /users/{id} | core.users.update |
| Deletar usuário | DELETE /users/{id} | core.users.delete |
| Atualizar perfil | PATCH /users/me | core.users.update-self |
| Alterar senha | PATCH /users/me/password | core.users.update-password |
| Login | POST /auth/login | - |
| Logout | POST /auth/logout | auth |
| Dados atuais | GET /auth/me | auth |
