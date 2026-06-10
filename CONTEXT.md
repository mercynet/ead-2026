# Contexto — EAD 2026

Este arquivo é apenas um **ponteiro**. A documentação canônica vive em:

- **`AGENTS.md`** — padrões de código, comandos úteis e regras de desenvolvimento. **Leia primeiro.**
- **`docs/specs/README.md`** — índice canônico das specs (arquitetura cross-cutting + domínios).
- **`docs/ROADMAP.md`** — fases/milestones. **`docs/STATE.md`** — sessão atual e próximos passos.

## Estrutura de diretórios (código)

```
app/
├── Actions/                # regra de negócio, namespaced por domínio
│   ├── Core/               # Auth, Users
│   ├── Learning/           # Catalog, Course, Enrollment, Lesson
│   └── Assessment/         # Questionnaire, Question, Attempt, Certificate
├── Http/
│   ├── Controllers/Api/V1/ # namespaced por domínio (Core, Learning, Assessment)
│   ├── Context/            # ApiContext (Value Object)
│   ├── Requests/           # FormRequests
│   └── Resources/          # API Resources
├── Models/                 # FLAT (sem subpastas): User, Tenant, Course,
│                           #   CourseModule, Lesson, Enrollment, Category,
│                           #   Questionnaire, QuizQuestion, QuizAttempt, Certificate, ...
├── Enums/                  # UserType, QuestionnaireType, ...
└── Policies/

database/migrations/        # migrations (Laravel padrão)
database/factories/         # factories (Laravel padrão)
docs/specs/                 # specs de domínio (ver docs/specs/README.md)
```

> Nota: `app/Models/` é **flat** (sem subpastas por domínio). Apenas `app/Actions/` e os
> controllers são namespaced por domínio.

Comandos úteis e regras de QA estão em `AGENTS.md` — não duplicados aqui.
