---
domain: catalog-learning
last-updated: 2026-09-06
---

# Tasks — Catalog & Learning

Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

> `[x]` registra slice entregue; não é promoção automática para `RUNTIME_VERIFIED`. O delta aberto
> fica exclusivamente em `Pending`.

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
- [x] ~~`GET /courses/{id}` (admin view)~~ → **re-slotado área-first** para `GET /api/v1/admin/courses/{id}` (2026-06-13): `area.guard:admin` com persona exata (somente admin; demais tipos → 403 `area_forbidden`) + `learning.courses.view-check` + `CourseDetailResource`. 1º slice da área Admin: carrega scaffold (`Area` enum, `EnsureAreaAccess`, `Routes/admin.php`, `Http/Controllers/Admin/`).
  `Journey: ADMIN-OPS | Area: admin | Depends on: FOUNDATION-0`
- [x] `POST /courses` (criar curso).
- [x] `POST /courses` cria sempre em `draft`; publicação fica exclusiva da área Admin (`learning.courses.publish`).
- [x] `PATCH /courses/{id}` (atualizar curso).
- [x] `CoursePriceHistory`: histórico append-only, tenant-aware e específico de curso para mudanças
  reais de `price_cents`, gravado com ator explícito na mesma transação do update bloqueado.
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
- [x] Registrar espelho financeiro atômico para concessão manual sem `billing_type` via `EnrollmentCreatedEvent`: `Order`/`OrderItem`/`Payment` zero-consideration idempotentes por matrícula; inclui catálogo pago e pendente de aprovação. `Journey: ADMIN-OPS | Area: admin | Depends on: Financial orders/payments`. Matrícula manual externa permanece fora deste espelho; reconciliação financeira externa e decisão de espelhar após aprovação ficam pendentes.

### Slices entregues posteriormente

- [x] Pivô `category_course`: `sort_order` + `is_featured`, FKs reais incluindo integridade tenant↔curso, unicidade de categoria/ordem por curso e relações ordenadas em `Course::categories()`/`Category::courses()`.
- [x] Soft delete + proteção no delete: system com cursos **bloqueia**; tenant com cursos exige `force`+`confirm` no `DeleteCategoryRequest` e faz **detach** sob lock antes do soft delete.
- [x] Re-slot área-first de categorias: system → Mzrt (`v1/mzrt/categories`), tenant → Admin (`v1/admin/categories`); escrita removida de `v1/learning/catalog/categories`, que fica só com o `GET`; `is_system` proibido nos payloads. `Journey: ADMIN-OPS | Area: admin + mzrt | Depends on: FOUNDATION-0`
- [x] Attach categories to courses: `PUT /api/v1/admin/courses/{id}/categories` substitui o conjunto pelo pivô dedicado, derivando `sort_order` da posição e rejeitando payload inválido sem alterar vínculos.
- [x] CRUD e reorder de módulos: `POST /modules`, `GET /modules/{id}`, `PATCH /modules/{id}`, `DELETE /modules/{id}`, `PATCH /modules/reorder`.
- [x] CRUD, reorder e preview de lessons para cursos draft: `POST/PATCH/DELETE /lessons`, `Lesson reorder`, preview para instrutor/admin.
- [x] Regras de acesso a curso/aula consomem a matrícula corrente (`pending|active`) sem ambiguidade com histórico.
- [x] `LessonView` + `LessonViewedEvent`; `Rating`/`RatingStats` para Course e Lesson, com rollup, student-only e update own.
- [x] `LessonMedia` / `LessonMediaProgress` + `ProgressStrategy` configurável.
- [x] Learning consome `OrderPaidEvent` via `EnrollService` e dispara `EnrollmentCreated`.
- [x] **Ownership `own` de instructor (2026-07-11)** implementado em courses, modules, lessons e lesson media; gates `-check`; teste `InstructorOwnershipTest` (18).
- [x] **Lesson media avançado**: subtypes YouTube/Vimeo/AWS (`provider=s3`), `progress_strategy` e contratos normalizados de configuração; upload real/`MediaEmbedService` permanecem fora deste slice.
- [x] **Matrícula manual por instrutor (`billing_type=external`)**: curso pago cria matrícula `pending` e preserva `created_by_instructor_id`; curso grátis retorna `422`.
- [x] Alinhar `Enrollment` ao contrato revisado: status `pending|active|cancelled|expired`, rematrícula de `cancelled/expired` e progresso fora do status.
- [x] **ADM-02 — superfície Admin canônica de conteúdo (2026-09-06):** CRUD e reorder de cursos,
  módulos e aulas, materiais e mídia administrativa em `/api/v1/admin`, com isolamento tenant,
  guard de área e payloads de escopo proibidos. A criação Admin não atribui ownership pedagógico;
  as rotas legacy `/api/v1/learning` permanecem compatíveis para autoria do Instructor.
