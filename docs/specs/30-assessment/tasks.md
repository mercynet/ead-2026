---
domain: assessment
last-updated: 2026-06-10
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

## Needs Review

- [ ] Permissions atreladas às roles corretas (tenant_admin, instructor) para todo o domínio.
- [ ] Fluxo completo do aluno (start/submit/finish) validado fim-a-fim com testes.

## Open Questions

- _(nenhuma)_
