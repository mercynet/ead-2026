---
domain: catalog-learning
last-updated: 2026-07-11
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
- [x] `POST /enrollments` (matrícula manual).
- [x] Matrícula manual **gratuita** por instrutor quando o tenant habilita `learning.enrollments.manual_free_by_instructor` em `tenant_customizations.published_settings`, com auditoria via `created_by_instructor_id`.
- [x] Delta de aprovação entregue: `learning.enrollments.manual_free_requires_approval` faz a matrícula manual gratuita por instrutor nascer `pending` em vez de `active`.
- [x] `GET/PATCH/DELETE /enrollments` (CRUD).
- [x] `GET /lessons/{id}`.
- [x] `POST /lessons/{id}/progress`.
- [x] `LessonCompletedEvent`.
- [x] Regras explícitas `canViewCourse` / `canAccessPaidContent` / `canAccessLesson` + flags de acesso na árvore de módulos.
- [x] E2E HTTP do fluxo aluno: módulos do curso, show de aula e heartbeat/progresso contra app rodando.
- [x] `LessonMedia` mínimo: model/migration + mídia/conteúdo retornado só quando `canAccessLesson()` permitir.
- [x] E2E HTTP Admin: criar módulo, criar aula, publicar e despublicar curso contra app rodando.
- [x] CRUD/gestão de `LessonMedia` para instrutor/admin (`POST /lessons/{lessonId}/media`, `PATCH/DELETE /lessons/{lessonId}/media/{mediaId}`).
- [x] `CourseMaterial` base: model + migration + `POST /courses/{courseId}/materials`.
- [x] `MaterialDownload` base: model + migration + `POST /courses/{courseId}/materials/{materialId}/downloads` + `MaterialDownloadedEvent`.
- [x] `MaterialStats` rollup/counters sobre `MaterialDownload`.
- [x] `POST /courses/{courseId}/materials/{materialId}/downloads` devolve URL temporária de download (sem proxy binário).
- [x] **P1.1 (auditoria 2026-07-11):** `access_days = 0` = vitalício (`access_expires_at = null`);
  presets 0/30/90/180/365 validados nos FormRequests de curso.
- [x] **P1.5 (auditoria 2026-07-11):** progresso conta só lessons `published + is_active`;
  `enrollment.completed_at` estampado ao atingir 100%.
- [x] **P1.4 (auditoria 2026-07-11):** `LessonPolicy` alinhada ao padrão canônico (developer
  bypass + matriz do config); progresso de aula restrito a developer/student.
- [x] **P0.2 (auditoria 2026-07-11):** hardening de `file_path` de material — prefixo
  `tenants/{tenant_id}/` + anti-traversal no FormRequest; revalidação + allowlist de disk na
  geração da URL assinada (defesa em profundidade); URL gerada antes de registrar download.
- [x] `POST /courses/{id}/ratings` (student-only para curso ativo/publicado, com rollup em `RatingStats`).
- [x] `POST /lessons/{id}/ratings` (student-only para aula acessível/ativa, com rollup em `RatingStats`).
- [x] Ranking por tenant para `RatingStats` exposto no catálogo de cursos via `GET /api/v1/learning/catalog/courses?sort=top_rated`.
- [x] Pre-signed URLs para `LessonMedia` `internal`/`s3` no `GET /lessons/{id}`.
- [x] Fechar contrato de providers externos de `LessonMedia` (ex.: Vimeo/embed) no mesmo envelope de leitura.
- [x] Definir subtipos/contrato avançado de `LessonMedia` (YouTube/Vimeo/AWS via `s3`) junto de `LessonMediaProgress` e `ProgressStrategy`.

## In Progress

- [ ] Registrar o espelho financeiro das matrículas manuais/gratuitas a partir de `EnrollmentCreatedEvent`.

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
- [x] Lesson reorder.
- [x] Preview de cursos draft para instrutor/admin.

### Enrollment & Progress

- [x] Regras de acesso a curso/aula devem consumir a matrícula corrente (`pending|active`) sem ambiguidade com histórico.

### Media & Ratings
- [x] `LessonView` (estatísticas de replay) + `LessonViewedEvent`.
- [x] `Rating` / `RatingStats` para **Course** (1-5 estrelas, like/dislike, rollup, student-only, update own).
- [x] `Rating` / `RatingStats` para **Lesson** (1-5 estrelas, like/dislike, rollup, student-only, update own).
- [x] `LessonMedia` / `LessonMediaProgress` + `ProgressStrategy` configurável.

### Eventos
- [x] Consumir `OrderPaidEvent` (Financial) para matrícula automática via `EnrollService`.
- [x] Disparar `EnrollmentCreated`.

### Reuso eadIA (a importar — ver ADR-001)
> **Curso-core revisado (2026-06-13):** ead2026 já é mais rico que o eadIA em `courses`/`lessons`
> (short_description, target_audience, requirements, level, order, thumbnail/banner, duration,
> vehiculation dates, certificate_*, price_cents). Trazer só os gaps abaixo. **Pular:**
> `access_months` (temos `access_days`), `image_cover` (temos `banner`), `country_id` em category
> (só multi-tenant), `CoursePrice` por país, `content_data` blob (LessonMedia substitui).
- [ ] **i18n traduzível** em `title`/`description`/`short_description` de Course/Module/Lesson/Category (JSON por locale, **com fallback**).
- [ ] **`is_fifo`** (sequência linear) no curso.
- [ ] **`meta_title`/`meta_description`** (JSON, SEO) — para a área Home/landing.
- [x] **Lesson media avançado** (`LessonMediaProgress`): subtypes YouTube/Vimeo/AWS (`provider=s3`), `progress_strategy` (80%/full/manual/time_based), contrato normalizado de `provider_config`/`progress_config`. Upload real/`MediaEmbedService` continuam fora deste slice.
- [x] **Matrícula manual por instrutor (delta restante)**: `POST /enrollments` agora aceita `billing_type=external` para instrutor em curso pago, persiste o marcador, cria a matrícula como `pending` e preserva `created_by_instructor_id`; curso grátis com `external` retorna `422`.
- [ ] **Ratings** (`Rating` + `RatingStats`) — delta restante: ranking por tenant; **Materials** (`CourseMaterial`/`MaterialDownload`/`MaterialStats` via medialibrary).

## Needs Review

- [x] Alinhar implementação de `Enrollment` ao contrato revisado: status `pending|active|cancelled|expired`, rematrícula para `cancelled/expired`, progresso fora do status da matrícula.

## Open Questions

- _(nenhuma)_ — modelo de acesso/conteúdo **resolvido** (2026-06-10): `price_cents`+`access_days`
  canônicos, `content_type` ortogonal mantido, modos de matrícula só `open` no MVP. Decisão em
  [`subspecs/courses-modules-lessons.md`](subspecs/courses-modules-lessons.md); CRUD de
  courses/lessons **desbloqueado**.