- [x] **Publication readiness Admin (2026-09-06):** `POST /api/v1/admin/lessons/{id}/publish` e
  `unpublish` são transições explícitas, sem mutação de status pelo CRUD; `publish` de curso exige
  curso ativo, não arquivado, módulo e Lesson publicada/ativa, preservando estado em falha.
- [x] **Categorias System/Custom Admin (2026-09-06):** `tenant_key`/unicidade semântica,
  normalização compartilhada, parent de mesmo escopo, materialized path com move/cycle guard e
  Resource público `type: system|custom` foram convergidos nas superfícies Admin/Mzrt.
- [x] **ADM-03 — matrícula Admin cash/manual (2026-09-06):** CRUD tenant-scoped em
  `/api/v1/admin/enrollments`, payloads de escopo/ownership proibidos, concessão manual com espelho
  financeiro idempotente e confirmação cash via outbox validada por E2E HTTP real. Matrícula externa,
  webhooks e automação de gateway permanecem fora deste slice.

## In Progress

- _(nenhuma)_

## Pending

> Somente deltas ainda abertos permanecem aqui. Histórico entregue não é repetido nesta seção.

### Categorias (redesign — ADR-002)
> Entregue em 2026-09-06: `normalized_name` é persistida por helper compartilhado; o banco mantém
> `tenant_key = COALESCE(tenant_id,0)` e a unicidade canônica; `path`/`depth` são mantidos em
> create/move com prevenção de ciclo; parents são do mesmo escopo; Resources expõem apenas
> `type: system|custom` (`is_system` permanece interno e proibido nos payloads).

### Reuso eadIA (a importar — ver ADR-001)
> **Curso-core revisado (2026-06-13):** ead2026 já é mais rico que o eadIA em `courses`/`lessons`
> (short_description, target_audience, requirements, level, order, thumbnail/banner, duration,
> vehiculation dates, certificate_*, price_cents). Trazer só os gaps abaixo. **Pular:**
> `access_months` (temos `access_days`), `image_cover` (temos `banner`), `country_id` em category
> (só multi-tenant), `CoursePrice` por país, `content_data` blob (LessonMedia substitui).
- [ ] **i18n traduzível** em `title`/`description`/`short_description` de Course/Module/Lesson/Category (JSON por locale, **com fallback**).
- [ ] **`is_fifo`** (sequência linear) no curso.
- [ ] **`meta_title`/`meta_description`** (JSON, SEO) — para a área Home/landing.
- [ ] Upload real / `MediaEmbedService` e o `MediaProvider` no módulo dono da mídia.
- [ ] Integração de `CourseMaterial` com media library/upload real (o slice atual cobre metadados/path e URL temporária).
- [ ] **UNKNOWN — Ratings:** confirmar se existe delta adicional além dos slices históricos de Course/Lesson e ranking por tenant; os registros anteriores misturavam capability entregue com “delta restante”.
- [ ] Reconciliação financeira da matrícula manual externa e decisão de espelhar após aprovação.

## Needs Review

- _(nenhuma)_ — slices históricos entregues foram movidos para *Done*; deltas abertos permanecem em
  *Pending*.

## Open Questions

- _(nenhuma)_ — modelo de acesso/conteúdo **resolvido** (2026-06-10): `price_cents`+`access_days`
  canônicos, `content_type` ortogonal mantido, modos de matrícula só `open` no MVP. Decisão em
  [`subspecs/courses-modules-lessons.md`](subspecs/courses-modules-lessons.md); CRUD de
  courses/lessons **desbloqueado**.
