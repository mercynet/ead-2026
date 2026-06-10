---
domain: catalog-learning
parent: ../spec.md
resource: courses-modules-lessons
last-reviewed: 2026-06-10
---

# Courses, Modules & Lessons

## Model / Schema

```
courses
- id
- tenant_id              // FK
- instructor_id          // FK (criador)
- title, slug, description, thumbnail
- status                 // published | draft | archived
- is_published, is_active
- price_cents            // valor em centavos (ver api-conventions.md)
- access_days            // presets: 30, 90, 180, 365, 0 (vitalício)
- is_free
- certificate_enabled        // boolean
- certificate_min_progress   // %
- certificate_requires_quiz  // boolean
- certificate_min_score      // %
- created_at, updated_at     // soft deletes

course_modules
- id
- tenant_id, course_id   // FK
- title, description
- sort_order, is_active
- created_at, updated_at

lessons
- id
- tenant_id, course_module_id  // FK
- title, slug, description
- content                // JSON
- duration_minutes
- sort_order
- is_free                // aula degustação
- is_active
- created_at, updated_at
```

## Rules

- **Course** é o agrupador raiz; soft deletes; atrelado a categorias via pivô; tem N módulos.
  `access_days` usa lista fechada de presets (30/90/180/365/0=vitalício).
- **CourseModule** pertence a um único curso e é reordenável. Ao criar módulo, o filtro de
  categorias exibe apenas categorias onde o instrutor já tem cursos.
- **Lesson** organizada dentro de módulo; reordenável; pode ser degustação (`is_free`).
- **Fluxo de publicação:** instrutor cria draft → adiciona módulos/aulas → configura certificado
  → publica (`is_published=true`) → alunos veem e matriculam.
- **Drafts** não acessíveis por alunos; rota de preview restrita a instrutor dono / tenant_admin / developer.

### Open Question — conciliar modelos divergentes

As duas fontes de spec divergiram sobre como modelar acesso e tipo de conteúdo. **Decisão
pendente** (registrada em [`../tasks.md`](../tasks.md)):

- Modelo A (catalog-learning, atual): `course.is_free` + `access_days` (presets de expiração).
- Modelo B (learning legado): `enrollment_type: open | invite_only | sales` e
  `content_type: video | text | quiz | assignment` na lição.

Conciliar antes de implementar o CRUD completo de courses/lessons.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/learning/courses` | Criar curso | `learning.courses.create` |
| GET | `/api/v1/learning/courses/{id}` | Ver curso (admin) | `learning.courses.view` |
| PATCH | `/api/v1/learning/courses/{id}` | Atualizar curso | `learning.courses.update` |
| DELETE | `/api/v1/learning/courses/{id}` | Deletar curso | `learning.courses.delete` |
| GET | `/api/v1/learning/courses/{id}/modules` | Árvore do curso + tracking | `learning.courses.view` |
| POST | `/api/v1/learning/courses/{id}/publish` | Publicar curso | `learning.courses.publish` |
| POST | `/api/v1/learning/courses/{id}/unpublish` | Despublicar curso | `learning.courses.publish` |
| POST | `/api/v1/learning/modules` | Criar módulo | `learning.modules.create` |
| GET | `/api/v1/learning/modules/{id}` | Ver módulo | `learning.modules.view` |
| PATCH | `/api/v1/learning/modules/{id}` | Atualizar módulo | `learning.modules.update` |
| DELETE | `/api/v1/learning/modules/{id}` | Deletar módulo | `learning.modules.delete` |
| PATCH | `/api/v1/learning/modules/reorder` | Reordenar módulos | `learning.modules.reorder` |
| POST | `/api/v1/learning/lessons` | Criar aula | `learning.lessons.create` |
| GET | `/api/v1/learning/lessons/{id}` | Ver aula (pre-signed URL) | `learning.lessons.view` / acesso |
| PATCH | `/api/v1/learning/lessons/{id}` | Atualizar aula | `learning.lessons.update` |
| DELETE | `/api/v1/learning/lessons/{id}` | Deletar aula | `learning.lessons.delete` |

## Permissions

```
learning.courses.{list,create,view,update,delete,publish}
learning.modules.{list,create,view,update,delete,reorder}
learning.lessons.{list,create,view,update,delete}
```

Matriz por UserType em [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §4 (Learning).

## Notes

- Config de certificado mora nas colunas `certificate_*` de `courses`; lógica de emissão é do
  domínio Assessment (`30-assessment/subspecs/certificates.md`).
- Acesso à aula resolve pre-signed URL (sem proxy binário) — ver
  [`media-ratings.md`](media-ratings.md) e
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
