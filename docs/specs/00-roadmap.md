# EAD 2026 - Roadmap de Desenvolvimento

## Visão Geral

Plataforma EAD multi-tenant API-first, reconstrução do sistema eadIA com arquitetura RESTful pura.

## Stack Tecnológica
- PHP 8.4 + Laravel 12
- Laravel Sanctum (autenticação)
- Spatie Permission (RBAC)
- MySQL 8.0 (dados principais)
- MariaDB (estatísticas/BI)
- Redis (cache/queues)
- RabbitMQ (filas assíncronas)
- Pest 4 (testes)

## Arquitetura
- Actions em `app/Actions/<Domain>/<Resource>/`
- Controllers lean com `ApiContext` injetado
- Exceptions centralizadas em `bootstrap/app.php`
- FormRequests para validação
- JsonResource para responses
- Eventos Laravel para captura de estatísticas

---

## Regras de Negócio Fundamentais

### Multi-Tenancy
- **Developers** (equipe): acesso total, CRUD completo para todos os tenants
- **Tenant Admins**: mesmo que developer, mas isolado em seu tenant
- **Instructors**: criam ambiente pedagógico (cursos, módulos, aulas, questionários)
- **Students**: consomem cursos, vêem tudo que é seu

### Usuários e CPF
- Usuários identificados por **CPF único** (não para login)
- **Login via email** (email pode se repetir entre tenants)
- CPF usado para evitar duplicação de cadastros entre tenants
- Ao matricular: buscar por CPF primeiro, se existir, reutilizar

### Categorias
- **Sistema** (globais): criadas/editadas apenas por developers, todos tenants podem usar
- **Custom** (do tenant): CRUD livre pelo tenant
- Categorias custom podem ser duplicadas entre tenants diferentes
- Categoria de sistema: nunca pode ter nome duplicado por tenant

---

## Status por Domínio

### 1. Core & Identity (80% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Auth (login/logout/me) | ✅ | P0 |
| Users CRUD | ✅ | P0 |
| ApiContext Pattern | ✅ | P0 |
| Middleware de Tenant | ✅ | P0 |
| TenantCustomization | ⏳ | P1 |
| TenantIntegration | ⏳ | P1 |
| GET /tenant/config | ⏳ | P1 |
| Impersonação | ⏳ | P2 |

### 2. Catalog & Learning (80% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Categories CRUD | ✅ | P0 |
| Courses CRUD | ✅ | P0 |
| CourseModules CRUD | ✅ | P0 |
| Lessons CRUD | ✅ | P0 |
| Enrollments | ✅ | P0 |
| LessonProgress | ✅ | P0 |
| LessonViews (estatísticas) | ⏳ | P1 |
| GET /courses/{id}/enrollment | ✅ | P0 |
| GET /lessons/{id} | ✅ | P0 |
| POST /lessons/{id}/progress | ✅ | P0 |
| GET /courses/{id}/modules | ✅ | P0 |
| LessonCompletedEvent | ✅ | P0 |
| Pre-signed URLs | ⏳ | P1 |
| LessonMedia/MediaProgress | ⏳ | P1 |
| CourseMaterials | ⏳ | P2 |
| Ratings | ⏳ | P2 |

### 3. Assessment (0% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Questionário (Quiz) CRUD | ⏳ | P1 |
| Questionários Vinculados (lesson/course) | ⏳ | P1 |
| Questionários Avulsos/Simulados | ⏳ | P1 |
| Questões (banco independente) | ⏳ | P1 |
| Questões com categorias | ⏳ | P1 |
| QuizAttempts com snapshot | ⏳ | P1 |
| QuizAttemptAnswers com snapshot | ⏳ | P1 |
| Score Calculation | ⏳ | P1 |
| Certificate Config (em Course) | ⏳ | P1 |
| Certificates | ⏳ | P2 |
| Certificate Validation | ⏳ | P2 |
| PDF Generation | ⏳ | P2 |

