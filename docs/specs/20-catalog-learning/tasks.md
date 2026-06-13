---
domain: catalog-learning
last-updated: 2026-06-13
---

# Tasks — Catalog & Learning

Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

- [x] Models `Course`, `Category`, `CourseModule`, `Lesson`, `Enrollment`, `LessonProgress` (+ factories).
- [x] Migrations com soft deletes, `tenant_id`, categorias hierárquicas.
- [x] Categorias: `is_system` + `parent_id`; antiduplicação contra padrão global (`StoreCategoryAction`).
- [x] Pivot `category_course` tenant-aware.
- [x] Policies: `CategoryPolicy`, `CoursePolicy`, `CourseModulePolicy`, `EnrollmentPolicy`, `LessonPolicy`.
- [x] `GET /catalog/courses` (filtros category/is_free/is_featured).
- [x] `GET /catalog/courses/{slug}`.
- [x] `GET /catalog/categories`.
- [x] `POST /catalog/categories`.
- [x] `PUT /catalog/categories/{id}`.
- [x] `DELETE /catalog/categories/{id}`.
- [x] `GET /courses/{id}/modules`.
- [x] `POST /courses` (criar curso).
- [x] `PATCH /courses/{id}` (atualizar curso).
- [x] `DELETE /courses/{id}`.
- [x] `GET /courses/{id}/enrollment`.
- [x] `GET /lessons/{id}`.
- [x] `POST /lessons/{id}/progress`.
- [x] `LessonCompletedEvent`.

## In Progress

- _(nenhuma)_

## Pending

### Courses
- [ ] `GET /courses/{id}` (admin view).
- [ ] `POST /courses/{id}/publish` + `POST /courses/{id}/unpublish`.
- [ ] Attach categories to courses.

### Modules
- [ ] `POST/GET/PATCH/DELETE /modules` (CRUD).
- [ ] `PATCH /modules/reorder`.

### Lessons
- [ ] `POST/PATCH/DELETE /lessons` (CRUD).
- [ ] Lesson reorder.
- [ ] Preview de cursos draft para instrutor/admin.

### Enrollment & Progress
- [ ] `POST /enrollments` (matrícula manual + auto via OrderPaidEvent).
- [ ] `GET/PATCH/DELETE /enrollments` (CRUD).
- [ ] Fluxo do aluno: enrollment → access → progress.

### Media & Ratings
- [ ] `CourseMaterial` model + download tracking (`MaterialDownload`/`MaterialStats`).
- [ ] `LessonView` (estatísticas de replay) + `LessonViewedEvent`.
- [ ] `Rating` / `RatingStats` (1-5 estrelas, like/dislike, rollup).
- [ ] Pre-signed URLs para mídia (AWS S3, Vimeo).
- [ ] `LessonMedia` / `LessonMediaProgress` + `ProgressStrategy` configurável.

### Eventos
- [ ] Disparar `EnrollmentCreated`.

## Needs Review

- _(nenhuma)_

## Open Questions

- _(nenhuma)_ — modelo de acesso/conteúdo **resolvido** (2026-06-10): `price_cents`+`access_days`
  canônicos, `content_type` ortogonal mantido, modos de matrícula só `open` no MVP. Decisão em
  [`subspecs/courses-modules-lessons.md`](subspecs/courses-modules-lessons.md); CRUD de
  courses/lessons **desbloqueado**.
