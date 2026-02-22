# Assessment - Questionários, Questões e Certificados

> **Este documento contém todas as regras de negócio do domínio Assessment.**
> Para LLM: Leia este arquivo antes de implementar anything em Assessment.

---

## 1. Visão Geral

O módulo Assessment gerencia:
- **Questionnaires**: Questionários/Quizzes
- **QuizQuestions**: Banco de questões
- **QuizAttempts**: Tentativas de alunos
- **Certificates**: Certificados emitidos

---

## 2. Questionnaires

### Modelo

```
questionnaires
- id
- tenant_id              // FK
- instructor_id          // FK (criador)
- title
- description
- type                   // enum: lesson | course | standalone
- quizable_id            // FK (morph)
- quizable_type          // morph type
- passing_score          // % mínima para aprovação
- time_limit_minutes     // tempo limite (opcional)
- is_active
- show_results           // mostra resultado ao aluno
- created_at
- updated_at
```

### Tipos de Questionário

| Tipo | Descrição | Vinculação |
|------|-----------|-----------|
| `lesson` | Questionário de aula | Morph → Lesson |
| `course` | Prova final do curso | Morph → Course |
| `standalone` | Simulado/avulso | Sem vinculação |

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessment/questionnaires` | Listar questionários |
| POST | `/api/v1/assessment/questionnaires` | Criar questionário |
| GET | `/api/v1/assessment/questionnaires/{id}` | Ver questionário |
| PATCH | `/api/v1/assessment/questionnaires/{id}` | Atualizar questionário |
| DELETE | `/api/v1/assessment/questionnaires/{id}` | Deletar questionário |
| GET | `/api/v1/assessment/questionnaires/{id}/questions` | Listar questões |
| POST | `/api/v1/assessment/questionnaires/{id}/questions` | Adicionar questões |

### Permissions

```
assessment.questionnaires.list
assessment.questionnaires.create
assessment.questionnaires.view
assessment.questionnaires.update
assessment.questionnaires.delete
```

---

## 3. QuizQuestions

### Modelo

```
quiz_questions
- id
- tenant_id              // FK
- instructor_id          // FK (criador)
- question               // texto da questão (longtext)
- type                   // enum: single_choice | multiple_choice | true_false
- options                // JSON array
- correct_options        // JSON array de índices corretos
- explanation            // explicação da resposta
- points                 // pontuação (default: 1)
- is_active
- created_at
- updated_at
```

### Tipos de Questão

| Tipo | Descrição | Resposta |
|------|-----------|----------|
| `single_choice` | Uma única opção correta | 1 índice |
| `multiple_choice` | Múltiplas opções corretas | múltiplos índices |
| `true_false` | Verdadeiro ou falso | 1 índice |

### Estrutura de Options

```json
[
  { "text": "Opção A", "correct": false },
  { "text": "Opção B", "correct": true },
  { "text": "Opção C", "correct": false },
  { "text": "Opção D", "correct": false }
]
```

### Regras Importantes (para LLM)

```
⚠️ IMPORTANTE:
- Questões usadas em tentativas NÃO PODEM ser editadas
- Isso garante integridade dos relatórios e estatísticas
- Se precisar mudar → criar nova questão
```

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessment/questions` | Listar questões |
| POST | `/api/v1/assessment/questions` | Criar questão |
| GET | `/api/v1/assessment/questions/{id}` | Ver questão |
| PATCH | `/api/v1/assessment/questions/{id}` | Atualizar questão |
| DELETE | `/api/v1/assessment/questions/{id}` | Deletar questão |

### Permissions

```
assessment.questions.list
assessment.questions.create
assessment.questions.view
assessment.questions.update
assessment.questions.delete
```

---

## 4. QuizAttempts

### Modelo

```
quiz_attempts
- id
- tenant_id              // FK
- user_id                // FK (aluno)
- questionnaire_id       // FK
- status                 // enum: in_progress | completed
- questionnaire_snapshot // JSON
- course_snapshot       // JSON (se course quiz)
- module_snapshot       // JSON (se lesson quiz)
- started_at
- finished_at
- score                  // nota final (0-100)
- passed                 // aprovado ou não
- time_spent_seconds
- created_at
- updated_at
```

