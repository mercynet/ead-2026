# RBAC - Roles, Permissions, UserTypes e Plugins

> **Este documento contém TODAS as regras de acesso do sistema.**
> Qualquer implementação de permissions deve seguir estas regras.
> Para LLM: Leia este arquivo primeiro antes de implementar qualquer coisa relacionada a acesso.

---

## 1. UserTypes (Tipos de Usuário)

### Enum

```php
enum UserType: string
{
    case Developer = 'developer';
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';
}
```

### Definição

| Type | Scope | Descrição |
|------|-------|-----------|
| `developer` | Global | Equipa técnica, acesso total ao sistema |
| `admin` | Tenant | Administrador do tenant (pode ter variações via roles) |
| `instructor` | Tenant | Criador de conteúdo (own only, pode ter variações) |
| `student` | User | Consumidor de cursos (próprio acesso) |

### Hierarquia de Acesso

```
developer  →  admin  →  instructor  →  student
   │            │            │            │
   └────────────┴────────────┴────────────┘
         Todos podem fazer menos que o anterior
```

**Regras:**
- Developer vê TODOS os tenants
- Admin só vê o próprio tenant
- Instructor só vê/editar próprio conteúdo (own)
- Student só vê próprio consumo, MAS pode ver outros **APENAS em contexto de PLUGINS**:
  - Forum plugin → vê comentários de outros
  - Gamificação plugin → vê ranking de outros
  - Social plugin (futuro) → vê perfis de outros
  - **Jamais** editará algo de outra pessoa

### Propriedades

- **Imutável**: student nunca vira admin, etc (somente developer pode mudar)
- **Migrations**: coluna `user_type` na tabela `users`
- **Validação**: verificar tipo antes de grantar role
- **Oculto**: students/instructors/admins não sabem que developers existem

### Exemplos Práticos

#### Exemplo 1: Developer

```
Usuário: Paulo (equipa técnica)
- UserType: developer
- Roles: developer (global)
- Plugins: todos

Permissões efetivas:
- Todas as permissions do sistema (multi-tenant)
- Pode fazer tudo em qualquer tenant
- Pode criar/editar TODAS as roles e permissions

⚠️ IMPORTANTE (para LLM):
- Developer é o ÚNICO que pode editar permissions
- Permissions são definidas em seeders/código
- Nenhuma interface para editar permissions
- Developer também pode criar roles de tenant se necessário
```

#### Exemplo 2: Tenant Admin

```
Usuário: Maria (diretora da Escola ABC)
- UserType: admin
- Tenant: Escola ABC
- Roles: admin (global)
- Plugins: [forum]

Permissões efetivas:
- core.users.* (dentro do tenant)
- learning.* (dentro do tenant)
- assessment.* (dentro do tenant)
- forum.topics.* (porque tem o plugin)
```

#### Exemplo 3: Admin com Role Específica

```
Usuário: João (responsável financeiro)
- UserType: admin
- Tenant: Escola ABC
- Roles: admin + financeiro (escopo: tenant)
- Plugins: [financial]

Permissões efetivas:
- core.users.list/view (base admin)
- learning.catalog.courses.view (base admin)
- financial.orders.* (role financeiro)
- financial.reports.* (plugin financial)
```

#### Exemplo 4: Instructor com Role Limitada

```
Usuário: Pedro (professor de matemática)
- UserType: instructor
- Tenant: Escola ABC
- Roles: instructor + matematica (escopo: tenant)
- Plugins: []

Permissões efetivas:
- learning.courses.own (só cursos que criou)
- learning.modules.own
- learning.lessons.own
- assessment.questionnaires.own
- assessment.questions.own
```

#### Exemplo 5: Student

```
Usuário: Ana (aluna)
- UserType: student
- Tenant: Escola ABC
- Roles: student (global)
- Plugins: []

Permissões efetivas:
- learning.catalog.courses.list/show (ver catálogo)
- learning.enrollments.own (só suas matrículas)
- learning.progress.own (só seu progresso)
- assessment.attempts.own (só suas tentativas)
- assessment.certificates.own (só seus certificados)

IMPORTANTE (para LLM):
- Student JAMAIS editará algo de outra pessoa
- Student só "vê" outros em contextos específicos de PLUGINS:
  - Se tiver plugin "Forum" → pode ver comentários de outros
  - Se tiver plugin "Gamificação/Ranking" → pode ver posição de outros
  - Se tiver plugin "Social Community" (futuro) → pode ver perfis
- O sistema CORE/NATIVO não inclui forums, rankings, social, etc
- Tudo isso será implementado como PLUGINS OPCIONAIS
```

