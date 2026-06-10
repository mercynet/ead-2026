---
domain: assessment
parent: ../spec.md
resource: attempts-scoring
last-reviewed: 2026-06-10
---

# Attempts & Scoring

## Model / Schema

```
quiz_attempts
- id
- tenant_id, user_id, questionnaire_id   // FK
- status                 // in_progress | completed
- questionnaire_snapshot // JSON
- course_snapshot        // JSON (se course quiz)
- module_snapshot        // JSON (se lesson quiz)
- started_at, finished_at
- score                  // 0-100
- passed                 // boolean
- time_spent_seconds
- created_at, updated_at

quiz_attempt_answers
- id, quiz_attempt_id
- question_snapshot      // JSON (texto, opções, resposta correta)
- selected_options       // JSON
```

## Rules

### Snapshots (integridade)

Ao iniciar a tentativa, o sistema congela:

- Questionário: `title`, `description`, `passing_score`.
- Curso (se course quiz): `id`, `title`.
- Módulo (se lesson quiz): `id`, `title`.
- Cada questão respondida: texto, opções, resposta correta.

Assim a tentativa permanece íntegra mesmo se o questionário for alterado depois.

### Score

```
score = (pontos_obtidos / pontos_totais) * 100
passed = score >= questionnaire.passing_score
```

### Fluxo do aluno

```
1. POST /attempts/questionnaires/{id}   -> inicia (snapshot)
2. GET  /attempts/{id}                  -> ver tentativa atual
3. PATCH /attempts/{id}                 -> responde questão { question_snapshot, selected_options }
4. POST /attempts/{id}/finish           -> finaliza (calcula score, passed)
5. GET  /attempts/{id}                  -> resultado
```

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/assessment/attempts/questionnaires/{id}` | Iniciar tentativa | `assessment.attempts.create` |
| GET | `/api/v1/assessment/attempts/{id}` | Ver tentativa/resultado | `assessment.attempts.view` |
| PATCH | `/api/v1/assessment/attempts/{id}` | Responder questão | `assessment.attempts.answer` |
| POST | `/api/v1/assessment/attempts/{id}/finish` | Finalizar tentativa | `assessment.attempts.finish` |

## Permissions

```
assessment.attempts.{list,view,create,answer,finish}
```

Matriz por UserType em [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md): student
acessa apenas as próprias tentativas (`own`); instructor/admin têm `view`.

## Notes

- Eventos `QuizAttemptStarted` / `QuizAttemptFinished` (+ passed/failed) emitidos para stats —
  ver `../spec.md` `## Events` e
  [`../../00-architecture/performance-scalability.md`](../../00-architecture/performance-scalability.md).
- Fluxo completo do aluno (start/submit/finish) ainda pendente de finalização — ver `../tasks.md`.
