# Domain Spec: Assessment (API-First)

## Visão Geral
Gerenciará formas de validação de conhecimento (Questionários/Quizzes) dentro de aulas e a emissão formal de certificados com base nos critérios configurados no curso.

## Status de Implementação

### ✅ Implementado
- [x] Evento LessonCompletedEvent para cálculo de progresso

### ⏳ Pendente
- [ ] Questionário CRUD
- [ ] Banco de Questões independente
- [ ] QuizAttempts com snapshot
- [ ] QuizAttemptAnswers com snapshot
- [ ] Certificate config na tabela Course
- [ ] Certificates

---

## 1. Estrutura de Questionários

### 1.1 Questionário (Quiz)
- **Tabela**: `questionnaires`
- **Tipos** (via morph):
  - `lesson` - questionário vinculado a uma aula
  - `course` - prova final do curso
  - `standalone` - simulado/questionário avulso
- **Campos**:
  - `tenant_id`
  - `title` - título (ex: "Simulado Pré-Vestibular")
  - `description` - descrição
  - `type` - lesson | course | standalone
  - `quizable_id` - id do morph (lesson_id ou course_id)
  - `quizable_type` - tipo do morph
  - `passing_score` - nota mínima para aprovação (%)
  - `time_limit_minutes` - tempo limite em minutos (opcional)
  - `is_active` - está ativo
  - `show_results` - mostra resultado ao aluno

### 1.2 Questão (QuizQuestion)
- **Tabela**: `quiz_questions`
- **Características**:
  - Banco de questões independente (não vinculada diretamente a questionário)
  - Pode ter **múltiplas categorias** (opcional)
  - Pode aparecer em múltiplos questionários
  - **一旦 usada em tentativa, não pode mais editar** (gera snapshot)
- **Campos**:
  - `tenant_id`
  - `question` - texto da questão (longtext)
  - `type` - single_choice | multiple_choice | true_false
  - `options` - JSON array de opções
  - `correct_options` - JSON array de índices corretos
  - `explanation` - explicação da resposta (para feedback)
  - `points` - pontuação da questão (default: 1)
  - `is_active`

### 1.3 Questão-Categoria (Pivot)
- **Tabela**: `quiz_question_categories`
- **Características**:
  - Relacionamento many-to-many
  - Kategorias = mesmas do sistema de cursos
  - Opcional (questão pode não ter categoria)
- **Campos**:
  - `quiz_question_id`
  - `category_id`

### 1.4 Questionário-Questão (Pivot)
- **Tabela**: `questionnaire_questions`
- **Características**:
  - Ordem das questões no questionário
  - Questões podem ser reutilizadas
- **Campos**:
  - `questionnaire_id`
  - `quiz_question_id`
  - `sort_order`

---

## 2. Tentativas e Snapshots

### 2.1 Attempt (QuizAttempt)
- **Tabela**: `quiz_attempts`
- **Características**:
  - Snapshot de toda a estrutura no momento da tentativa
  - Dados imutáveis após finalização
- **Campos**:
  - `tenant_id`
  - `user_id`
  - `questionnaire_id`
  - `status` - in_progress | completed
  - **Snapshot**:
    - `questionnaire_snapshot` - JSON com dados do questionário
    - `course_snapshot` - JSON com dados do curso (se course quiz)
    - `module_snapshot` - JSON com dados do módulo (se lesson quiz)
  - `started_at`
  - `finished_at`
  - `score` - nota final (0-100)
  - `passed` - aprovado ou não
  - `time_spent_seconds`

### 2.2 AttemptAnswer (QuizAttemptAnswer)
- **Tabela**: `quiz_attempt_answers`
- **Características**:
  - Cada resposta com snapshot da questão original
  - Dados imutáveis
- **Campos**:
  - `tenant_id`
  - `quiz_attempt_id`
  - **Snapshot**:
    - `question_snapshot` - JSON com dados da questão original
    - `selected_options` - JSON array de índices selecionados
  - `is_correct` - está correta
  - `points_earned` - pontuação obtida
  - `answered_at`

