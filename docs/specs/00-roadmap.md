# EAD 2026 - Roadmap de Desenvolvimento

## Visão Geral

Plataforma EAD multi-tenant API-first, reconstrução do sistema eadIA com arquitetura RESTful pura.

## Stack Tecnológica

- PHP 8.4 + Laravel 12
- Laravel Sanctum (autenticação)
- Spatie Permission (RBAC)
- MySQL 8.0 / MariaDB / Redis / RabbitMQ
- Pest 4 (testes)

---

## Arquivos de Documentação

| Arquivo | Descrição |
|---------|-----------|
| `01-rbac.md` | Roles, Permissions, UserTypes, Plugins |
| `02-core.md` | Users, Auth, Tenants |
| `03-learning.md` | Courses, Modules, Lessons, Enrollments |
| `04-assessment.md` | Questionnaires, Questions, Certificates |

---

## Ordem de Desenvolvimento

### Fase 1: Permissões e Roles (PRIORIDADE)

> Antes de qualquer coisa, o sistema de permissions precisa estar completo.

1. Adicionar coluna `user_type` na tabela users (enum)
2. Adicionar colunas `tenant_id` e `scope` na tabela roles
3. Atualizar RolesSeeder com UserTypes corretos
4. Implementar gate/policy que verifica UserType
5. Implementar "teto" de permissions baseado em UserType

### Fase 2: Base Administrativa Learning

1. CRUD Categories (update, delete)
2. CRUD Courses (update, delete)
3. CRUD Modules (create, update, delete)
4. CRUD Lessons (create, update, delete)
5. CRUD Enrollments (create, update, delete)
6. Module/Lesson reorder

### Fase 3: Fluxos do Aluno

1. Enrollment flow
2. Lesson access + progress
3. LessonViews (estatísticas)

### Fase 4: Assessment Ajustes

1. Ajustar permissions assessment
2. Attach questions to questionnaire
3. Student: attempt flow

### Fase 5: Eventos + Extras

1. Disparar eventos para stats
2. Certificate PDF
3. Pre-signed URLs

---

## Status de Implementação

### ✅ Feito

#### Infraestrutura
- [x] Auth (login/logout/me)
- [x] ApiContext Pattern
- [x] Middleware de Tenant
- [x] Policies base
- [x] UserType enum (developer|admin|instructor|student)
- [x] Coluna `user_type` na tabela users
- [x] Métodos isDeveloper(), isAdmin(), isInstructor(), isStudent() no User model
- [x] Gate/policy para verificar UserType
- [x] Permissions base para tenant admin

#### Core
- [x] User model + factory
- [x] POST /users (criar)
- [x] GET /users (listar)
- [x] GET /users/{id}
- [x] PATCH /users/me

#### Learning
- [x] Category model + factory + API
- [x] Category policy com isSystem check
- [x] Course model + factory + API
- [x] CourseModule model + factory
- [x] Lesson model + factory
- [x] Enrollment model + factory
- [x] LessonProgress model + factory
- [x] GET /courses (listar)
- [x] GET /courses/{id}
- [x] GET /courses/{id}/modules
- [x] GET /courses/{id}/enrollment
- [x] GET /lessons/{id}
- [x] POST /lessons/{id}/progress

#### Assessment
- [x] Questionnaire model + factory + API
- [x] QuizQuestion model + factory + API
- [x] QuestionnaireQuestion (pivot)
- [x] QuizQuestionCategory (pivot)
- [x] QuizAttempt model + factory + API
- [x] QuizAttemptAnswer model + factory
- [x] QuizAttemptPolicy (criada)
- [x] Certificate model + factory + API
- [x] Score calculation
- [x] Certificate verification público
- [x] Certificate config em Course

---

### ⏳ Pendente

#### Permissões e Roles
- [ ] Adicionar colunas `tenant_id` e `scope` na tabela roles
- [ ] Implementar "teto" de permissions baseado em UserType
- [ ] Remover código Spatie não utilizado

#### Core
- [ ] PATCH /users/{id} (update)
- [ ] DELETE /users/{id}
- [ ] PATCH /users/me/password

#### Learning
- [ ] CRUD Categories (update, delete)
- [ ] CRUD Courses (update, delete)
- [ ] CRUD Modules (create, update, delete)
- [ ] CRUD Lessons (create, update, delete)
- [ ] CRUD Enrollments (create, update, delete)
- [ ] Module reorder
- [ ] Attach categories to courses
- [ ] Course publish/unpublish
- [ ] Lesson reorder

#### Assessment
- [ ] CRUD Questionnaires (ajustar permissions)
- [ ] CRUD Questions (ajustar permissions)
- [ ] Attach questions to questionnaire
- [ ] List questions in questionnaire
- [ ] CRUD Certificates (ajustar permissions)

#### Fluxos
- [ ] Student: enrollment (matricular-se)
- [ ] Student: access lessons
- [ ] LessonViews (estatísticas de replay)
- [ ] Student: start attempt
- [ ] Student: submit answers
- [ ] Student: finish attempt

#### Extras
- [ ] Certificate PDF Generation
- [ ] Eventos: QuizAttemptFinished, CourseCompleted, CertificateIssued

---

## Fonte de Referência

Projeto `eadIA` em `/home/paulo/www/eadIA`:
- 49 models
- 97 migrations
- 5 painéis Filament
- 140+ testes

Usar como referência para regras de negócio e estrutura de dados.
