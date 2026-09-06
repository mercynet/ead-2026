---
domain: assessment
last-updated: 2026-09-06
---

# Tasks — Assessment

Cada task = 1 slice fino (≤ 1 endpoint ou 1 migration+model). Critério de aceite = teste.

## Done

- [x] Models `Questionnaire`, `QuizQuestion`, `QuizAttempt`, `QuizAttemptAnswer`, `Certificate` (+ factories).
- [x] Pivôs `QuestionnaireQuestion`, `QuizQuestionCategory`.
- [x] `QuizAttemptPolicy`.
- [x] Questionnaire CRUD: `GET/POST/GET{id}/PATCH/DELETE /questionnaires`.
- [x] Questions: `GET/POST/GET{id}/PATCH /questions`.
- [x] Attempts: `POST /attempts/questionnaires/{id}`, `GET /attempts/{id}`, `PATCH /attempts/{id}`, `POST /attempts/{id}/finish`.
- [x] QuizAttempts/Answers com snapshot.
- [x] Cálculo de score.
- [x] **P0.1 (auditoria 2026-07-11):** snapshot de questões congelado no servidor
  (`questions_snapshot`); `PATCH /attempts/{id}` aceita só `question_id` + `selected_options`;
  score do snapshot do servidor (mata nota forjável e bug `maxPoints=0`); gabarito não sai
  mais nos Resources; fix `QuizAttemptPolicy::create` (checava `attempts.view`).
- [x] Config de certificado nas colunas de `courses`.
- [x] Certificates: `GET /certificates`, `GET /certificates/{id}`.
- [x] **P1.3 parcial (auditoria 2026-07-11):** coluna `course_id` em `certificates` (backfill via
  enrollment); relação `Certificate::course()` funcional; verify público devolve `course_title` real.
- [x] Verificação pública de certificado (`GET /certificates/verify/{code}`).
- [x] Emissão automática de certificado: `CourseCompletedEvent` (Learning, disparado na transição
  do enrollment para 100% em `UpdateProgressAction`) + `IssueCertificateOnCourseCompletedListener`
  → `IssueCertificateAction` honrando `certificate_enabled` / `certificate_min_progress` /
  `certificate_requires_quiz` / `certificate_min_score`, idempotente por enrollment
  (testes em `CertificateIssuanceTest` + `CourseCompletedEventTest`).
- [x] **Assessment Admin básico (2026-09-06):** superfícies area-first em
  `/api/v1/admin/questionnaires` e `/api/v1/admin/questions`, com criação Admin sem
  `instructor_id`, edição preservando ownership pedagógico, tenant isolation e Contract de
  Learning para parents/categories.

## In Progress

- _(nenhuma)_

## Pending

- [ ] **Assign/transfer pedagógico:** se necessário ao produto, implementar operação explícita,
  autorizada e auditável para atribuir/transferir `instructor_id`; não fazer como efeito colateral do
  CRUD administrativo.
- [ ] `DELETE /questions/{id}`.
- [ ] `GET /questionnaires/{id}/questions` (listar questões do questionário).
- [ ] `POST /questionnaires/{id}/questions` (anexar questões).
- [ ] Geração de PDF do certificado.
- [ ] Eventos: `QuizAttemptStarted`, `QuizAttemptFinished` (+ passed/failed), `CertificateIssuedEvent`, `CertificateRevokedEvent` (`CourseCompletedEvent` já existe no Learning).
- [ ] Trigger complementar de emissão: quiz aprovado **depois** do curso completo
  (`certificate_requires_quiz` + aluno fecha quiz por último — hoje só o `CourseCompletedEvent` engatilha).
- [ ] Revoke de certificado (`assessment.certificates.revoke`).
- [ ] Alinhar permissions de Assessment às roles (admin/instructor/student) conforme a matriz em [`../00-architecture/rbac.md`](../00-architecture/rbac.md).
- [ ] Teste E2E do fluxo do aluno (start → answer → finish → resultado).

> A decisão Admin tenant-wide / Instructor owner pedagógico está fechada documentalmente. O slice
> básico Admin foi entregue; as linhas restantes são deltas independentes.

## Needs Review

- _(nenhuma)_

## Open Questions

- _(nenhuma)_
