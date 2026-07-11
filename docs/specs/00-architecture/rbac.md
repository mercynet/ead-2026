---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# RBAC — Roles, Permissions, UserTypes e Plugins

Este documento é a fonte única das regras de acesso do sistema. Toda implementação de
autorização deve seguir o que está aqui. As specs de domínio listam apenas as permissions do seu
domínio e linkam de volta para cá.

## 1. UserTypes

```php
enum UserType: string
{
    case Developer = 'developer';
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';
}
```

| Type | Scope | Descrição |
|------|-------|-----------|
| `developer` | Global | Equipe técnica, acesso total ao sistema, multi-tenant |
| `admin` | Tenant | Administrador do tenant (pode ter variações via roles) |
| `instructor` | Tenant | Criador de conteúdo (own only, pode ter variações) |
| `student` | User | Consumidor de cursos (próprio acesso) |

### Hierarquia de Acesso

```
developer  →  admin  →  instructor  →  student
```

Cada nível pode menos que o anterior:

- Developer vê TODOS os tenants e é o único que edita permissions.
- Admin só vê o próprio tenant.
- Instructor só vê/edita o próprio conteúdo (`own`).
- Student só vê o próprio consumo. Só vê dados de outras pessoas em **contexto de PLUGINS**
  (Forum → comentários de outros; Gamificação → ranking; Social, futuro → perfis). **Jamais**
  edita algo de outra pessoa. O core nativo não inclui forum/ranking/social — tudo é plugin opcional.

### Propriedades

- **Imutável por padrão**: student nunca vira admin; apenas `developer` muda UserType.
- Persistido na coluna `user_type` da tabela `users` (enum).
- Validação: verificar o tipo antes de conceder role.
- **Oculto**: students/instructors/admins não sabem que developers existem.

### Quem pode criar/editar cada UserType

| Type | Quem pode criar | Quem pode editar |
|------|-----------------|------------------|
| developer | Apenas developer | Apenas developer |
| admin | Developer ou admin | Apenas developer |
| instructor | Developer ou admin | Apenas developer |
| student | Developer, admin, ou self-register | Apenas developer |

## 2. Roles com Tenant Scope

A tabela `roles` (Spatie) recebe duas colunas adicionais:

```
- tenant_id  // nullable (null = global/system)
- scope      // 'global' | 'tenant'
```

| Scope | Quem cria | Quem pode editar | Exemplo |
|-------|-----------|------------------|---------|
| `global` | Sistema (seed) | **Ninguém** | developer, admin, instructor, student |
| `tenant` | Tenant Admin | Apenas Tenant Admin | financeiro, pedagógico, suporte |

Lógica de aplicação:

- Role global (`tenant_id = null`): disponível para todos os tenants.
- Role de tenant (`tenant_id = X`): só disponível para usuários daquele tenant. Dois tenants
  diferentes podem ter roles com o mesmo nome (`financeiro` no tenant 1 e no tenant 2 são distintas).

## 3. Sistema de Plugins

Cada plugin é um módulo opcional que adiciona permissions específicas. Estrutura:

```
Plugin → identifier (ex: "forum") · name · description · permissions
       └── PluginSubscription (tenant assina → ganha as permissions)
```

### Permission Naming

```
<plugin>.<resource>.<action>
ex.: forum.topics.create · financial.orders.approve · webinars.live.create
```

### Modelo de Dados (resumo)

```
plugins              (id, identifier unique, name, description, is_active, version)
plugin_permissions   (id, plugin_id, permission, description)
plugin_subscriptions (id, tenant_id, plugin_id, started_at, ended_at, is_active)
```

O catálogo completo de plugins (entidades de marketplace, billing) vive em `50-ecosystem-plugins/`.

### Fluxo e Verificação

```
Permissions efetivas = Base (UserType) + Role + Plugins ativos
```

1. Developer cria plugin com permissions definidas.
2. Tenant assina → `PluginSubscription` criada → usuários ganham as permissions.
3. Subscription expira → permissions removidas automaticamente (invalidar cache do tenant —
   ver `performance-scalability.md`).

