---
domain: catalog-learning
parent: ../spec.md
resource: courses-modules-lessons
last-reviewed: 2026-09-06
---

# Courses, Modules & Lessons

## Model / Schema

```
courses
- id
- tenant_id              // FK
- instructor_id          // FK (ownership pedagógico; nullable em curso operado pelo Admin)
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

course_price_histories
- id, tenant_id, course_id, changed_by_user_id // FKs; changed_by_user_id nullable
- old_price_cents, new_price_cents             // inteiros em centavos
- changed_at

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
- **Fluxo de publicação Admin:** curso ativo e não arquivado → pelo menos um módulo → pelo menos uma
  aula do curso, publicada e ativa → `publish`. Curso sem `instructor_id` é válido e administrável
  pelo tenant; Admin não vira Instructor.
- **Readiness mínimo:** quiz, mídia e preço não são pré-condições. A aula deve ser publicada em
  transição própria; publicar o curso não publica aulas por efeito colateral. Falha de readiness não
  altera estado. `archived` é terminal no MVP para publish/unpublish.
- **Drafts** não acessíveis por alunos; rota de preview restrita a instrutor dono / tenant_admin / developer.
- **Histórico de preço:** `course_price_histories` é append-only e específico de `Course`; não há
  histórico polimórfico/genérico no Financial. Registrar somente atualização real de
  `Course.price_cents` — nunca criação do curso nem valor idêntico — na mesma transação do update
  com `Course` bloqueado. O ator deve ser explícito; `changed_by_user_id` pode ser nulo apenas
  quando não houver usuário responsável. Não há `reason` livre neste momento. Futuro update em
  lote deve preservar mesma cadeia de auditoria por curso.
- **Preço contratado:** Financial é dono do `price_cents` e `item_snapshot` imutáveis em
  `OrderItem`; histórico Learning não altera orders já criadas.
- **Fronteira Admin ↔ Instructor:** Admin opera o conteúdo do próprio tenant; Instructor mantém a
  autoria/ownership do curso próprio nas rotas legacy de authoring. Um curso criado na superfície
  Admin nasce sem `instructor_id`; este slice não cria uma operação de atribuição de autor nem
  transforma Admin em Instructor.

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

### Gestão de conteúdo — Admin (superfície canônica)

Admin controla o conteúdo do próprio tenant por esta superfície area-first. Todas as rotas abaixo
usam `area.guard:admin`, `tenant.required.unless.developer` e `tenant.access`; o payload não pode
redefinir `tenant_id`, ownership, parent ou status quando esses valores são derivados do contexto,
da URL ou de uma transição dedicada.

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET/POST/PATCH/DELETE | `/api/v1/admin/courses[/{id}]` | Listar, criar, atualizar e remover curso | `learning.courses.{list,create,update,delete}` |
| GET | `/api/v1/admin/courses/{id}` | Ver curso administrativo com módulos/aulas | `learning.courses.view` |
| GET | `/api/v1/admin/courses/{courseId}/modules` | Listar módulos do curso | `learning.modules.list` |
| POST | `/api/v1/admin/modules` | Criar módulo | `learning.modules.create` |
| GET/PATCH/DELETE | `/api/v1/admin/modules/{id}` | Ver, atualizar e remover módulo | `learning.modules.{view,update,delete}` |
| PATCH | `/api/v1/admin/modules/reorder` | Reordenar módulos | `learning.modules.reorder` |
| GET | `/api/v1/admin/modules/{moduleId}/lessons` | Listar aulas do módulo | `learning.lessons.list` |
| POST | `/api/v1/admin/lessons` | Criar aula | `learning.lessons.create` |
| GET/PATCH/DELETE | `/api/v1/admin/lessons/{id}` | Ver, atualizar e remover aula administrativa | `learning.lessons.{view,update,delete}` |
| POST | `/api/v1/admin/lessons/{id}/publish` | Publicar aula explicitamente | `learning.lessons.update` |
| POST | `/api/v1/admin/lessons/{id}/unpublish` | Despublicar aula explicitamente | `learning.lessons.update` |
| PATCH | `/api/v1/admin/lessons/reorder` | Reordenar aulas | `learning.lessons.reorder` |

As rotas equivalentes em `/api/v1/learning` continuam como compatibilidade legacy e preservam a
matriz `own` do Instructor. A superfície Admin não expõe consumo, progresso, download ou tracking
de aluno; uma atribuição explícita de ownership para cursos criados pelo Admin permanece um delta
futuro fora do ADM-02.

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
- O contrato de `publish` exige curso ativo, não archived, um módulo e uma lesson publicada/ativa
  pertencente a esse curso. A operação Admin de Lesson usa transições explícitas
  `publish/unpublish`, sob o teto da permission `learning.lessons.update`; CRUD genérico não altera
  status.
- Acesso à aula resolve pre-signed URL (sem proxy binário) — ver
  [`media-ratings.md`](media-ratings.md) e
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
