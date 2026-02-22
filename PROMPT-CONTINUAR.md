# Prompt de Contexto - EAD 2026

## Visão Geral do Projeto

Plataforma EAD multi-tenant API-first, reconstrução do sistema eadIA com arquitetura RESTful pura em Laravel 12.

## Stack Tecnológica
- PHP 8.4 + Laravel 12
- Laravel Sanctum (autenticação)
- Spatie Permission (RBAC)
- MySQL 8.0 (dados principais)
- MariaDB (estatísticas/BI) - planejado
- Redis (cache/queues)
- RabbitMQ (filas assíncronas) - planejado
- Pest 4 (testes)

## Regras de Negócio Fundamentais

### Multi-Tenancy
- **Developers**: acesso total, CRUD completo para todos os tenants
- **Tenant Admins**: mesmo que developer, mas isolado em seu tenant
- **Instructors**: criam ambiente pedagógico (cursos, módulos, aulas, questionários)
- **Students**: consomem cursos, vêem tudo que é seu
- **CPF**: usado para evitar duplicação de cadastros entre tenants (não para login)
- **Login via email** (email pode se repetir entre tenants)

### Categorias
- **Sistema** (globais): criadas/editadas apenas por developers, todos tenants podem usar
- **Custom** (do tenant): CRUD livre pelo tenant
- Categoria de sistema: nunca pode ter nome duplicado por tenant
- Categorias custom podem ser duplicadas entre tenants diferentes

### Questionários
- Questões são banco independente, reaproveitáveis em múltiplos questionários
- Questões podem ter múltiplas categorias (opcional)
- Uma questão usada em tentativa **não pode mais ser editada** (gera snapshot)
- Cada tentativa gera snapshot de todas as questões respondidas
- Tipos: lesson (vinculado a aula), course (prova final), standalone (avulso/simulado)

### Certificados
- Por curso, não por aula
- Critérios configuráveis na tabela courses:
  - certificate_enabled
  - certificate_min_progress (%)
  - certificate_requires_quiz
  - certificate_min_score (%)

### Reassistir Aulas
- Aula concluída pode ser reassistida
- Não muda status de "concluída"
- Cada visualização gera registro em lesson_views (para estatísticas)

### Eventos para Estatísticas
- Todos os eventos significativos devem ser Disparados via Laravel Events
- Processados por Queue (RabbitMQ) para MariaDB de estatísticas
- Dados históricos nunca se perdem

---

## Status Atual do Desenvolvimento

### ✅ Implementado (68 testes, 87.5% coverage)

**Core & Identity:**
- Auth (login/logout/me)
- Users CRUD
- ApiContext Pattern
- Middleware de Tenant

**Catalog & Learning:**
- Categories CRUD
- Courses CRUD
- CourseModules CRUD
- Lessons CRUD
- Enrollments
- LessonProgress
- LessonCompletedEvent
- Endpoints: login, logout, me, users CRUD, catalog, enrollment, progress

---

## Próximas Prioridades

### Fase 1: Completar Learning
1. LessonViews - tabela para estatísticas de replay
2. Pre-signed URLs para mídias (AWS S3, Vimeo)
3. CourseMaterials

### Fase 2: Assessment (PRIORIDADE)
1. Questionário (Questionnaire) - modelo e CRUD
2. Questões (QuizQuestion) - banco independente
3. Relacionamento morph: lesson, course, standalone
4. QuizAttempts com snapshot
5. QuizAttemptAnswers com snapshot
6. Score Calculation
7. Certificate config na tabela Course
8. Certificates

### Fase 3: Financial
1. Orders e Payments
2. Checkout endpoint
3. Webhooks de gateway
4. Matrícula automática pós-pagamento

### Fase 4: Plugins
1. Plugin System
2. Assinaturas
3. Cart

---

## Estrutura de Arquivos

```
app/
├── Actions/<Domain>/<Resource>/...
├── Events/<Domain>/...
├── Http/
│   ├── Controllers/Api/V1/<Domain>/...
│   ├── Context/ApiContext.php
│   ├── Middleware/...
│   └── Resources/...
├── Models/...
├── Policies/...
├── Exceptions/...
database/
├── factories/...
└── migrations/...
docs/specs/
├── 00-roadmap.md
├── 01-core-identity.md
├── 02-catalog-learning.md
├── 03-assessment.md
└── 04-financial.md
tests/
└── Feature/Api/...
```

---

## Arquitetura

- **Actions**: regras de negócio em `app/Actions/`
- **Controllers**: lean, apenas orquestram (ApiContext + Gate + Action + Resource)
- **ApiContext**: injetado via middleware, encapsula user e tenant
- **Exceptions**: renderizadas centralmente em `bootstrap/app.php`
- **Response Pattern**: `->toResponse(request())` para Resources

---

## Como Continuar

1. Leia `docs/specs/00-roadmap.md` para ver status completo
2. Escolha uma feature da Fase 2 (Assessment) para implementar
3. Crie migrations, models, factories, actions, controllers, resources, policies, requests
4. Escreva testes com Pest
5. Execute `sail artisan test --compact --coverage`
6. Execute `vendor/bin/pint --dirty` para formatar
7. Atualize o roadmap com o status

---

## Comandos Úteis

```bash
# Executar testes
sail artisan test --compact --coverage

# Criar migration
sail artisan make:migration create_questionnaires_table

# Criar model com factory
sail artisan make:model Questionnaire -f

# Criar action
sail artisan make:class Actions/Assessment/Questionnaire/ListQuestionnairesAction

# Criar controller
sail artisan make:controller Api/V1/Assessment/QuestionnaireController

# Criar policy
sail artisan make:policy QuestionnairePolicy

# Formatar código
vendor/bin/pint --dirty

# Verificar rotas
sail artisan route:list --path=api/v1
```

---

## Referências

- `docs/specs/03-assessment.md` - Especificação detalhada do domínio Assessment
- `docs/specs/02-catalog-learning.md` - Especificação do domínio Learning
- `AGENTS.md` - Padrões de código e arquitetura
- `CHECKLIST-VERIFICACAO.md` - Checklist de verificação

---

**Última atualização**: Fevereiro 2026
