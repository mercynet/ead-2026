# I-02 — Instructor Attachments, Roster, Progress e Free Enrollment

Data: 2026-09-08
Branch/HEAD: `main` / `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`
Proveniência: I-01 já estava no working tree sem commit; I-02 foi implementado sobre esse estado. Nada foi staged, commitado ou pushed.

## 1. Baseline

Estado autorizado: MZRT complete, Admin complete no escopo autorizado, Instructor `INSTRUCTOR_PARTIAL`, I-01 `I01_COMPLETE` e decisões I-02 fechadas com itens deferred. O slice permaneceu em Learning, usando a superfície canônica `/api/v1/instructor` e sem iniciar I-03/I-04, Student, Assessment, WS2 ou WS3.

## 2. RED Evidence

Antes da implementação, os dois testes prioritários foram registrados como `RED_CONFIRMED` em `tests/Feature/Api/Learning/InstructorI02ApiTest.php`:

- `RED_CONFIRMED: exposes the instructor-owned roster endpoint`: falha real `404`, envelope `not_found`.
- `RED_CONFIRMED: exposes the instructor-owned free enrollment endpoint`: falha real `404`, envelope `not_found`.

Após a implementação: Feature I-02 `9 passed (164 assertions)`.

## 3. LessonMedia

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: list, show, create, update e delete em `/api/v1/instructor/lessons/{lessonId}/media`, com ownership transitivo Lesson → Module → Course → `instructor_id`. A resposta é `Instructor/LessonMediaResource`, sem `metadata` ou storage path. O teste cobre múltiplas mídias, owner A/B, Course sem owner, parent spoof e cross-tenant; o E2E confirmou create próprio e 404 defensivo para owner/tenant foreign. Upload, MediaProvider, proxy, DRM/CDN/live não foram implementados.

## 4. CourseMaterial

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: list, show, create, update, delete e download temporário em `/api/v1/instructor/courses/{courseId}/materials`. Ownership é Course → `instructor_id`; `CourseMaterial` permanece distinto de `LessonMedia`. `Instructor/CourseMaterialResource` não expõe `file_path`; o download reutiliza URL temporária segura e não expõe path bruto. Testes cobrem owner A/B, cross-tenant e parent spoof.

## 5. Roster

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: `GET /api/v1/instructor/enrollments` e show individual. A query é tenant-scoped e reduzida por Courses do actor; `course_id` é apenas filtro. O default é `status=active`; os status explícitos são `pending|active|expired|cancelled`, com cursor pagination. Não há filtro livre por `user_id` na superfície Instructor.

## 6. PII Projection

`TEST_VERIFIED` e `STATIC_EVIDENCE_ONLY`: `Instructor/EnrollmentResource` projeta somente `user.id`, `user.name`, `user.avatar`, além de id/course/status/timestamps e progresso permitido. Não há email, telefone, CPF/NIF, endereço, nascimento, tenant_id, dados financeiros, metadata interna ou UserResource completo. Architecture prova a ausência dos campos proibidos.

## 7. Progress

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: progresso de enrollment em `/api/v1/instructor/enrollments/{id}/progress`, limitado a Course próprio. O agregado contém percentual, aulas concluídas, total elegível e última atividade. O detalhe contém apenas `lesson_id`, `progress_percentage`, `is_completed`, `completed_at`, `last_watched_at` e `time_spent_seconds`. O denominador conta somente Lessons `published + active`; foreign owner/cross-tenant retornam 404 defensivo.

## 8. Free Enrollment

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: `POST /api/v1/instructor/enrollments` é uma operação dedicada. Deriva tenant, status, billing e actor no servidor; exige Course próprio, tenant atual, published, active, free e Student válido do mesmo tenant. Respeita `manual_free_by_instructor`; respeita `manual_free_requires_approval`, produzindo `active` ou `pending`. Payloads de tenant/status/billing/ownership/valores financeiros são proibidos.

