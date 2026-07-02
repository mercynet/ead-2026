---
domain: catalog-learning
parent: ../spec.md
resource: enrollment-progress
last-reviewed: 2026-07-01
---

# Enrollment & Progress

## Model / Schema

```
enrollments
- id
- tenant_id, user_id, course_id   // FK
- status                 // pending | active | expired | cancelled
- enrolled_at
- access_expires_at      // expiração de acesso
- created_at, updated_at

lesson_progress
- id
- tenant_id, user_id, lesson_id, enrollment_id  // FK
- status                 // not_started | in_progress | completed
- progress_percentage    // 0-100
- time_spent_seconds
- completed_at
- created_at, updated_at
```

## Rules

- **Enrollment** representa o vínculo de acesso/participação no curso. Um aluno tem **no máximo
  uma matrícula `active` por curso**; matrículas históricas inativas podem coexistir.
- Status da matrícula: `pending`, `active`, `expired`, `cancelled`.
- Conclusão do curso **não** é status da matrícula; pertence ao domínio de progresso.
- Matrícula `expired` ainda vê a vitrine (`canViewCourse=true`) mas não consome conteúdo pago
  (`canAccessPaidContent=false`).
- Rematrícula é permitida quando a matrícula anterior estiver `cancelled` ou `expired`; não faz
  sentido enquanto houver matrícula `pending` ou `active`.
- **Cálculo de progresso (assíncrono):** ao marcar `is_completed=true` numa aula, dispara
  `LessonCompletedEvent`; um job recalcula o percentual com base nos módulos/aulas concluídos. Ao
  atingir 100%, o progresso do curso é marcado como concluído e (se config) engatilha certificado.
- **Heartbeat de progresso:** o frontend envia `POST /lessons/{id}/progress` periodicamente com
  `{ duration_watched / progress_percentage, is_completed / time_spent_seconds }` para atualizar o
  tracking.
- A `ProgressStrategy` (ex.: `80_percent`, `full_duration`, `manual`, `time_based`) é configurável
  por aula e define quando a meta de conclusão é batida.
- **Auditoria financeira:** toda matrícula, mesmo gratuita, idealmente gera registro espelho no Financial.

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| GET | `/api/v1/learning/enrollments` | Listar matrículas | `learning.enrollments.list` |
| POST | `/api/v1/learning/enrollments` | Criar matrícula | `learning.enrollments.create` |
| GET | `/api/v1/learning/enrollments/{id}` | Ver matrícula | `learning.enrollments.view` |
| PATCH | `/api/v1/learning/enrollments/{id}` | Atualizar matrícula | `learning.enrollments.update` |
| DELETE | `/api/v1/learning/enrollments/{id}` | Cancelar matrícula | `learning.enrollments.delete` |
| GET | `/api/v1/learning/courses/{id}/enrollment` | Minha matrícula no curso (situação + progresso) | auth (own) |
| POST | `/api/v1/learning/lessons/{id}/progress` | Heartbeat de progresso | auth (own) |

## Permissions

```
learning.enrollments.{list,create,view,update,delete}
learning.progress.view   // instrutor/admin: ver progresso dos alunos
```

## Notes

- Matrícula automática a partir de `OrderPaidEvent` (Financial) via `EnrollService` é alvo
  planejado — código de matrícula não deve ficar espalhado nas rotas financeiras.
- Matrícula manual por instrutor (switch do tenant_admin); cobrança `external` pode cair como
  `pending` para aprovação do tenant_admin.