### 4. Financial (0% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Orders CRUD | ⏳ | P1 |
| OrderItems | ⏳ | P1 |
| Payments | ⏳ | P1 |
| POST /checkout | ⏳ | P1 |
| Webhooks | ⏳ | P1 |
| TenantPaymentGateway | ⏳ | P1 |
| Cart (plugin) | ⏳ | P2 |
| Coupons (plugin) | ⏳ | P2 |
| Asaas Gateway (plugin) | ⏳ | P2 |
| Stripe Gateway (plugin) | ⏳ | P2 |

### 5. Ecosystem & Plugins (0% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Plugin System | ⏳ | P2 |
| PluginSubscriptions | ⏳ | P2 |
| PluginBilling | ⏳ | P2 |
| Assinaturas (planos) | ⏳ | P2 |

---

## Eventos para Estatísticas (MariaDB Separado)

Todos os eventos devem ser Disparados e processados via fila (RabbitMQ) para o MariaDB de stats:

### Eventos de Usuários
- `UserCreated` - usuário criado
- `UserUpdated` - usuário atualizado
- `UserDeleted` - usuário deletado (soft delete)
- `UserRoleChanged` - role alterada

### Eventos de Cursos
- `CourseCreated` - curso criado
- `CoursePublished` - curso publicado
- `CourseUpdated` - curso atualizado
- `CourseArchived` - curso arquivado

### Eventos de Matrículas
- `EnrollmentCreated` - matrícula criada
- `EnrollmentCompleted` - curso 100% concluído
- `EnrollmentExpired` - matrícula expirada
- `EnrollmentCancelled` - matrícula cancelada

### Eventos de Aulas
- `LessonViewedEvent` - aula visualizada (para stats de replay)
- `LessonCompletedEvent` ✅

### Eventos de Questionários
- `QuizAttemptStarted` - tentativa iniciada
- `QuizAttemptFinished` - tentativa finalizada
- `QuizAttemptPassed` - aprovado
- `QuizAttemptFailed` - reprovado

### Eventos de Certificados
- `CertificateIssuedEvent` - certificado emitido
- `CertificateRevokedEvent` - certificado revogado

### Eventos de Pagamentos
- `OrderCreated` - pedido criado
- `OrderPaidEvent` - pagamento confirmado
- `OrderFailedEvent` - pagamento falhou
- `OrderRefundedEvent` - pagamento estornado

### Eventos de Plugins
- `PluginSubscribedEvent` - plugin assinado
- `PluginUnsubscribedEvent` - assinatura cancelada
- `PluginActivatedEvent` - plugin ativado
- `PluginDeactivatedEvent` - plugin desativado

---

## Próximos Passos (Recomendado)

### Fase 1: Completar Learning (P0)
1. ~~Implementar `Enrollment` model e migrações~~ ✅
2. ~~Implementar `LessonProgress` model e migrações~~ ✅
3. ~~`GET /courses/{id}/enrollment` - Status da matrícula~~ ✅
4. ~~`GET /lessons/{id}` - Acesso à aula~~ ✅
5. ~~`POST /lessons/{id}/progress` - Heartbeat de progresso~~ ✅
6. ~~`GET /courses/{id}/modules` - Árvore do curso com tracking~~ ✅
7. ~~Evento `LessonCompletedEvent`~~ ✅
8. `LessonViews` - tabela para estatísticas de replay
9. Pre-signed URLs para mídias (AWS S3, Vimeo)

### Fase 2: Assessment (P1)
1. Models de Questionário e Questões
2. Questionários vinculados (morph: lesson/course/standalone)
3. Questões com banco independente e categorias
4. QuizAttempts com snapshot
5. Cálculo de score
6. Certificate config na tabela Course
7. Certificados

### Fase 3: Financial (P1)
1. Orders e Payments
2. Checkout endpoint
3. Webhooks de gateway
4. Matrícula automática pós-pagamento

---

## Fonte de Referência

O projeto `eadIA` em `/home/paulo/www/eadIA` contém a implementação completa de referência:
- 49 models
- 97 migrations
- 5 painéis Filament
- 140+ testes
- Sistema de plugins completo

Usar como referência para regras de negócio e estrutura de dados.