---

## 2. Roles com Tenant Scope

### Regras de Edição (IMPORTANTE para LLM)

```
┌─────────────────────────────────────────────────────────────┐
│ O QUE PODE SER EDITADO ONLINE?                              │
├─────────────────────────────────────────────────────────────┤
│ ✓ ROLES de tenant (criar, editar, excluir)                 │
│   - Apenas pelo Tenant Admin                                 │
│   - Apenas roles com scope = 'tenant'                       │
│   - Ex: criar role "financeiro" para seu tenant            │
│                                                             │
│ ✗ PERMISSIONS (NUNCA, em nenhuma circunstância)            │
│   - Apenas Developers podem editar                           │
│   - Inclui permissions de plugins E sistema                 │
│   - Editing causaria confusão massiva                       │
└─────────────────────────────────────────────────────────────┘
```

**Por que permissions não são editáveis:**
- Permissions são definidas em código/seeders
- Plugins trazem suas próprias permissions
- Developer define o "cardápio" de permissions disponível
- Tenant Admin só pode escolher QUALES roles usar (não criar permissions)

### Modelo

```php
// roles table (Spatie) - adicionar:
- tenant_id  // nullable (se null = global/system)
- scope     // 'global' | 'tenant'
```

### Escopo

| Scope | Quem cria | Quem pode editar | Exemplo |
|-------|-----------|------------------|---------|
| `global` | Sistema (seed) | **NINGUÉM** | developer, admin, instructor, student |
| `tenant` | Tenant Admin | Apenas Tenant Admin | financeiro, pedagógico, suporte |

### Lógica de Aplicação

```
1. Se Role é global (tenant_id = null):
   - Disponíveis para TODOS os tenants
   - Ex: developer, admin, instructor, student

2. Se Role é do tenant (tenant_id = X):
   - Só disponível para usuários daquele tenant
   - Criada pelo Tenant Admin
   - Ex: admin_financeiro só existe na Escola ABC
```

### Exemplo de Dados

```
roles table:

| id | tenant_id | name | scope   |
|----|-----------|------|---------|
| 1  | NULL      | developer | global |
| 2  | NULL      | admin     | global |
| 3  | NULL      | instructor| global |
| 4  | NULL      | student   | global |
| 5  | 1         | financeiro| tenant |
| 6  | 1         | pedagogico| tenant |
| 7  | 2         | financeiro| tenant |  (outro tenant pode ter role com mesmo nome)
```

---

## 3. Sistema de Plugins

### Conceito

Cada plugin é um módulo opcional que adiciona permissions específicas ao sistema.

### Estrutura

```
Plugin
    │
    ├── identifier  (ex: "forum", "financial", "webinars")
    ├── name
    ├── description
    └── permissions  (ex: forum.topics.*, financial.orders.*)
        │
        └── PluginSubscription (tenant assina → ganha permissions)
```

### Permission Naming

```
<plugin>.<resource>.<action>

Exemplos:
- forum.topics.create
- forum.topics.view
- financial.orders.create
- financial.orders.approve
- certificates_advanced.pdf.generate
- webinars.live.create
```

⚠️ IMPORTANTE (para LLM):
- Permissions de plugins TAMBÉM são fixas (não editáveis)
- Plugin define permissions → Developer adiciona ao sistema
- Tenant assina → ganha acesso automaticamente
- NENHUMA interface para editar permissions de plugins

### Modelo de Dados

```
plugins
- id
- identifier  (unique, ex: "forum")
- name
- description
- is_active
- version

plugin_permissions
- id
- plugin_id
- permission  (ex: "forum.topics.create")
- description

plugin_subscriptions
- id
- tenant_id
- plugin_id
- started_at
- ended_at
- is_active
```

### Fluxo de Permissions

```
1. Developer cria plugin com permissions definidas

2. Tenant assina plugin → PluginSubscription criada

3. Usuários do tenant ganham permissions:
   Permissions = Base (UserType) + Role + Plugins(ativos)

4. Se subscription expirar → permissions removidas automaticamente
```

### Verificação em Runtime

```php
// Exemplo de check
function canAccess(string $permission, Tenant $tenant): bool
{
    // 1. Base permission (UserType)
    $hasBase = user()->hasDirectPermission($permission);
    
    // 2. Role permission
    $hasRole = user()->hasRolePermission($permission);
    
    // 3. Plugin permission (se plugin ativo)
    $hasPlugin = $tenant->hasActivePluginPermission($permission);
    
    return $hasBase || $hasRole || $hasPlugin;
}
```

