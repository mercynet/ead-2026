---
domain: assessment
maturity: stable
last-reviewed: 2026-09-06
owners: [paulo]
related:
  - ../00-architecture/rbac.md
  - ../00-architecture/api-conventions.md
  - ../00-architecture/performance-scalability.md
  - subspecs/questionnaires-questions.md
  - subspecs/attempts-scoring.md
  - subspecs/certificates.md
---

# Assessment

## Intent / Why

Mede e certifica o aprendizado. Permite ao instrutor montar questionários reutilizáveis a partir
de um banco de questões, aplicar provas (de aula, de curso ou avulsas) com integridade histórica
(snapshots), pontuar automaticamente e emitir certificados verificáveis. É o que transforma
"assistir aulas" em "aprovação comprovável".

## Overview

Padrões transversais em
[`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md). Mecânica
assíncrona de eventos em
[`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md).

Recursos detalhados nas subspecs:

- [`subspecs/questionnaires-questions.md`](subspecs/questionnaires-questions.md) — questionários, banco de questões, pivôs.
- [`subspecs/attempts-scoring.md`](subspecs/attempts-scoring.md) — tentativas, snapshots, cálculo de score.
- [`subspecs/certificates.md`](subspecs/certificates.md) — emissão e verificação de certificados.

## Entities

| Model | Tabela | Invariantes |
|-------|--------|-------------|
| `Questionnaire` | `questionnaires` | Tipo `lesson|course|standalone`; vínculo polimórfico (`quizable`). |
| `QuizQuestion` | `quiz_questions` | Banco de questões; **questão usada em tentativa não pode ser editada**. |
| `QuizAttempt` | `quiz_attempts` | Guarda snapshots (questionário/curso/módulo/questões) para integridade. |
| `QuizAttemptAnswer` | `quiz_attempt_answers` | Resposta com snapshot da questão. |
| `Certificate` | `certificates` | `certificate_number` único; status `issued|revoked`. |
| `QuestionnaireQuestion` (pivot) | `questionnaire_questions` | Ordena questões no questionário. |
| `QuizQuestionCategory` (pivot) | `quiz_question_categories` | Categoriza questões. |

## Business Rules

- **Imutabilidade de questões usadas:** questão já usada em tentativa não pode ser editada (cria
  nova) — preserva integridade de relatórios.
- **Snapshots na tentativa:** ao iniciar, congela questionário + curso/módulo + cada questão
  respondida; a tentativa não muda se o questionário for alterado depois.
- **Score:** `score = (pontos_obtidos / pontos_totais) * 100`; `passed = score >= passing_score`.
- **Certificado automático:** emitido quando progresso ≥ `certificate_min_progress` e, se
  `certificate_requires_quiz`, quiz aprovado com ≥ `certificate_min_score` (config em `courses`).
- **Verificação pública** de certificado por número, sem autenticação.

### Ownership operacional e pedagógico

- **Admin** é operador tenant-wide do Assessment básico do próprio tenant: pode listar, consultar e
  gerir o recurso sem assumir autoria pedagógica.
- **Instructor** é o owner pedagógico: `instructor_id` representa o autor/responsável e o Instructor
  só opera Assessment dentro do próprio ownership pedagógico e dos parents que lhe pertencem.
- Criação de `Questionnaire` ou `QuizQuestion` pela superfície Admin usa `instructor_id = null`.
  Admin não vira Instructor por executar a operação; `null` representa recurso operado pelo tenant
  ainda sem autor pedagógico atribuído.
- Atualização administrativa não transfere ownership. Um futuro `assign/transfer` deverá ser ação
  explícita, autorizada e auditável; não é comportamento implícito nem parte desta implementação.
- A decisão de ownership está fechada para o contexto Admin, mas sua superfície area-first e suas
  transições ainda são deltas de implementação em `tasks.md`.

## Domain Boundaries

- **Consome:** sinal de conclusão de curso (do Learning, via `LessonCompletedEvent`/recálculo de
  progresso) para engatilhar emissão de certificado.
- **Emite:** eventos de tentativa/certificado (ver `## Events`), processados assíncronamente
  (RabbitMQ → MariaDB stats — ver
  [`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md)).
- Config de certificado vive nas colunas `certificate_*` de `courses` (domínio Learning).

## Authorization

Matriz completa em [`../00-architecture/rbac.md`](../00-architecture/rbac.md) §4 (Assessment).
Permissions do domínio:

```
assessment.questionnaires.{list,create,view,update,delete}
assessment.questions.{list,create,view,update,delete}
assessment.attempts.{list,view,create,answer,finish}
assessment.certificates.{list,view,revoke}
```

## Events

- `QuizAttemptStarted`
- `QuizAttemptFinished` (com variantes `QuizAttemptPassed` / `QuizAttemptFailed`)
- `CourseCompletedEvent`
- `CertificateIssuedEvent`
- `CertificateRevokedEvent`

## Quick Reference

| Recurso | Endpoint | Permission |
|---------|----------|------------|
| Listar questionários | `GET /api/v1/assessment/questionnaires` | `assessment.questionnaires.list` |
| Criar questionário | `POST /api/v1/assessment/questionnaires` | `assessment.questionnaires.create` |
| Listar questões | `GET /api/v1/assessment/questions` | `assessment.questions.list` |
| Criar questão | `POST /api/v1/assessment/questions` | `assessment.questions.create` |
| Iniciar tentativa | `POST /api/v1/assessment/attempts/questionnaires/{id}` | `assessment.attempts.create` |
| Responder | `PATCH /api/v1/assessment/attempts/{id}` | `assessment.attempts.answer` |
| Finalizar | `POST /api/v1/assessment/attempts/{id}/finish` | `assessment.attempts.finish` |
| Listar certificados | `GET /api/v1/assessment/certificates` | `assessment.certificates.list` |
| Verificar certificado | `GET /api/v1/assessment/certificates/verify/{code}` | público |