### Snapshots (IMPORTANTE)

Ao iniciar uma tentativa, o sistema faz snapshot de:

```
- Questionário: title, description, passing_score
- Curso (se course quiz): id, title
- Módulo (se lesson quiz): id, title
- Cada questão respondida: texto, opções, resposta correta
```

Isso garante que a tentativa permanece igual mesmo se o questionário for alterado depois.

### Fluxo do Aluno

```
1. Iniciar tentativa
   POST /api/v1/assessment/attempts/questionnaires/{id}

2. Ver tentativa atual
   GET /api/v1/assessment/attempts/{id}

3. Responder questão
   PATCH /api/v1/assessment/attempts/{id}
   Body: { question_snapshot, selected_options }

4. Finalizar tentativa
   POST /api/v1/assessment/attempts/{id}/finish

5. Ver resultado
   GET /api/v1/assessment/attempts/{id}
   (retorna score, passed)
```

### Cálculo de Score

```
score = (pontos_obtidos / pontos_totais) * 100

passed = score >= questionnaire.passing_score
```

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/v1/assessment/attempts/questionnaires/{id}` | Iniciar tentativa |
| GET | `/api/v1/assessment/attempts/{id}` | Ver tentativa |
| PATCH | `/api/v1/assessment/attempts/{id}` | Responder questão |
| POST | `/api/v1/assessment/attempts/{id}/finish` | Finalizar tentativa |

### Permissions

```
assessment.attempts.create
assessment.attempts.view
assessment.attempts.answer
assessment.attempts.finish
```

---

## 5. Certificates

### Configuração no Course

O certificado é configurado na tabela `courses`:

```
certificate_enabled          // boolean - emite certificado?
certificate_min_progress     // integer - % mínima de conclusão
certificate_requires_quiz   // boolean - requer quiz aprovado?
certificate_min_score       // integer - % mínima no quiz
```

### Emissão Automática

O certificado é emitido automaticamente quando:
1. Progresso >= certificate_min_progress
2. Se certificate_requires_quiz: quiz aprovado com >= certificate_min_score

### Modelo

```
certificates
- id
- tenant_id              // FK
- user_id                // FK (aluno)
- enrollment_id          // FK
- course_id              // FK
- certificate_number     // único: CERT-2026-XXXXX
- issued_at
- status                 // enum: issued | revoked
- created_at
- updated_at
```

### Certificate Number

Formato: `CERT-{ANO}-{CODIGO_HEX}`

Exemplo: `CERT-2026-A1B2C3D4`

### Verificação Pública

```
GET /api/v1/assessment/certificates/verify/{certificateNumber}
```

Retorna:
```json
{
  "valid": true,
  "certificate": {
    "certificate_number": "CERT-2026-A1B2C3D4",
    "status": "issued",
    "issued_at": "2026-01-15T10:00:00Z",
    "course_title": "Curso de Laravel",
    "user_name": "João Silva"
  }
}
```

### Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/assessment/certificates` | Listar certificados |
| GET | `/api/v1/assessment/certificates/{id}` | Ver certificado |
| GET | `/api/v1/assessment/certificates/verify/{code}` | Verificar (público) |

### Permissions

```
assessment.certificates.list
assessment.certificates.view
assessment.certificates.revoke
```

---

## 6. Pivot Tables

### QuestionnaireQuestion

```
questionnaire_questions
- id
- questionnaire_id      // FK
- quiz_question_id      // FK
- sort_order            // ordem no questionário
```

### QuizQuestionCategory

```
quiz_question_categories
- id
- quiz_question_id      // FK
- category_id           // FK
```

---

## 7. Eventos para Estatísticas

### Eventos Necessários