```php
function canAccess(string $permission, Tenant $tenant): bool
{
    return user()->hasDirectPermission($permission)        // base (UserType)
        || user()->hasRolePermission($permission)          // role
        || $tenant->hasActivePluginPermission($permission); // plugin ativo
}
```

## 4. Matriz de Permissões por Domínio

Fonte única das permissions. Cada `spec.md`/`subspec` referencia esta seção.

### Core (`api/v1/core`)

```
core.users.list · core.users.create · core.users.view · core.users.update
core.users.delete · core.users.update-self · core.users.update-password
```

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:----------:|:-------:|
| core.users.list | sim | sim (tenant) | não | não |
| core.users.create | sim | sim | não | não |
| core.users.view | sim | sim | own | não |
| core.users.update | sim | sim | não | não |
| core.users.delete | sim | sim | não | não |
| core.users.update-self | sim | sim | sim | sim |
| core.users.update-password | sim | sim | sim | sim |

### Learning (`api/v1/learning`)

```
learning.categories.{list,create,view,update,delete} · learning.categories.system.manage (só developer)
learning.courses.{list,create,view,update,delete,publish}
learning.modules.{list,create,view,update,delete,reorder}
learning.lessons.{list,create,view,update,delete}
learning.enrollments.{list,create,view,update,delete}
learning.progress.view
```

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:----------:|:-------:|
| learning.categories.* (system) | sim | não | não | não |
| learning.categories.* (tenant) | sim | sim | sim | list |
| learning.courses.* | sim | sim | own | não |
| learning.modules.* | sim | sim | own | não |
| learning.lessons.* | sim | sim | own | não |
| learning.enrollments.* | sim | sim | view | own (próprias) |
| learning.progress.view | sim | sim | sim | não |

### Assessment (`api/v1/assessment`)

```
assessment.questionnaires.{list,create,view,update,delete}
assessment.questions.{list,create,view,update,delete}
assessment.attempts.{list,view,create,answer,finish}
assessment.certificates.{list,view,revoke}
```

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:----------:|:-------:|
| assessment.questionnaires.* | sim | sim | sim | não |
| assessment.questions.* | sim | sim | sim | não |
| assessment.attempts.list | sim | sim | view | não |
| assessment.attempts.view | sim | sim | view | own |
| assessment.attempts.create | sim | sim | não | sim |
| assessment.attempts.answer | sim | sim | não | sim |
| assessment.attempts.finish | sim | sim | não | sim |
| assessment.certificates.* | sim | sim | view | own |

### Financial / Ecosystem

Permissions financeiras (`financial.*`) e de ecossistema/plugins (`<plugin>.*`,
`ecosystem.*`) chegam principalmente via plugins assinados. Ver as specs dos domínios 40 e 50.

## 5. Regras de Ouro

1. **Permissions são definidas em código (seeders).** Nunca editáveis via interface. Só
   developer altera. Vale também para permissions de plugins.
2. **Roles podem ser criadas pelos tenants** — apenas Tenant Admin cria roles com `scope = 'tenant'`.
   Roles globais são fixas.
3. **UserType define o teto.** Student jamais vira admin; só developer muda UserType.
4. **Plugins trazem suas próprias permissions** (fixas). Tenant assina → ganha acesso; não edita permissions.

Não existem interfaces de "editar permissions" — tudo é configurado via seeders/código.

## Referência Rápida

| UserType | Acesso |
|----------|--------|
| developer | Tudo, multi-tenant |
| admin | Tudo no tenant |
| instructor | Próprio conteúdo (own) |
| student | Próprio consumo |

| Recurso | Editável | Por quem |
|---------|----------|----------|
| Permissions | não | Apenas Developer (código) |
| Roles globais | não | Ninguém |
| Roles de tenant | sim | Tenant Admin |
| UserType | não | Apenas Developer |
