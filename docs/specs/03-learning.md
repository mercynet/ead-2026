# Learning - Cursos, Módulos, Aulas e Matrículas

> **Este documento contém todas as regras de negócio do domínio Learning.**
> Para LLM: Leia este arquivo antes de implementar anything em Learning.

---

## 1. Visão Geral

O módulo Learning gerencia:
- **Categories**: Categorias de cursos
- **Courses**: Cursos
- **CourseModules**: Módulos dentro de cursos
- **Lessons**: Aulas dentro de módulos
- **Enrollments**: Matrículas de alunos
- **LessonProgress**: Progresso do aluno nas aulas

---

## 2. Categories

### Modelo

```
categories
- id
- tenant_id          // FK
- parent_id          // FK (auto-referência para subcategorias)
- name
- slug
- description
- is_system          // boolean (global vs custom)
- is_active
- sort_order
- created_at
- updated_at
```

### Tipos de Categoria

| Tipo | Escopo | Quem pode criar/editar |
|------|--------|----------------------|
| System | Global (todos os tenants) | Apenas developer |
| Custom | Do tenant | Tenant Admin, Instructor |

### Regras

- **System**: nome nunca pode ser duplicado por tenant
- **Custom**: pode ser duplicada entre tenants diferentes
- Suporta hierarquia (parent_id) até 3 níveis

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/learning/catalog/categories` | Listar categorias |
| POST | `/api/v1/learning/catalog/categories` | Criar categoria |
| GET | `/api/v1/learning/catalog/categories/{id}` | Ver categoria |
| PATCH | `/api/v1/learning/catalog/categories/{id}` | Atualizar categoria |
| DELETE | `/api/v1/learning/catalog/categories/{id}` | Deletar categoria |

### Permissions

```
learning.categories.list
learning.categories.create
learning.categories.view
learning.categories.update
learning.categories.delete
learning.categories.system.manage  # só developer
```

---

## 3. Courses

### Modelo

```
courses
- id
- tenant_id              // FK
- instructor_id          // FK (criador)
- title
- slug
- description
- thumbnail
- is_published
- is_active
- enrollment_type        // open | invite_only | sales
- certificate_enabled    // boolean
- certificate_min_progress
- certificate_requires_quiz
- certificate_min_score
- created_at
- updated_at
```

### Relacionamentos

```
Course belongsTo Tenant
Course belongsTo User (instructor)
Course hasMany CourseModules
Course hasMany Enrollments
Course belongsToMany Categories
```

### Fluxo de Publicação

```
1. Instructor cria curso (draft)
2. Instructor adiciona módulos e aulas
3. Instructor configura certificado (opcional)
4. Instructor publica curso → is_published = true
5. Alunos podem ver e matricular
```

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/learning/catalog/courses` | Listar cursos (catálogo) |
| GET | `/api/v1/learning/catalog/courses/{slug}` | Ver curso |
| POST | `/api/v1/learning/courses` | Criar curso |
| GET | `/api/v1/learning/courses/{id}` | Ver curso (admin) |
| PATCH | `/api/v1/learning/courses/{id}` | Atualizar curso |
| DELETE | `/api/v1/learning/courses/{id}` | Deletar curso |
| GET | `/api/v1/learning/courses/{id}/modules` | Listar módulos |
| POST | `/api/v1/learning/courses/{id}/publish` | Publicar curso |
| POST | `/api/v1/learning/courses/{id}/unpublish` | Despublicar curso |

### Permissions

```
learning.courses.list
learning.courses.create
learning.courses.view
learning.courses.update
learning.courses.delete
learning.courses.publish
```

---

## 4. CourseModules

### Modelo

```
course_modules
- id
- tenant_id              // FK
- course_id              // FK
- title
- description
- sort_order
- is_active
- created_at
- updated_at
```

### Regras

- Pertence a um único curso
- Pode ser reordenado (sort_order)
- Aulas são organizadas dentro de módulos

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/learning/modules` | Listar módulos |
| POST | `/api/v1/learning/modules` | Criar módulo |
| GET | `/api/v1/learning/modules/{id}` | Ver módulo |
| PATCH | `/api/v1/learning/modules/{id}` | Atualizar módulo |
| DELETE | `/api/v1/learning/modules/{id}` | Deletar módulo |
| PATCH | `/api/v1/learning/modules/reorder` | Reordenar módulos |

### Permissions

```
learning.modules.list
learning.modules.create
learning.modules.view
learning.modules.update
learning.modules.delete
learning.modules.reorder
```

---

## 5. Lessons

### Modelo

```
lessons
- id
- tenant_id              // FK
- course_module_id       // FK
- title
- slug
- description
- content_type           // video | text | quiz | assignment
- content                // JSON (conteúdo)
- duration_minutes       // duração estimada
- sort_order
- is_free                // boolean (aula gratuita para amostragem)
- is_active
- created_at
- updated_at
```

### Tipos de Aula

| Tipo | Descrição |
|------|-----------|
| video | Vídeo (url ou embedded) |
| text | Texto/Markdown |
| quiz | Questionário (Assessment) |
| assignment | Tarefa/Atividade |

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/learning/lessons` | Listar aulas |
| POST | `/api/v1/learning/lessons` | Criar aula |
| GET | `/api/v1/learning/lessons/{id}` | Ver aula |
| PATCH | `/api/v1/learning/lessons/{id}` | Atualizar aula |
| DELETE | `/api/v1/learning/lessons/{id}` | Deletar aula |
| POST | `/api/v1/learning/lessons/{id}/progress` | Registrar progresso |

### Permissions

```
learning.lessons.list
learning.lessons.create
learning.lessons.view
learning.lessons.update
learning.lessons.delete
```

---