```
QuizAttemptStarted   - quando aluno inicia tentativa
QuizAttemptFinished  - quando aluno termina tentativa
QuizAttemptPassed    - quando aprovado
QuizAttemptFailed    - quando reprovado
CourseCompletedEvent - quando curso 100%
CertificateIssuedEvent - quando certificado emitido
CertificateRevokedEvent - quando certificado revogado
```

### Processamento

```
1. Evento é disparado
2. Vai para fila (RabbitMQ)
3. Consumer processa → MariaDB de stats
4. Dados históricos preservados
```

---

## 8. Fluxos

### Fluxo do Instrutor

```
1. Criar questões
   POST /api/v1/assessment/questions

2. Criar questionário
   POST /api/v1/assessment/questionnaires

3. Adicionar questões ao questionário
   POST /api/v1/assessment/questionnaires/{id}/questions

4. Configurar certificado no curso
   PATCH /api/v1/learning/courses/{id}
   Body: { certificate_enabled: true, certificate_min_score: 70 }

5. Acompanhar tentativas
   GET /api/v1/assessment/attempts/{id}
```

### Fluxo do Aluno

```
1. Ver questionário disponível
   GET /api/v1/assessment/questionnaires/{id}

2. Iniciar tentativa
   POST /api/v1/assessment/attempts/questionnaires/{id}

3. Responder questões
   PATCH /api/v1/assessment/attempts/{id}
   Body: { question_snapshot, selected_options }

4. Finalizar tentativa
   POST /api/v1/assessment/attempts/{id}/finish

5. Ver certificado (se aprovado)
   GET /api/v1/assessment/certificates/{id}
```

---

## 9. Status de Implementação

### ✅ Feito

- [x] Questionnaire CRUD
- [x] QuizQuestion (banco de questões)
- [x] QuizAttempts com snapshot
- [x] QuizAttemptAnswers com snapshot
- [x] Score Calculation
- [x] Certificate config na tabela Course
- [x] Certificates
- [x] Certificate Validation (público)
- [x] Evento LessonCompletedEvent

### ⚠️ Precisa Revisão

- [ ] Permissions para roles corretas (tenant_admin, instructor)
- [ ] Attach questions to questionnaire (endpoint)
- [ ] List questions in questionnaire

### ⏳ Pendente

- [ ] Fluxo do aluno (start/finish attempt)
- [ ] Certificate PDF Generation
- [ ] Eventos: QuizAttemptFinished, CertificateIssued

---

## 10. Permissions por UserType

| Permissão | Developer | Admin | Instructor | Student |
|-----------|:---------:|:-----:|:----------:|:-------:|
| assessment.questionnaires.* | ✅ | ✅ | ✅ | ❌ |
| assessment.questions.* | ✅ | ✅ | ✅ | ❌ |
| assessment.attempts.list | ✅ | ✅ | view | ❌ |
| assessment.attempts.view | ✅ | ✅ | view | own |
| assessment.attempts.create | ✅ | ✅ | ❌ | ✅ |
| assessment.attempts.answer | ✅ | ✅ | ❌ | ✅ |
| assessment.attempts.finish | ✅ | ✅ | ❌ | ✅ |
| assessment.certificates.* | ✅ | ✅ | view | own |

---

## 11. Referência Rápida

| Recurso | Endpoint | Permissão |
|---------|----------|----------|
| Listar questionários | GET /questionnaires | assessment.questionnaires.list |
| Criar questionário | POST /questionnaires | assessment.questionnaires.create |
| Listar questões | GET /questions | assessment.questions.list |
| Criar questão | POST /questions | assessment.questions.create |
| Atualizar questão | PATCH /questions/{id} | assessment.questions.update |
| Iniciar tentativa | POST /attempts/questionnaires/{id} | assessment.attempts.create |
| Responder | PATCH /attempts/{id} | assessment.attempts.answer |
| Finalizar | POST /attempts/{id}/finish | assessment.attempts.finish |
| Ver certificados | GET /certificates | assessment.certificates.list |
| Verificar | GET /certificates/verify/{code} | público |
