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

### Decisão — modelo de acesso/conteúdo (resolvido 2026-06-10)

Verificado contra o schema atual **e** o legado `eadIA`: o "conflito" não existia no código.

- **Acesso/preço (canônico, já implementado):** `course.price_cents` (`0` = grátis) + `access_days`
  (presets 30/90/180/365/`0`=vitalício). **Não há coluna `course.is_free`** — "grátis" é derivado de
  `price_cents == 0` (inclusive no filtro do catálogo).
- **`content_type` (lesson):** mantido — **ortogonal** ao preço (tipo de conteúdo: video/text/quiz).
  Presente no schema atual e no legado; nunca conflitou com `is_free`/`access_days`.
- **`lesson.is_free`:** degustação/preview da aula (mantido).
- **Modos de matrícula:** apenas **`open`** no MVP (matrícula aberta; grátis se `price_cents == 0`,
  paga via checkout). `enrollment_type: invite_only | sales` nunca existiu (nem código nem legado) →
  **diferido** (YAGNI), registrado em [`../../../ROADMAP.md`](../../../ROADMAP.md). Vira coluna
  `enrollment_type` só quando o produto exigir convite/funil de vendas.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/learning/courses` | Criar curso (sempre nasce `draft`) | `learning.courses.create` |
| GET | `/api/v1/admin/courses/{id}` | Ver curso (Admin area-first) | `learning.courses.view` |
| GET | `/api/v1/learning/courses/{id}/preview` | Preview de curso draft para owner/admin/developer | `learning.courses.view` |
| PATCH | `/api/v1/learning/courses/{id}` | Atualizar curso | `learning.courses.update` |
| DELETE | `/api/v1/learning/courses/{id}` | Deletar curso | `learning.courses.delete` |
| GET | `/api/v1/learning/courses/{id}/modules` | Árvore do curso + tracking | `learning.courses.view` |
| POST | `/api/v1/admin/courses/{id}/publish` | Publicar curso (Admin) | `learning.courses.publish` |
| POST | `/api/v1/admin/courses/{id}/unpublish` | Despublicar curso (Admin) | `learning.courses.publish` |
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
- `POST/PATCH /api/v1/learning/courses` não publicam nem arquivam curso; a transição de status fica
  concentrada na superfície Admin via `publish/unpublish`.
- Acesso à aula resolve pre-signed URL (sem proxy binário) — ver
  [`media-ratings.md`](media-ratings.md) e
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
