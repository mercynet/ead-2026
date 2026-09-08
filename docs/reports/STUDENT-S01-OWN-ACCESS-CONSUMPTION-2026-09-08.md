# Student S-01 — Own Access & Consumption

Data: 2026-09-08
Projeto: Sistema de EAD
Verdict do slice: **`S01_COMPLETE`**
Estado comercial: permanece **`PAID_PILOT_NOT_READY`**.

## 1. Baseline

O baseline era `STUDENT_PARTIAL`, com checkout área-first e consumo Learning domínio-first. A
decisão vigente exige Enrollment própria `active` e não expirada para consumo integral, inclusive
em Course gratuito; preview de Lesson `is_free` é read-only e sem side effect.

Proveniência: último commit `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`; alterações S-01 estão no
working tree, sem stage, commit ou push. Alterações pré-existentes de Instructor/Assessment foram
preservadas.

## 2. RED Evidence

`RED_CONFIRMED` antes da implementação: `GET /api/v1/student/courses` inexistente; consumo legacy
sem `area.guard:student`; risco de `CURRENT_STATUSES` confundir `pending` com acesso corrente;
árvore/Resources legacy com risco de drafts e metadata técnica/path; ausência de prova canônica de
own scope, tenant isolation e URL após authorization. A superfície legacy continua compatibilidade
histórica e não foi promovida a Student canonical.

## 3. Routes

Em `app/Modules/Learning/Routes/student.php`, todas sob `resolve.tenant.optional`, `api.context`,
`auth:sanctum`, `area.guard:student`, `tenant.required.unless.developer`, `tenant.access`:

- `GET /api/v1/student/courses`
- `GET /api/v1/student/courses/{courseId}`
- `GET /api/v1/student/courses/{courseId}/modules`
- `GET /api/v1/student/courses/{courseId}/modules/{moduleId}/lessons`
- `GET /api/v1/student/lessons/{lessonId}`
- `GET /api/v1/student/lessons/{lessonId}/media`
- `POST /api/v1/student/lessons/{lessonId}/progress`
- `GET /api/v1/student/courses/{courseId}/materials`
- `POST /api/v1/student/courses/{courseId}/materials/{materialId}/downloads`

## 4. My Courses

`ListStudentCoursesAction` fixa tenant + actor, filtra Course `published`/`active` e Enrollment
própria `active` com expiração nula/futura. Usa cursor pagination, ordem determinística e eager
loading. Curso gratuito não cria auto-enrollment.

Feature cobre exclusão de pending, expired, cancelled, active expirado, draft, inactive e foreign
tenant. O Resource é uma projeção Student própria.

## 5. Enrollment Access

`ResolveStudentAccessAction` aplica o boundary canônico para Course, Lesson, Material e progresso.
Entitlement ausente retorna 404 defensivo; 401, `area_forbidden` e `access_denied` mantêm a
semântica da stack.

`EnrollmentResource` expõe somente id, course_id, status, is_active, enrolled_at,
access_expires_at e progress_percentage; não expõe tenant, financeiro, order/payment, actor de
criação ou outro Student.

## 6. Course Tree

Course show, Modules e Lessons são navegação incremental e paginada. A árvore não carrega conteúdo,
mídia ou material. Modules pertencem ao Course/tenant e precisam de Lesson visível; Lessons são
somente `published` + `active`, ordenadas por `sort_order`/id. Course pai precisa ser publicado e
ativo.

## 7. Lessons

O detalhe autoriza Lesson → Module → Course antes de devolver conteúdo textual, descrição
pedagógica, duração, indicador free/access mode, mídia ativa e progresso próprio. Draft/inactive,
parent inconsistente, foreign tenant e Enrollment não autorizada não são consumíveis.

Preview de Lesson `is_free` não cria LessonView/Event, não cria/atualiza LessonProgress, não libera
material e não inicia Assessment. Consumo enrolled registra a visualização existente.

## 8. Media

Somente mídia ativa é projetada por `LessonMediaResource`, com id, type, provider público, URL
consumível, tipo/expiração da URL, duração, estratégia/configuração pedagógica mínima e ordem.
Não são retornados `file_path`, `storage_path`, `disk`, provider secrets, provider_ref interno,
metadata genérica, ownership ou tenant internals. A resolução ocorre depois da autorização.

