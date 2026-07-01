---
domain: catalog-learning
last-updated: 2026-07-01
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
- [x] ~~`GET /courses/{id}` (admin view)~~ → **re-slotado área-first** para `GET /api/v1/admin/courses/{id}` (2026-06-13): `area.guard:admin` (admin+developer por hierarquia, student/instructor → 403 `area_forbidden`) + `learning.courses.view-check` + `CourseDetailResource`. 1º slice da área Admin: carrega scaffold (`Area` enum, `EnsureAreaAccess`, `Routes/admin.php`, `Http/Controllers/Admin/`).
- [x] `POST /courses` (criar curso).
- [x] `POST /courses` cria sempre em `draft`; publicação fica exclusiva da área Admin (`learning.courses.publish`).
- [x] `PATCH /courses/{id}` (atualizar curso).
- [x] `POST /api/v1/admin/courses/{id}/publish` + `POST /api/v1/admin/courses/{id}/unpublish` (2026-06-30): área Admin, `learning.courses.publish-check`, `published_at` preservado como primeira publicação, bloqueio de `archived`, bypass de publish fechado no `POST/PATCH /api/v1/learning/courses`.
- [x] `DELETE /courses/{id}`.
- [x] `GET /courses/{id}/enrollment`.
- [x] `GET /lessons/{id}`.
- [x] `POST /lessons/{id}/progress`.
- [x] `LessonCompletedEvent`.

## In Progress

- [ ] Lesson reorder.

## Pending

### Categorias (redesign — ADR-002)
> Impl atual já tem `is_system` + `parent_id` + antiduplicação em `StoreCategoryAction`. Delta até o
> alvo do [ADR-002](../00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md):
- [ ] `normalized_name` **persistida** + helper de normalização compartilhado (lower, sem acento, trim, espaços colapsados).
- [ ] Coluna gerada `tenant_key = COALESCE(tenant_id,0)` + `UNIQUE(tenant_key, normalized_name)`.
- [ ] Materialized path: colunas `path`/`depth` + manutenção na escrita (create/move) + prevenção de ciclo.
- [ ] Regra **parent de mesmo escopo** (system→system, tenant→mesmo tenant); proibir cross-escopo.
- [ ] Pivô `category_course`: adicionar `order` + `is_featured`, FK reais; ajustar relação `Category::courses()`.
- [ ] Soft delete + proteção no delete: system com cursos **bloqueia**; tenant com cursos exige `force/confirm` + **detach** dos pivôs (invariante: nenhum pivô → categoria soft-deletada).
- [ ] Re-slot área-first: system → Mzrt (`v1/mzrt`), tenant → Admin (`v1/admin`); `CategoryPolicy` decide por `is_system`.

### Courses
- [ ] Attach categories to courses (usa pivô dedicado + `order`/`is_featured`).

### Modules
- [x] `POST /modules` (criação mínima; tenant isolation + sort_order automático).
- [x] `GET /modules/{id}`.
- [x] `PATCH /modules/{id}`.
- [x] `DELETE /modules/{id}`.
- [x] `PATCH /modules/reorder`.

### Lessons
- [x] `POST/PATCH/DELETE /lessons` (CRUD).
- [x] `POST /lessons`.
- [x] `DELETE /lessons/{id}`.
- [x] `PATCH /lessons/{id}`.
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

### Reuso eadIA (a importar — ver ADR-001)
> **Curso-core revisado (2026-06-13):** ead2026 já é mais rico que o eadIA em `courses`/`lessons`
> (short_description, target_audience, requirements, level, order, thumbnail/banner, duration,
> vehiculation dates, certificate_*, price_cents). Trazer só os gaps abaixo. **Pular:**
> `access_months` (temos `access_days`), `image_cover` (temos `banner`), `country_id` em category
> (só multi-tenant), `CoursePrice` por país, `content_data` blob (LessonMedia substitui).
- [ ] **i18n traduzível** em `title`/`description`/`short_description` de Course/Module/Lesson/Category (JSON por locale, **com fallback**).
- [ ] **`is_fifo`** (sequência linear) no curso.
- [ ] **`meta_title`/`meta_description`** (JSON, SEO) — para a área Home/landing.
- [ ] **Lesson media** (`LessonMedia` + `LessonMediaProgress`): video/streaming/audio/document, subtypes YouTube/Vimeo/AWS, `progress_strategy` (80%/full/manual/time_based), `MediaEmbedService`. Usar `medialibrary` p/ uploads.
- [ ] **Regras de acesso** (eadIA `enrollment-access-rules.md`): `canViewCourse()`/`canAccessPaidContent()`/`canAccessLesson()` — free, is_free degustação, expirado vê vitrine mas não conteúdo pago.
- [ ] **Matrícula manual por instrutor**: config tenant (`instructor_can_create_free_enrollments`, `..._mark_external_payment`, `..._requires_approval`), `enrollment_type`/`payment_type`/`created_by_instructor_id`.
- [ ] **Ratings** (`Rating` + `RatingStats`, polimórfico curso/aula, rollup) e **Materials** (`CourseMaterial`/`MaterialDownload`/`MaterialStats` via medialibrary).

## Needs Review

- _(nenhuma)_

## Open Questions

- _(nenhuma)_ — modelo de acesso/conteúdo **resolvido** (2026-06-10): `price_cents`+`access_days`
  canônicos, `content_type` ortogonal mantido, modos de matrícula só `open` no MVP. Decisão em
  [`subspecs/courses-modules-lessons.md`](subspecs/courses-modules-lessons.md); CRUD de
  courses/lessons **desbloqueado**.
