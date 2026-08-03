---
domain: catalog-learning
parent: ../spec.md
resource: catalog
last-reviewed: 2026-06-13
---

# Catalog (Categories & Course Discovery)

> Desenho estrutural (tabela única, pivô dedicado, materialized path, soft delete) decidido em
> [`../../00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md`](../../00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md).

## Model / Schema

```
categories  (tabela única — system OU tenant)
- id
- tenant_id          // FK, null = categoria de sistema
- tenant_key         // coluna GERADA = COALESCE(tenant_id, 0) — só para o índice único
- is_system          // boolean (sistema vs. custom)
- parent_id          // FK auto-referência (hierarquia, mesmo escopo)
- depth              // profundidade (materialized path)
- path               // materialized path ("/1/4/9") — leitura de subárvore barata
- name
- normalized_name    // persistida: lowercase, sem acento, trim, espaços colapsados
- slug
- color, icon, description, status, is_featured
- deleted_at         // soft delete
- created_at, updated_at

UNIQUE(tenant_key, normalized_name)   // system-único-global + tenant-único-interno de uma vez

category_course (pivô dedicado, tenant-aware)
- tenant_id
- course_id          // FK
- category_id        // FK
- sort_order
- is_featured
```

> **Por que pivô dedicado e não polimórfico:** um `MorphToMany` único centraliza tudo numa tabela
> sem FK real (drift) e sem coluna de pivô por tipo — é o n:n gigante, não a mitigação. Produtos no
> futuro ganham seu próprio `category_product`. Ver ADR-002.

## Rules

### Tipos de categoria

| Tipo | Escopo | Dono / quem cria-edita | Área |
|------|--------|------------------------|------|
| System (`tenant_id=null, is_system=true`) | Global (todos os tenants) | Apenas developer | Mzrt |
| Custom (`tenant_id=X, is_system=false`) | Fechada ao tenant | Tenant Admin | Admin |

- O tenant **vê** todas as de sistema, mas só **seleciona** (não cria/edita/exclui). Funcionam como
  um **banco de categorias** — o tenant usa só as que fazem sentido para ele.
- Decisão por `is_system` vive na `CategoryPolicy`, nunca em `if` de controller.

### Antiduplicação (normalização)

- `normalized_name` = `name` em lowercase, sem acentuação, `trim`, espaços colapsados — **persistida**
  para indexar e comparar barato.
- **System é único global**; **tenant é único dentro do próprio tenant**. Ambos garantidos por
  `UNIQUE(tenant_key, normalized_name)` (`tenant_key = COALESCE(tenant_id, 0)` contorna o
  "NULL é distinto" do MySQL).
- **Tenant não pode colidir com nome de sistema** (ex.: existe sistema `Desenvolvimento de Software`
  → nenhum tenant cria outra com esse nome). Essa regra cruza escopos, não cabe em índice → validada
  no app (`StoreCategoryAction`).
- **Entre tenants diferentes o mesmo nome é livre** (dois tenants podem ter "Exercícios Aeróbicos").
- Fonte de verdade da unicidade = app, escopada a `whereNull('deleted_at')`; o `UNIQUE` de banco é
  rede secundária.

### Hierarquia (N níveis)

- `parent_id` + materialized path (`path`, `depth`), mantidos na escrita. Subárvore por
  `WHERE path LIKE '<path-do-nó>/%'`.
- **Parent de mesmo escopo:** system→system, tenant→mesmo tenant. Cross-escopo **proibido** (tenant
  não aninha sob categoria de sistema; usa-a por seleção, não como pai).
- Prevenção de ciclo (não setar pai em descendente próprio) na camada de aplicação.
- Mover subárvore reescreve `path`/`depth` dos descendentes (operação rara).

### Soft delete e proteção

- Categorias usam **soft delete** (mesmo `id` preservado para audit/restore).
- **System com cursos → bloqueia** (nem developer apaga categoria de sistema que tenha cursos).
- **Tenant com cursos → permite com `force`/`confirm` explícito**; ao confirmar, faz **detach** dos
  pivôs. **Invariante: nenhum pivô aponta para categoria soft-deletada.**
- Restore traz a categoria de volta **vazia** (re-tag manual). Restore cujo nome colida com categoria
  ativa → rename forçado ou recusa.