## 9. Materials

Listagem e download exigem primeiro Course acessível e depois material do Course/tenant. A URL
temporária é gerada somente após authorization e o side effect `MaterialDownload` é registrado.
Resources Student não expõem `file_path`, `storage_path`, `instructor_id`, `user_id` ou `tenant_id`.

## 10. Ownership / PII

Student A não alcança Course/Lesson/Media/Material de B no mesmo tenant. Foreign tenant e IDs
inexistentes usam 404 defensivo; tenant header incompatível é 403 `access_denied`. Não foi
adicionado PII novo; Resources Admin/Instructor não são reutilizados.

## 11. Performance

My Courses usa cursor pagination e eager loading. O query-log smoke compara dataset pequeno/maior e
passou com contagem maior limitada a `small + 1`. Course tree é incremental, sem árvore recursiva ou
payload de material/mídia desnecessário; não foi imposto SLA arbitrário.

## 12. Security

Feature/E2E cobrem 401, 403 area/RBAC, same-tenant A/B, cross-tenant, Enrollment missing/pending/
expired/cancelled/active-expired, Course draft/inactive, Lesson draft/inactive, media/material
foreign, IDs inexistentes, PII e storage-path leakage. A dívida legacy sem area guard está
identificada e fora da superfície canonical.

## 13. Feature / Architecture

- Feature S-01: **12 testes, 407 asserções, verde**.
- Architecture completa: **36 testes, 1.325 asserções, verde**; inclui area guard, boundaries e
  isolamento de controllers/Resources.
- PHPStan completo: **verde, no errors**.
- Pint dirty no container: **verde**.

Foi adicionada migration de `Lesson.content` e seu cast/fillable, necessários ao contrato de
consumo.

## 14. Scribe

`composer docs` terminou com exit 0. `public/docs/openapi.yaml` foi inspecionado: as nove rotas
Student aparecem nos grupos Student, com descrições de preview/access e body parameters somente no
progresso. A seção Student não documenta authoring, Assessment, certificados, paid external ou
Financial internals. Warnings do Scribe são de FormRequests existentes fora do S-01.

## 15. E2E

Runner: `artisan e2e:run learning/student-s01-own-access-consumption --base=http://localhost
--timeout=10`. Resultado final: **16 passou, 0 falhou, exit 0**.

Runtime foi executado em stack Compose separada, `APP_ENV=e2e`, base interna `http://localhost`,
banco `ead2026_e2e`, após `migrate:fresh`. O container E2E preexistente foi recusado pelo gate por
apontar para `ead2026`; não foi usado `--force-db`. Stack isolada, volume, rede, fixtures, tokens e
arquivo temporário de material foram removidos ao final.

## 16. Regression

- Learning API: **319 testes, 2.123 asserções, verde**.
- RBAC/tenant/route-security/Scribe selecionados: **19 testes, 61 asserções, verde**.
- Architecture: **36 testes, 1.325 asserções, verde**.
- `scripts/ai/verify-changes.sh`: verde — invariantes de 11 arquivos.
- `git diff --check`: verde.

## 17. Deferred

Assessment permanece `CONDITIONAL_CAPABILITY`; attempts, quiz, results e `show_results` Student não
foram criados. Certificates permanecem `NOT_PROMISED_IN_V0_1`. Checkout, payment gateway, paid
external, upload/MediaProvider, ratings, marketplace, S-02, WS2 e WS3 não foram iniciados. Rotas
Learning legacy continuam compatibilidade.

## 18. Remaining Paid Pilot MUST

S-01 fecha a superfície e consumo Student, mas não fecha o paid pilot comercial. Restam: E2E
integrado tenant → Admin publish → enrollment/cash/manual → Student; Assessment Student básico se
um Course o vender; certificado somente se uma oferta o prometer; e os requisitos operacionais do
release contract ainda não promovidos por este slice (observabilidade, health/rollback,
backup/restore, secrets e storage quando material for essencial).

## 19. Verdict

**`S01_COMPLETE`** com Feature, Architecture, Scribe, performance smoke, regressão e E2E HTTP
isolado verdes. O estado global permanece **`PAID_PILOT_NOT_READY`**.
