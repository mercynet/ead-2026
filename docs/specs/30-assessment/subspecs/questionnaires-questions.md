---
domain: assessment
parent: ../spec.md
resource: questionnaires-questions
last-reviewed: 2026-06-10
---

# Questionnaires & Questions

## Model / Schema

```
questionnaires
- id
- tenant_id, instructor_id    // FK
- title, description
- type                   // lesson | course | standalone
- quizable_id, quizable_type  // morph (Lesson / Course / none)
- passing_score          // % mínima para aprovação
- time_limit_minutes     // opcional
- show_results           // mostra resultado ao aluno
- is_active
- created_at, updated_at

quiz_questions
- id
- tenant_id, instructor_id    // FK
- question               // longtext
- type                   // single_choice | multiple_choice | true_false
- options                // JSON array
- correct_options        // JSON array de índices corretos
- explanation
- points                 // default 1
- is_active
- created_at, updated_at

questionnaire_questions (pivot)
- id, questionnaire_id, quiz_question_id
- sort_order

quiz_question_categories (pivot)
- id, quiz_question_id, category_id
```

## Rules

### Tipos de questionário

| Tipo | Descrição | Vinculação |
|------|-----------|------------|
| `lesson` | Questionário de aula | morph → Lesson |
| `course` | Prova final do curso | morph → Course |
| `standalone` | Simulado/avulso | sem vinculação |

### Tipos de questão

| Tipo | Resposta |
|------|----------|
| `single_choice` | 1 índice correto |
| `multiple_choice` | múltiplos índices |
| `true_false` | 1 índice |

Estrutura de `options`:

```json
[
  { "text": "Opção A", "correct": false },
  { "text": "Opção B", "correct": true }
]
```

### Imutabilidade

Questão usada em uma tentativa **não pode ser editada** — garante integridade de relatórios e
estatísticas. Para mudar, criar nova questão.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/assessment/questionnaires` | Listar | `assessment.questionnaires.list` |
| POST | `/api/v1/assessment/questionnaires` | Criar | `assessment.questionnaires.create` |
| GET | `/api/v1/assessment/questionnaires/{id}` | Ver | `assessment.questionnaires.view` |
| PATCH | `/api/v1/assessment/questionnaires/{id}` | Atualizar | `assessment.questionnaires.update` |
| DELETE | `/api/v1/assessment/questionnaires/{id}` | Deletar | `assessment.questionnaires.delete` |
| GET | `/api/v1/assessment/questionnaires/{id}/questions` | Listar questões do questionário | `assessment.questionnaires.view` |
| POST | `/api/v1/assessment/questionnaires/{id}/questions` | Anexar questões | `assessment.questionnaires.update` |
| GET | `/api/v1/assessment/questions` | Listar banco de questões | `assessment.questions.list` |
| POST | `/api/v1/assessment/questions` | Criar questão | `assessment.questions.create` |
| GET | `/api/v1/assessment/questions/{id}` | Ver questão | `assessment.questions.view` |
| PATCH | `/api/v1/assessment/questions/{id}` | Atualizar questão | `assessment.questions.update` |
| DELETE | `/api/v1/assessment/questions/{id}` | Deletar questão | `assessment.questions.delete` |

## Permissions

```
assessment.questionnaires.{list,create,view,update,delete}
assessment.questions.{list,create,view,update,delete}
```

## Notes

- Endpoints de anexar/listar questões no questionário e `DELETE /questions/{id}` ainda em
  revisão/pendentes — ver [`../tasks.md`](../tasks.md).
