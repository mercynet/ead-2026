# EAD 2026 - Roadmap de Desenvolvimento

## Visão Geral

Plataforma EAD multi-tenant API-first, reconstrução do sistema eadIA com arquitetura RESTful pura.

## Stack Tecnológica
- PHP 8.4 + Laravel 12
- Laravel Sanctum (autenticação)
- Spatie Permission (RBAC)
- MySQL 8.0
- Redis (cache/queues)
- Pest 4 (testes)

## Arquitetura
- Actions em `app/Actions/<Domain>/<Resource>/`
- Controllers lean com `ApiContext` injetado
- Exceptions centralizadas em `bootstrap/app.php`
- FormRequests para validação
- JsonResource para responses

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
| Quiz CRUD | ⏳ | P1 |
| QuizQuestions | ⏳ | P1 |
| QuizAttempts | ⏳ | P1 |
| QuizAttemptAnswers | ⏳ | P1 |
| Score Calculation | ⏳ | P1 |
| CertificateTemplates | ⏳ | P2 |
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
| Stripe Gateway | ⏳ | P2 |

### 5. Ecosystem & Plugins (0% completo)
| Feature | Status | Prioridade |
|---------|--------|------------|
| Plugin CRUD (admin) | ⏳ | P2 |
| PluginSubscriptions | ⏳ | P2 |
| PluginBilling | ⏳ | P2 |
| Marketplace endpoints | ⏳ | P2 |
| Plugin activation/deactivation | ⏳ | P2 |

---

## Próximos Passos (Recomendado)

### Fase 1: Completar Learning (P0)
1. ~~Implementar `Enrollment` model e migrações~~ ✅
2. ~~Implementar `LessonProgress` model e migrações~~ ✅
3. ~~`GET /courses/{id}/enrollment` - Status da matrícula~~ ✅
4. ~~`GET /lessons/{id}` - Acesso à aula~~ ✅
5. ~~`POST /lessons/{id}/progress` - Heartbeat de progresso~~ ✅
6. ~~`GET /courses/{id}/modules` - Árvore do curso com tracking~~ ✅
7. Evento `LessonCompletedEvent` + cálculo de progresso assíncrono
8. Pre-signed URLs para mídias (AWS S3, Vimeo)

### Fase 2: Assessment (P1)
1. Models de Quiz e Questions
2. QuizAttempts com snapshot
3. Cálculo de score
4. Certificados

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
