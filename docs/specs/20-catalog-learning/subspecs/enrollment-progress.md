---
domain: catalog-learning
parent: ../spec.md
resource: enrollment-progress
last-reviewed: 2026-06-10
---

# Enrollment & Progress

## Model / Schema

```
enrollments
- id
- tenant_id, user_id, course_id   // FK
- status                 // active | completed | expired | cancelled
- progress_percentage    // 0-100
- enrolled_at
- completed_at
- access_expires_at      // expiração de acesso
- created_at, updated_at

lesson_progress
- id
- tenant_id, user_id, lesson_id, enrollment_id  // FK
- status                 // not_started | in_progress | completed
- progress_percent       // 0-100
- time_spent_seconds
- completed_at
- created_at, updated_at
```

## Rules

- **Enrollment** é o aggregate root do cálculo de conclusão. Um aluno tem **uma matrícula ativa
  por curso**.
- Status: `active` (em andamento), `completed` (100%), `expired`, `cancelled`.
- Matrícula `expired` ainda vê a vitrine (`canViewCourse=true`) mas não consome conteúdo pago
  (`canAccessPaidContent=false`).
- **Cálculo de progresso (assíncrono):** ao marcar `is_completed=true` numa aula, dispara
  `LessonCompletedEvent`; um job recalcula o percentual com base nos módulos/aulas concluídos. Ao
  atingir 100%, `Enrollment.status = completed` e (se config) engatilha certificado.
- **Heartbeat de progresso:** o frontend envia `POST /lessons/{id}/progress` periodicamente com
  `{ duration_watched / progress_percent, is_completed / time_spent_seconds }` para atualizar o
  tracking.
- A `ProgressStrategy` (ex.: `80_percent`, `full_duration`, `manual`, `time_based`) é configurável
  por aula e define quando a meta de conclusão é batida.
- **Auditoria financeira:** toda matrícula, mesmo gratuita, gera registro espelho no Financial.

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

- Matrícula automática a partir de `OrderPaidEvent` (Financial) via `EnrollService` — código de
  matrícula não fica espalhado nas rotas financeiras.
- Matrícula manual por instrutor (switch do tenant_admin); cobrança `external` pode cair como
  `pending` para aprovação do tenant_admin.