### Pivô curso × categoria

- `course_id` referencia curso do mesmo `tenant_id`.
- `category_id` pode ser de sistema (`tenant_id=null`) ou do mesmo `tenant_id` do curso.
- Nunca vincular categoria de outro tenant.
- Vínculo é escrito só pela área **Admin**, em `PUT /api/v1/admin/courses/{id}/categories`, com
  semântica de substituição total do conjunto: a posição no array vira `sort_order` (1..n) e array
  vazio limpa os vínculos. O cliente **não** envia `sort_order` — derivar da posição evita colisão
  com `UNIQUE(course_id, sort_order)`. Payload inválido não altera o vínculo existente.

### Descoberta de cursos

- `GET /catalog/courses` lista cursos publicados com filtros (`category`, `is_free`, `is_featured`).
  **Regra forte:** não exibir cursos que o aluno logado já comprou.
- O comportamento padrão da listagem permanece inalterado; o ranking só entra quando o consumidor
  pede explicitamente `sort=top_rated`.
- `sort=top_rated` aplica ordenação estável por `rating_stats` do tenant: `average_stars desc`,
  `total_ratings desc`, `courses.id asc`.
- `min_ratings` é opcional (inteiro `>= 1`) e filtra o ranking por total mínimo de avaliações.
- O payload do catálogo expõe `rating_stats` do curso para o consumidor poder renderizar a nota.
- Por ora, ranking público só existe no catálogo de cursos; aulas continuam sem superfície pública
  de ranking.
- `GET /catalog/courses/{slug}` retorna a matriz curricular completa + DTO público (preço,
  descrição) para montar a landing page.

## Endpoints

> **Escrita de categoria é área-first** (decisão D de
> [`../../00-architecture/areas-surfaces.md`](../../00-architecture/areas-surfaces.md)): categoria de
> **sistema** vive na área **Mzrt** (`v1/mzrt/categories`, só developer, sem contexto de tenant), a
> de **tenant** na área **Admin** (`v1/admin/categories`). A área decide o escopo — `is_system` é
> **proibido** no payload das duas superfícies. A listagem (sistema + tenant) permanece leitura de
> catálogo em `v1/learning/catalog`.

| Método | Path | Área | Descrição | Permission |
|--------|------|------|-----------|------------|
| GET | `/api/v1/learning/catalog/courses` | neutra | Lista cursos publicados (filtros + `sort=top_rated`) | público/auth |
| GET | `/api/v1/learning/catalog/courses/{slug}` | neutra | Curso completo p/ landing page | público/auth |
| GET | `/api/v1/learning/catalog/categories` | neutra | Listar categorias (sistema + tenant) | `learning.categories.list` |
| POST | `/api/v1/admin/categories` | Admin | Criar categoria do tenant | `learning.categories.create` |
| PUT | `/api/v1/admin/categories/{id}` | Admin | Atualizar categoria do tenant | `learning.categories.update` |
| DELETE | `/api/v1/admin/categories/{id}` | Admin | Excluir categoria do tenant (com proteção) | `learning.categories.delete` |
| POST | `/api/v1/mzrt/categories` | Mzrt | Criar categoria de sistema | `learning.categories.system.manage` |
| PUT | `/api/v1/mzrt/categories/{id}` | Mzrt | Atualizar categoria de sistema | `learning.categories.system.manage` |
| DELETE | `/api/v1/mzrt/categories/{id}` | Mzrt | Excluir categoria de sistema (bloqueia com cursos) | `learning.categories.system.manage` |
| PUT | `/api/v1/admin/courses/{id}/categories` | Admin | Sincronizar categorias do curso | `learning.courses.update` |

`GetCategoryAction` sem tenant resolve apenas categorias de sistema, então a superfície Mzrt nunca
alcança categoria de tenant (404) e não precisa de `if` de escopo no controller.

## Permissions

```
learning.categories.list · learning.categories.create · learning.categories.view
learning.categories.update · learning.categories.delete
learning.categories.system.manage   // só developer
```

## Notes

- Acesso ao catálogo público vs. autenticado depende de flag do tenant.
- Decisão de `is_system` em autorização fica na `CategoryPolicy`, não em `if` de controller.