### Exemplos de Plugins

| Plugin | Permissions | Público-alvo |
|--------|-------------|--------------|
| Forum | forum.topics.*, forum.replies.* | Todos |
| Webinars | webinars.* | Escolas corporativas |
| Certificados Avançados | certificates_advanced.* | Premium |
| Financial | financial.* | Admin financeiro |
| Relatórios | reports.* | Managers |
| API Externa | api.* | Integrações |

---

## 4. Matriz de Permissões por Domain

### Core

```
core.users.list
core.users.create
core.users.view
core.users.update
core.users.delete
core.users.update-self
core.users.update-password
```

### Learning

```
learning.categories.list
learning.categories.create
learning.categories.view
learning.categories.update
learning.categories.delete
learning.categories.system.manage  # só developer

learning.courses.list
learning.courses.create
learning.courses.view
learning.courses.update
learning.courses.delete
learning.courses.publish

learning.modules.list
learning.modules.create
learning.modules.view
learning.modules.update
learning.modules.delete
learning.modules.reorder

learning.lessons.list
learning.lessons.create
learning.lessons.view
learning.lessons.update
learning.lessons.delete

learning.enrollments.list
learning.enrollments.create
learning.enrollments.view
learning.enrollments.update
learning.enrollments.delete

learning.progress.view  # ver progresso dos alunos
```

### Assessment

```
assessment.questionnaires.list
assessment.questionnaires.create
assessment.questionnaires.view
assessment.questionnaires.update
assessment.questionnaires.delete

assessment.questions.list
assessment.questions.create
assessment.questions.view
assessment.questions.update
assessment.questions.delete

assessment.attempts.list
assessment.attempts.view
assessment.attempts.answer
assessment.attempts.finish

assessment.certificates.list
assessment.certificates.view
assessment.certificates.revoke
```

---

## 5. Resumo: Sistema de Permissions (REGRAS FIXAS)

```
┌────────────────────────────────────────────────────────────────────┐
│                        REGRAS DE OURO                              │
├────────────────────────────────────────────────────────────────────┤
│ 1. Permissions = DEFINIDAS EM CÓDIGO (seeders)                   │
│    - NUNCA editáveis via interface                                │
│    - Apenas Developer pode alterar                                │
│                                                                    │
│ 2. Roles = PODEM SER CRIADAS PELOS TENANTS                        │
│    - Apenas Tenant Admin cria roles de tenant                     │
│    - Roles globais são fixas (não editáveis)                     │
│                                                                    │
│ 3. UserType = DEFINE O TETO                                      │
│    - student jamais vira admin                                    │
│    - apenas Developer pode mudar                                  │
│                                                                    │
│ 4. Plugins = TRAZEM PERMISSIONS PRÓPRIAS                         │
│    - Permissions de plugins também são fixas                       │
│    - Tenant assina → ganha acesso (não_edita permissions)         │
└────────────────────────────────────────────────────────────────────┘

Para LLM: NÃO invente interfaces de "editar permissions".
Tudo é configurado via seeders/código.
```

---

## 6. Próximos Passos - Implementação

### Fase 1: Base de Dados

1. Adicionar coluna `user_type` na tabela `users` (enum: developer|admin|instructor|student)
2. Adicionar colunas `tenant_id` e `scope` na tabela `roles`

### Fase 2: Seeders

1. Atualizar RolesSeeder com UserTypes corretos
2. Atualizar seeders de usuários com user_type correto

### Fase 3: Gate/Policy

1. Implementar gate/policy que verifica UserType
2. Implementar "teto" de permissions baseado em UserType
3. Implementar verificação de plugin permissions

---

## Referência Rápida

| UserType | Access |
|----------|--------|
| developer | Tudo, multi-tenant |
| admin | Tudo no tenant |
| instructor | Próprio conteúdo (own) |
| student | Próprio consumo |

| Escopo Role | Quem cria |
|-------------|-----------|
| global | Sistema (seed) |
| tenant | Tenant Admin |

| Recurso | Editável | Por quem |
|---------|----------|----------|
| Permissions | ❌ | Apenas Developer |
| Roles globais | ❌ | Ninguém |
| Roles de tenant | ✅ | Tenant Admin |
| UserType | ❌ | Apenas Developer |
