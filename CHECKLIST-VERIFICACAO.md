# Checklist de Verificação - EAD 2026

## 1. Arquitetura e Padrões

### ✅ Implementado Corretamente
- [x] Actions em `app/Actions/<Domain>/<Resource>/`
- [x] Controllers lean com `ApiContext` injetado
- [x] Exceptions centralizadas em `bootstrap/app.php`
- [x] FormRequests para validação
- [x] JsonResource para responses
- [x] ApiContext como Value Object readonly

### ⚠️ Problemas Encontrados
- [x] **ApiContext** usava `RuntimeException` genérico ao invés de `TenantContextRequiredException` (spec line 96) - **CORRIGIDO**
- [x] **ApiContext** não lançava exceção para user não autenticado (deveria ter `requiredUser` exception) - **CORRIGIDO**

---

## 2. Diferenças Críticas eadIA vs ead2026 (REVISAR)

### ⚠️ ARQUITETURA DE RELACIONAMENTOS - DIFERENÇA CRÍTICA
O eadIA tem uma arquitetura de relacionamentos diferente que deve ser implementada:

- [ ] **Lesson ↔ CourseModule ↔ Course**: Uma aula pode pertencer a múltiplos módulos de múltiplos cursos
  - Tabela pivot: `course_module_lesson` (não `course_module_id` direto na lessons)
  - Implementar no ead2026: aulas belongToMany courseModules

- [ ] **Course ↔ Category**: Relacionamento many-to-many com pivot `category_course`
  - Precisa ser tenant-aware (filtrar por tenant_id)

### ⚠️ SISTEMA DE ROLES - DIFERENÇA CRÍTICA
O eadIA usa esta hierarquia (REVISAR se está no ead2026):
- [ ] **developer**: Acesso total (somente outros developers veem)
- [ ] **tenant_admin**: Admin do tenant
- [ ] **instructor**: Criador de cursos (só vê próprios cursos)
- [ ] **student**: Acesso a cursos matriculados

### ⚠️ REGRAS DE SEGURANÇA (CLAUDE.md - IMPORTANTE)
Do CLAUDE.md do eadIA:
- [ ] **REGRA CRÍTICA**: Tenants só podem aparecer para users com role "developer"
- [ ] NUNCA commit sem testar
- [ ] Análise rigorosa obrigatória antes de finalizar
- [ ] Testar funcionalidade real antes de finalizar
- [ ] Debug sistemático quando algo não funcionar

---

## 3. Endpoints Implementados vs Specs

### Core & Identity
| Spec | Implementado | Observações |
|------|--------------|-------------|
| POST /auth/login | ✅ | Taxa 5/min OK |
| POST /auth/logout | ✅ | |
| GET /auth/me | ✅ | |
| POST /users | ✅ | |
| GET /users | ✅ | |
| GET /users/{id} | ✅ | |
| PATCH /users/me | ✅ | |
| PATCH /users/me/password | ✅ | |
| GET /tenant/config | ❌ | Pendente |
| PATCH /tenant/config | ❌ | Pendente |

### Catalog & Learning
| Spec | Implementado | Observações |
|------|--------------|-------------|
| GET /catalog/courses | ✅ | |
| GET /catalog/courses/{slug} | ✅ | |
| GET /catalog/categories | ✅ | |
| POST /catalog/categories | ✅ | |
| GET /courses/{id}/enrollment | ✅ | |
| GET /courses/{id}/modules | ✅ | |
| GET /lessons/{id} | ✅ | |
| POST /lessons/{id}/progress | ✅ | |
| LessonCompletedEvent | ✅ | Acabado de implementar |

---

## 3. Models vs eadIA (Database Schema)

### ✅ Presentes
- [x] Tenant (spec: `landlord_tenants`)
- [x] User (com tenant_id)
- [x] Category (hierárquica)
- [x] Course
- [x] CourseModule
- [x] Lesson
- [x] Enrollment
- [x] LessonProgress
- [x] TenantCustomization
- [x] TenantIntegration

### ❌ Faltando (do eadIA)
- [ ] LessonMedia (mídias das aulas)
- [ ] LessonMediaProgress
- [ ] CourseMaterial (materiais baixáveis)
- [ ] MaterialDownload
- [ ] Rating (avaliações)
- [ ] RatingStats