## 9. Financial Mirror

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: o fluxo reutiliza `EnrollmentCreatedEvent` e o listener/bridge existente. O teste confirmou exatamente um `Order` `paid`/`direct` com `total_cents=0`, um `OrderItem` do Course e um `Payment` `free` com charge state `resolved`. Learning não importa Models Financial nem chama gateway, charge, webhook, confirmação de pagamento ou ledger.

## 10. Paid External Rejection

`TEST_VERIFIED` e `RUNTIME_VERIFIED`: Course pago retorna `422 validation_error`; `billing_type=external` é rejeitado por `422 validation_error`. Feature e E2E confirmaram ausência de Enrollment, Order, Payment, outbox, gateway e evento financeiro adicional. A compatibilidade legacy não foi expandida.

## 11. Security

`TEST_VERIFIED` e `STATIC_EVIDENCE_ONLY`: cobertura inclui 401, RBAC/area guard, tenant isolation, owner A/B, Course foreign, Enrollment/progress foreign, LessonMedia/CourseMaterial foreign, null-owner Course, tenant/owner/parent spoof, PII leakage, lifecycle mutating ausente e ausência de Financial/Assessment controller leakage. Enrollment lifecycle permanece Admin-only; Instructor não cancela, remove, suspende, reativa, altera status/billing ou confirma pagamento.

## 12. Feature / Architecture

Feature I-02: `9 passed (164 assertions)`.
Regressão API Learning: `310 passed (1821 assertions)`.
Regressão Financial/RBAC/ownership: `35 passed (185 assertions)`.
Architecture completa: `29 passed (986 assertions)`.
Provas específicas Instructor: `7 passed (277 assertions)`.
PHPStan: `469/469`, zero erros. Pint no container: pass.

## 13. Scribe

`composer docs` terminou com exit 0. Foram emitidos os grupos Instructor para roster, progress, free enrollment, LessonMedia e CourseMaterial, incluindo download de material. Não foi documentada paid external como capability suportada. Permanecem apenas warnings de extração básica de `bodyParameters()` já compatíveis com o padrão existente; a geração HTML/OpenAPI/Postman concluiu.

## 14. E2E

Runner: `php artisan e2e:run instructor/i02-roster-progress-attachments --base=http://localhost:8084`.
Base URL: `http://localhost:8084` (servidor HTTP efêmero no container E2E).
Banco: `ead2026_e2e`, separado do banco local/teste.
Resultado/exit: `14 passou, 0 falhou`, exit 0.

A jornada confirmou Instructor A/B no mesmo tenant, roster e progresso próprios, matrícula FREE, replay sem duplicação, espelho zero-consideration, paid/external sem efeitos, mídia/material próprios, foreign owner/cross-tenant e 401. O teardown padrão do runner removeu as fixtures efêmeras; nenhum resíduo E2E permaneceu.

## 15. Regression

Além das suítes acima: `scripts/ai/verify-changes.sh` terminou verde para 8 arquivos de Architecture; `git diff --check` terminou verde. A primeira tentativa concorrente de regressão foi descartada como evidência por disputa conhecida do banco compartilhado `testing`; o banco foi recriado e todas as regressões foram repetidas serialmente com resultado verde.

## 16. Deferred

Permanecem fora deste slice: upload real, MediaProvider, media proxy/binário, DRM/CDN/live, integração de CourseMaterial com upload/media library, paid external e sua reconciliação/aprovação, lifecycle mutante de Enrollment no Instructor, publish/unpublish/assignment/reassignment, Assessment, analytics avançado e ranking/cohort.

## 17. Remaining Instructor MUST

O restante Instructor MUST autorizado não é iniciado neste slice: I-03/I-04, superfície Student, publish/unpublish Instructor, assignment/reassignment, lifecycle completo de enrollment e demais deltas explicitamente deferred. O próximo trabalho depende de autorização própria.

## 18. Verdict

`I02_COMPLETE`

Todos os itens MUST do I-02 têm evidência atual Feature, Architecture, Scribe e E2E/runtime, incluindo ownership A/B, tenant isolation, null-owner, PII allowlist, idempotência, mirror zero-consideration, rejeição paid/external e cleanup. O status é do working tree; não implica commit ou push.
