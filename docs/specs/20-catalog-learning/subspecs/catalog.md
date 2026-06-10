---
domain: catalog-learning
parent: ../spec.md
resource: catalog
last-reviewed: 2026-06-10
---

# Catalog (Categories & Course Discovery)

## Model / Schema

```
categories
- id
- tenant_id          // FK, null para categoria padrão do sistema
- parent_id          // FK auto-referência (hierarquia)
- name
- slug
- description
- is_system          // boolean (global vs. custom)
- is_active
- sort_order
- created_at, updated_at

category_course (pivot, tenant-aware)
- tenant_id
- course_id
- category_id
```

## Rules

### Tipos de categoria

| Tipo | Escopo | Quem cria/edita |
|------|--------|-----------------|
| System (`tenant_id=null, is_system=true`) | Global (todos os tenants) | Apenas developer |
| Custom (`tenant_id=X, is_system=false`) | Do tenant | Tenant Admin, Instructor |

### Categorias padrão globais e antiduplicação

- Existe um conjunto de categorias padrão globais, pré-cadastradas, reutilizáveis por todos os tenants.
- Tenant **usa** categorias padrão, mas **não cria/edita/exclui** padrão.
- Tenant pode criar categorias próprias, **exceto** com mesmo nome (normalizado: case-insensitive,
  sem espaços excedentes, sem acentuação) de qualquer categoria padrão global.
  - Ex.: se existe padrão `Desenvolvimento de Software`, nenhum tenant cria outra com esse nome.
  - `Desenvolvimento de Programas` pode ser criada por um tenant.
- **Categorias custom podem ser duplicadas entre tenants diferentes** (dois tenants podem ter
  "Tipos de bolo" cada um).
- Validação implementada em `StoreCategoryAction`.

### Pivô curso × categoria

- `course_id` deve referenciar curso do mesmo `tenant_id`.
- `category_id` pode ser categoria padrão (`tenant_id=null`) ou do mesmo `tenant_id` do curso.
- Nunca vincular categoria de outro tenant.

### Descoberta de cursos

- `GET /catalog/courses` lista cursos publicados com filtros (`category`, `is_free`, `is_featured`).
  **Regra forte:** não exibir cursos que o aluno logado já comprou.
- `GET /catalog/courses/{slug}` retorna a matriz curricular completa + DTO público (preço,
  descrição) para montar a landing page.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/learning/catalog/courses` | Lista cursos publicados (filtros) | público/auth |
| GET | `/api/v1/learning/catalog/courses/{slug}` | Curso completo p/ landing page | público/auth |
| GET | `/api/v1/learning/catalog/categories` | Listar categorias (sistema + tenant) | `learning.categories.list` |
| POST | `/api/v1/learning/catalog/categories` | Criar categoria | `learning.categories.create` |
| PUT | `/api/v1/learning/catalog/categories/{id}` | Atualizar categoria | `learning.categories.update` |
| DELETE | `/api/v1/learning/catalog/categories/{id}` | Deletar categoria | `learning.categories.delete` |

## Permissions

```
learning.categories.list · learning.categories.create · learning.categories.view
learning.categories.update · learning.categories.delete
learning.categories.system.manage   // só developer
```

## Notes

- Acesso ao catálogo público vs. autenticado depende de flag do tenant.
- Decisão de `is_system` em autorização fica na `CategoryPolicy`, não em `if` de controller.