### ⚠️ Diferenças de Schema
- **Tenant**: spec usa `slug`, ead2026 usa só `domain` - verificar necessidade
- **Course**: spec tem campos detalhados (target_audience, requirements, level, etc) - verificar se precisamos
- **Lesson**: spec tem `video_path`, ead2026 não tem - necessário para Pre-signed URLs

---

## 4. Factories - Estado Atual

### ✅ Boas
- [x] TenantFactory
- [x] UserFactory (agora com tenant_id)
- [x] CourseFactory
- [x] CourseModuleFactory
- [x] LessonFactory (agora com slug, status, is_active)
- [x] EnrollmentFactory
- [x] LessonProgressFactory
- [x] CategoryFactory

### ⚠️ Ações Tomadas
- Adicionado `tenant_id` na UserFactory
- Adicionado `slug`, `status`, `is_active` na LessonFactory

---

## 5. Testes

### Estado Atual
- [x] 68 testes passando
- [x] Coverage: 87.5%
- [x] LessonCompletedEventTest refatorado com factories

### ⚠️ Tests que precisam de factories
- [ ] Verificar outros testes que usam `create()` ao invés de factories

---

## 6. Middleware e Segurança

### ✅ Implementado
- [x] resolve.tenant.optional
- [x] resolve.tenant
- [x] api.context
- [x] tenant.access
- [x] tenant.required.unless.developer

### ⚠️ Verificações
- [ ] Middleware order: `resolve.tenant.optional` antes de `api.context` ✅ OK
- [ ] Rate limiting no login: `throttle:5,1` ✅ OK

---

## 7. Items Pendentes do Roadmap

### Fase 1: Completar Learning (P0)
- [x] Enrollment model e migrações
- [x] LessonProgress model e migrações
- [x] GET /courses/{id}/enrollment
- [x] GET /lessons/{id}
- [x] POST /lessons/{id}/progress
- [x] GET /courses/{id}/modules
- [x] LessonCompletedEvent
- [ ] Pre-signed URLs para mídias (AWS S3, Vimeo)

### Fase 2: Assessment (P0)
- [ ] Quiz CRUD
- [ ] QuizQuestions
- [ ] QuizAttempts
- [ ] QuizAttemptAnswers
- [ ] Score Calculation
- [ ] CertificateTemplates
- [ ] Certificates
- [ ] Certificate Validation
- [ ] PDF Generation

### Fase 3: Financial (P1)
- [ ] Orders CRUD
- [ ] OrderItems
- [ ] Payments
- [ ] POST /checkout
- [ ] Webhooks
- [ ] TenantPaymentGateway

---

## 8. Issues de Código (DRY, SOLID, KISS, YAGNI)

### ✅ Bom
- ApiContext injetado corretamente
- Controllers lean
- Response pattern consistente com `->toResponse(request())`
- Exceptions centralizadas
- Actions separated by responsibility

### ⚠️ Para Corrigir
1. **ApiContext.php:27-29** - Usar `TenantContextRequiredException` ao invés de `RuntimeException`
2. **ApiContext.php** - Falta exception para user não autenticado (criar `AuthenticatedUserRequiredException`)
3. **Verificar** se há código duplicado entre actions similares

---

## 9. Checklist de Correções Imediatas

### Alta Prioridade
- [ ] Corrigir ApiContext para usar TenantContextRequiredException
- [ ] Adicionar AuthenticatedUserRequiredException ao ApiContext

### Média Prioridade  
- [ ] Revisar se todos os testes usam factories
- [ ] Adicionar Campos de Course que podem estar faltando (level, target_audience, etc)

### Baixa Prioridade (Feature Work)
- [ ] Implementar GET /tenant/config
- [ ] Implementar LessonMedia
- [ ] Implementar Ratings

---

## 10. Comparação Final ead2026 vs eadIA

### ✅ Vantagens do ead2026
- API-first desde o início
- Arquitetura mais limpa (Actions, ApiContext)
- Laravel 12 + PHP 8.4
- Testes com Pest
- Coverage alto

### ⚠️ Pontos a Atenção
- eadIA tem 49 models vs 10 models atuais - muitos domains pendentes
- eadIA tem sistema de plugins completo - não implementado
- eadIA tem 5 painéis Filament - não aplicável (API-only)

---

## Ação Recomendada

1. **Imediata**: Corrigir ApiContext (2 linhas)
2. **Curto prazo**: Implementar Pre-signed URLs (Fase 1)
3. **Médio prazo**: Começar Assessment (Fase 2)
4. **Longo prazo**: Completar Financial e Plugins