## 6. Enrollments

### Modelo

```
enrollments
- id
- tenant_id              // FK
- user_id                // FK (aluno)
- course_id              // FK
- status                 // enum: active | completed | expired | cancelled
- enrolled_at
- completed_at
- expires_at
- created_at
- updated_at
```

### Status

| Status | Descrição |
|--------|-----------|
| active | Aluno matriculado e em andamento |
| completed | Aluno completou 100% do curso |
| expired | Matrícula expirou |
| cancelled | Matrícula cancelada |

### Regras

- Um aluno pode ter apenas uma matrícula ativa por curso
- Progresso é calculado baseado em aulas concluídas
- Certificado é emitido quando:
  - Progresso >= certificate_min_progress
  - Se certificate_requires_quiz: quiz aprovado com >= certificate_min_score

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/learning/enrollments` | Listar matrículas |
| POST | `/api/v1/learning/enrollments` | Criar matrícula |
| GET | `/api/v1/learning/enrollments/{id}` | Ver matrícula |
| PATCH | `/api/v1/learning/enrollments/{id}` | Atualizar matrícula |
| DELETE | `/api/v1/learning/enrollments/{id}` | Cancelar matrícula |
| GET | `/api/v1/learning/courses/{id}/enrollment` | Ver minha matrícula no curso |

### Permissions

```
learning.enrollments.list
learning.enrollments.create
learning.enrollments.view
learning.enrollments.update
learning.enrollments.delete
```

---

## 7. LessonProgress

### Modelo

```
lesson_progress
- id
- tenant_id              // FK
- user_id                // FK (aluno)
- lesson_id              // FK
- enrollment_id          // FK
- status                 // enum: not_started | in_progress | completed
- progress_percent       // 0-100
- completed_at
- time_spent_seconds     // tempo assistindo
- created_at
- updated_at
```

### Fluxo de Progresso

```
1. Aluno acessa aula → registra LessonProgress (in_progress)
2. Aluno termina aula → status = completed
3. Sistema calcula progresso total do curso
4. Se progresso = 100% → Enrollment.status = completed
5. Se certificado enabled + requisitos → emite certificado
```

### Heartbeat

```
POST /api/v1/learning/lessons/{id}/progress
Body: { progress_percent, time_spent_seconds }
```

Chamado periodicamente pelo frontend para registrar progresso.

### Permissions

```
learning.progress.view  # ver progresso dos alunos (instructor/admin)
```

---

## 8. Fluxo do Aluno

```
1. Browse catálogo → Ver cursos
   GET /api/v1/learning/catalog/courses

2. Ver detalhes do curso
   GET /api/v1/learning/catalog/courses/{slug}

3. Matricular-se
   POST /api/v1/learning/enrollments
   Body: { course_id }

4. Ver módulos e aulas
   GET /api/v1/learning/courses/{id}/modules

5. Acessar aula
   GET /api/v1/learning/lessons/{id}

6. Registrar progresso (heartbeat)
   POST /api/v1/learning/lessons/{id}/progress
   Body: { progress_percent, time_spent_seconds }

7. Ver própria matrícula
   GET /api/v1/learning/courses/{id}/enrollment
```

---

## 9. Fluxo do Instrutor

```
1. Criar categoria
   POST /api/v1/learning/catalog/categories

2. Criar curso
   POST /api/v1/learning/courses

3. Adicionar módulos
   POST /api/v1/learning/modules

4. Adicionar aulas
   POST /api/v1/learning/lessons

5. Configurar certificado no curso
   PATCH /api/v1/learning/courses/{id}
   Body: { certificate_enabled: true, certificate_min_score: 70 }

6. Publicar curso
   POST /api/v1/learning/courses/{id}/publish

7. Acompanhar progresso dos alunos
   GET /api/v1/learning/enrollments
   (filtered by course)
```

---

## 10. Status de Implementação

### ✅ Feito

- [x] Category model + factory + API
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

### ⏳ Pendente

- [ ] CRUD Categories (update, delete)
- [ ] CRUD Courses (update, delete)
- [ ] CRUD Modules (create, update, delete)
- [ ] CRUD Lessons (create, update, delete)
- [ ] CRUD Enrollments (create, update, delete)
- [ ] Module reorder
- [ ] Course publish/unpublish
- [ ] Attach categories to courses
- [ ] Lesson reorder
- [ ] LessonViews (estatísticas de replay)

---

## 11. Permissions por UserType

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:----------:|:-------:|
| learning.categories.* (system) | ✅ | ❌ | ❌ | ❌ |
| learning.categories.* (tenant) | ✅ | ✅ | ✅ | list |
| learning.courses.* | ✅ | ✅ | own | ❌ |
| learning.modules.* | ✅ | ✅ | own | ❌ |
| learning.lessons.* | ✅ | ✅ | own | ❌ |
| learning.enrollments.* | ✅ | ✅ | view | ❌ |
| learning.progress.view | ✅ | ✅ | ✅ | ❌ |

---

## 12. Referência Rápida

| Recurso | Endpoint | Permissão |
|---------|----------|----------|
| Listar cursos | GET /catalog/courses | - |
| Ver curso | GET /catalog/courses/{slug} | - |
| Criar curso | POST /courses | learning.courses.create |
| Listar módulos | GET /courses/{id}/modules | learning.courses.view |
| Criar módulo | POST /modules | learning.modules.create |
| Criar aula | POST /lessons | learning.lessons.create |
| Acessar aula | GET /lessons/{id} | learning.lessons.view |
| Progresso | POST /lessons/{id}/progress | auth |
| Ver matrícula | GET /courses/{id}/enrollment | auth (own) |
| Criar matrícula | POST /enrollments | learning.enrollments.create |
