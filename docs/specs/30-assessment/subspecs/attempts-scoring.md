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
- questions_snapshot     // JSON — questões congeladas no start (com gabarito; nunca exposto na API)
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

Ao iniciar a tentativa, o sistema congela **no servidor**:

- Questionário: `title`, `description`, `passing_score`.
- Curso (se course quiz): `id`, `title`.
- Módulo (se lesson quiz): `id`, `title`.
- **Todas as questões ativas** do questionário (`questions_snapshot`): id, texto, tipo, opções,
  gabarito (`correct_options`), `points`, `sort_order`. Questionário sem questão ativa → 422.

Assim a tentativa permanece íntegra mesmo se o questionário for alterado depois.

**Integridade do scoring:** o cliente envia apenas `question_id` + `selected_options`;
`is_correct`/`points_earned` são calculados exclusivamente a partir do `questions_snapshot`
congelado. Qualquer `question_snapshot` enviado pelo cliente é ignorado. Cada questão só pode
ser respondida uma vez por tentativa (422 na repetição); questão fora do snapshot → 422.
O gabarito (`correct_options`/`explanation`) **nunca** sai nos Resources da API.

### Score

```
score = (pontos_obtidos / pontos_totais_do_questions_snapshot) * 100
passed = score >= questionnaire.passing_score
```

### Fluxo do aluno

```
1. POST /attempts/questionnaires/{id}   -> inicia (congela snapshots no servidor)
2. GET  /attempts/{id}                  -> ver tentativa atual (questões sem gabarito)
3. PATCH /attempts/{id}                 -> responde questão { question_id, selected_options }
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