---

## 3. Certificates

### 3.1 Configuração (na tabela Courses)
O certificado é configurado na tabela `courses`:
- `certificate_enabled` (boolean) - emite certificado?
- `certificate_min_progress` (integer) - % mínima de conclusão de aulas
- `certificate_requires_quiz` (boolean) - requer quiz aprovado?
- `certificate_min_score` (integer) - % mínima no quiz

### 3.2 Certificate
- **Tabela**: `certificates`
- **Características**:
  - Gerado automaticamente ou manualmente
  - Imutável após emissão (não muda se course mudar)
- **Campos**:
  - `tenant_id`
  - `user_id`
  - `enrollment_id`
  - `course_id`
  - `certificate_number` - código único (ex: CERT-2026-XXXXX)
  - `issued_at`
  - `status` - issued | revoked

---

## 4. Endpoints Principais (JSON)

### Questionários (Assessment)
*Base URL: `api/v1/assessment`*

#### Questionários
- `GET /questionnaires` - lista questionários (filtro por type)
- `POST /questionnaires` - cria questionário
- `GET /questionnaires/{id}` - detalhe
- `PATCH /questionnaires/{id}` - atualiza
- `DELETE /questionnaires/{id}` - remove
- `POST /questionnaires/{id}/questions` - adiciona questões
- `GET /questionnaires/{id}/questions` - lista questões do questionário

#### Questões (Banco)
- `GET /questions` - lista questões (filtro por categoria)
- `POST /questions` - cria questão
- `GET /questions/{id}` - detalhe
- `PATCH /questions/{id}` - atualiza (apenas se não usada em tentativas)
- `POST /questions/{id}/categories` - associa categorias

#### Tentativas
- `POST /questionnaires/{id}/attempts` - inicia tentativa
- `GET /attempts/{id}` - detalhe da tentativa
- `GET /attempts/{id}/questions` - questões da tentativa (com snapshot)
- `POST /attempts/{id}/answers` - responde questão(ões)
- `POST /attempts/{id}/finish` - finaliza tentativa

#### Certificados
- `GET /certificates` - lista certificados do aluno
- `GET /certificates/{code}` - detalhe do certificado
- `GET /certificates/{code}/verify` - endpoint público de validação

---

## 5. Regras de Negócio

### Questões Imutáveis
- Uma questão que já foi usada em uma tentativa **não pode ser editada**
- Instrutor precisa criar nova versão se quiser mudar
- Isso garante integridade dos relatórios e estatísticas

### Snapshots
- Ao iniciar tentativa, fazer snapshot de:
  - Questionário (título, descrição, passing_score)
  - Curso (se for course quiz)
  - Módulo (se for lesson quiz)
  - Cada questão respondida (texto, opções, resposta correta)

### Reassistir Aulas
- Aula concluída pode ser reassistida
- Não muda status de "concluída"
- Cada visualização gera registro em `lesson_views`

### Categorias de Questões
- Mesmo tabela de categorias dos cursos
- Relacionamento many-to-many opcional
- Apenas para facilitar busca ao montar questionário

### Certificate
- Por curso, não por aula
- Critérios configuráveis no curso
- Plugin de certificados avançados pode ter mais opções
- Dados do certificado são snapshots (não mudam se curso mudar)

---

## 6. Eventos para Estatísticas

### Eventos Necessários
- `QuizAttemptFinished` - quando aluno termina tentativa
- `CourseCompletedEvent` - quando curso 100%
- `CertificateIssuedEvent` - quando certificado emitido

### Processamento
- Todos eventos vão para fila (RabbitMQ)
- Processed by consumer → MariaDB de stats
- Dados históricos nunca se perdem

---

## 7. Permissions

### Roles
- `developer`: acesso total
- `tenant_admin`: CRUD em todos os questionários do tenant
- `instructor`: CRUD em seus próprios questionários
- `student`: fazer tentativas, ver seus resultados

### Instructor Scope
- Instrutor pode ver todos os cursos do tenant OU só os seus
- Configurável via `tenant_settings`
