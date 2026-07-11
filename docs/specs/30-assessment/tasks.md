---
domain: assessment
last-updated: 2026-07-11
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
- [x] Verificação pública de certificado (`GET /certificates/verify/{code}`).

## In Progress

- _(nenhuma)_

## Pending

- [ ] `DELETE /questions/{id}`.
- [ ] `GET /questionnaires/{id}/questions` (listar questões do questionário).
- [ ] `POST /questionnaires/{id}/questions` (anexar questões).
- [ ] Geração de PDF do certificado.
- [ ] Eventos: `QuizAttemptStarted`, `QuizAttemptFinished` (+ passed/failed), `CourseCompletedEvent`, `CertificateIssuedEvent`, `CertificateRevokedEvent`.
- [ ] Revoke de certificado (`assessment.certificates.revoke`).
- [ ] Alinhar permissions de Assessment às roles (admin/instructor/student) conforme a matriz em [`../00-architecture/rbac.md`](../00-architecture/rbac.md).
- [ ] Teste E2E do fluxo do aluno (start → answer → finish → resultado).

## Needs Review

- _(nenhuma)_

## Open Questions

- _(nenhuma)_
