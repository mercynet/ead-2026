# Domain Spec: Core & Identity (API-First)

## Status de Implementação

### ✅ Implementado
- [x] Middleware de resolução de tenant (`ResolveTenant`, `ResolveTenantOptional`)
- [x] Middleware de validação de acesso ao tenant (`EnsureTenantAccess`)
- [x] Middleware de contexto obrigatório para não-developers (`EnsureTenantRequiredForNonDeveloper`)
- [x] `ApiContext` Value Object injetado via middleware
- [x] Exceptions de domínio com render centralizado (`TenantContextRequiredException`, `InvalidCredentialsException`, `ResourceNotFoundException`)
- [x] Response Pattern padronizado (`->toResponse(request())`, `JsonResource`)
- [x] Models: `User`, `Tenant` com relacionamentos e roles/permissions (spatie/laravel-permission)
- [x] Policies: `UserPolicy`, `CategoryPolicy`
- [x] Seeders: Roles, Permissions, Users de desenvolvimento

### ✅ Endpoints Implementados
- [x] `POST /api/v1/core/auth/login` - Autenticação com rate limiting (5/min), token com device type
- [x] `POST /api/v1/core/auth/logout` - Revoga token atual
- [x] `GET /api/v1/core/auth/me` - Retorna usuário + roles + permissions
- [x] `POST /api/v1/core/users` - Registro de usuário
- [x] `GET /api/v1/core/users` - Listagem (tenant-scoped, developers veem todos)
- [x] `GET /api/v1/core/users/{id}` - Detalhe do usuário
- [x] `PATCH /api/v1/core/users/me` - Atualização de perfil
- [x] `PATCH /api/v1/core/users/me/password` - Atualização de senha

### ⏳ Pendente
- [ ] `GET /api/v1/core/tenant/config` - Configurações públicas do tenant (white-label)
- [ ] `PATCH /api/v1/core/tenant/config` - Edição de personalizações (tenant_admin)
- [ ] Impersonação segura (tokens especiais com Sanctum Abilities)
- [ ] `TenantCustomization` model e relacionamentos
- [ ] `TenantIntegration` model e relacionamentos

---

## Visão Geral
Este domínio é responsável pela Autenticação, Identidade do Usuário, Controle de Acesso (RBAC) e Multi-Tenancy. Ele serve como o alicerce fundamental, garantindo que usuários sejam devidamente identificados, autorizados e roteados para o inquilino (Tenant) correto.

## 1. Padrões Arquiteturais e API RESTful Estrito
- **Autenticação:** Tokens opacos gerenciados pelo `Laravel Sanctum`.
- **Formato da API:** JSON padrão focado no padrão REST (endpoints pragmáticos e semânticos). Para listagens, usar paginação por cursor (`cursorPaginate`) e Resource Collections.
- **Controladores / Handlers:** Controller HTTP com métodos explícitos (`index`, `show`, `store`, etc.), sem `__invoke` como padrão do domínio.
- **Command/Query (Action Layer):** Toda regra de negócio deve viver em `app/Actions/<Domain>/<Resource>/...`, separando leitura (Query Actions) e escrita (Command Actions). Controller apenas orquestra request/authorization/response.
- **Autorização Obrigatória por Endpoint:** Todo método de controller deve validar autorização via Gate/Policy antes de executar a Action.
- **Validação & Transferência:** `FormRequests` do Laravel para validação estrita.
- **Tratamento de Exceções:** Formato unificado de erros de API com render centralizado em `bootstrap/app.php`.
- **API Context (`ApiContext`):** Value Object injetado automaticamente via middleware que encapsula `$user` e `$tenant`. Controllers e Actions recebem `ApiContext` como parâmetro, nunca acessam request/tenant manualmente.

### 1.1 ApiContext Pattern (Obrigatório)
```php
// app/Http/Context/ApiContext.php - Value Object injetado via middleware
final readonly class ApiContext {
    public function __construct(
        public readonly ?User $user,
        public readonly ?Tenant $tenant,
    ) {}
    
    public function hasUser(): bool { return $this->user !== null; }
    public function hasTenant(): bool { return $this->tenant !== null; }
    public function requiredUser(): User { ... }
    public function requiredTenant(): Tenant { ... }
}

// Controller - injetar ApiContext no método
public function index(ApiContext $context): JsonResponse
{
    Gate::forUser($context->user)->authorize('core.users.list', [$context->tenant]);
    $result = $this->action->handle($context);
    return Resource::collection($result)->toResponse(request());
}

// Action - receber ApiContext
public function handle(ApiContext $context): CursorPaginator
{
    if ($context->user->isDeveloper()) { ... }
    if ($context->tenant !== null) { ... }
}
```

### 1.2 Response Pattern (Obrigatório)
```php
// Para collections paginadas - retorna AnonymousResourceCollection
return UserResource::collection($paginator);

// Para resource único - retorna o tipo do Resource
return UserResource::make($model);

// Para resource único com status diferente de 200 - usa toResponse
return UserResource::make($model)->toResponse(request())->setStatusCode(201);

// Para payloads manuais (login, logout, etc.)
return new JsonResponse(['data' => $payload]);
```

**Tipos de retorno recomendados:**
- `UserResource::collection()` → `AnonymousResourceCollection`
- `UserResource::make()` → `UserResource`
- `JsonResponse` manual → `JsonResponse`
- `Resource::make()->toResponse()->setStatusCode(201)` → `JsonResponse`

### 1.3 FormRequests para Filtros (Obrigatório em Listagens)
Todo endpoint de listagem (`index()`) deve usar uma classe FormRequest para validar os filtros de query string.

```php
// app/Http/Requests/Core/Users/ListUsersRequest.php
class ListUsersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:admin,instructor,student'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    // Método obrigatório para documentação Scribe
    public function queryParameters(): array
    {
        return [
            'search' => [
                'description' => 'Filtrar por nome ou email',
                'example' => 'john',
            ],
            'role' => [
                'description' => 'Filtrar por role',
                'example' => 'student',
            ],
        ];
    }
}

// Controller
public function index(ListUsersRequest $request, ApiContext $context): JsonResponse
{
    $filters = $request->validated();
    // ...
}
```

### 1.4 Guardrails Obrigatórios (não repetir anti-patterns)
- Controllers devem injetar `ApiContext` como parâmetro de método, NUNCA acessar tenant/user via request manualmente.
- `tenant_not_resolved` deve ser emitido por exceção de domínio (`TenantContextRequiredException`) com render centralizado.
- Controllers devem somente orquestrar: `ApiContext` + `Gate/Policy` + Action + Resource.
- Decisão condicional de autorização (ex.: `is_system`) deve ficar em `Policy`, não em `if` de controller.
- Não usar `->resolve()` em Resources - usar `->toResponse(request())`.
- Não retornar `response(Resource::collection(...)->response()->getData(true))` - usar `->toResponse(request())` diretamente.
- `meta` vazio não deve ser retornado (`'meta' => []` proibido). Só incluir `meta` quando houver conteúdo real.

## 2. Multi-Tenancy Resolution
O sistema suportará a resolução do tenant de duas formas, nessa ordem de prioridade (Middleware customizado):
1. **Header HTTP:** `X-Tenant-ID` (ou `X-Tenant-Domain`). Padrão usado nos Frontends (Mobile Apps / SPA Principal).
2. **Identificação por Domínio da Requisição:** O Middleware extrai o host e busca na tabela de Tenants (Padrão para White-labels finais e configuração DNS customizada).
_Nota técnica_: Utilizaremos `spatie/laravel-multitenancy` nos mesmos moldes atuais, atrelando a Connection/Database context dependendo do escopo ou escopando no banco único via `tenant_id`.

## 3. Identidade e Entidades Principais
### Entidade: `User`
- **Tabela:** `users` (tenant-aware / tenant_id nulo no caso de developer/landlord).
- **Atributos Principais:** id, name, email, password, tenant_id, headline, bio, avatar, cpf, linkedin, twitter, etc.
- **Roles & Permissions:** Usa `spatie/laravel-permission`. Tipos básicos: `developer` (Master/Landlord), `tenant_admin`, `instructor`, `student`.

### Entidade: `Tenant`
- **Tabela:** `tenants` (tabela landlord).
- **Atributos Principais:** id, name, slug, domain, database (se houver isolamento físico), is_active, data (json config).
- Suporta relacionamentos para personalizações e integrações visuais do Tenant (`TenantCustomization`, `TenantIntegration`).

### Entidade: `TenantCustomization`
- **Tabela:** `tenant_customizations`
- **Propósito:** Configurações visuais e de brand do tenant (white-label).
- **Atributos:** tenant_id, logo, banner, primary_color, secondary_color, custom_css, terms_url, privacy_url, support_email.
- **Uso:** Retornado em `GET /tenant/config` para renderização do frontend antes do login.

### Entidade: `TenantIntegration`
- **Tabela:** `tenant_integrations`
- **Propósito:** Credenciais de integrações externas por tenant (gateways, analytics, etc).
- **Atributos:** tenant_id, integration_type, credentials (encrypted json), is_active.
- **Segurança:** Credenciais sempre cifradas via Eloquent Cast (`encrypted:json`).

### Entidade: `SystemSetting`
- **Tabela:** `system_settings`
- **Propósito:** Configurações globais da plataforma (landlord only).
- **Atributos:** key, value, tenant_id (null para globais).
- **Acesso:** Apenas `developer` pode ler/escrever configurações globais.

## 4. Endpoints (JSON)
*Base URL: `api/v1/core`*

### Autenticação & Sessão (`/auth`)
- `POST /auth/login`: Autentica usuário via e-mail e senha. Retorna `token` do Sanctum. Requer contexto do tenant (Header ou Host).
- `POST /auth/logout`: Invalida token atual do Sanctum.
- `GET /auth/me`: Retorna o DTO da entidade do usuário autenticado + suas Roles e Permissões atreladas ao Tenant atual.

### Usuários e Perfis (`/users`)
- `POST /users`: Criação de usuário (Registro) e atrela a `student` (ou cria pelo Admin).
- `PATCH /users/me`: Atualização de dados cadastrais do próprio perfil (Nome, Bio, Avatar, CPF).
- `PATCH /users/me/password`: Atualização de senha.
- `GET /users`: Listagem de usuários (Apenas para `tenant_admin`). Respeita scope do Tenant. Regras de paginação no JSON `meta`.
- `GET /users/{id}`: Detalhe do usuário.

### Gestão de Tenant (`/tenant`)
_Rotas focadas na customização e configurações do ambiente atual._
- `GET /tenant/config`: Retorna definições públicas do tenant resolvido (Nome, Marca, Cores baseadas no `TenantCustomization`). Não requer autenticação. Útil para o frontend renderizar o "visual" (White-label) na tela de login.
- `PATCH /tenant/config`: Edita personalizações (apenas `tenant_admin`).

## 5. Regras de Negócio e Casos de Uso
- **Isolamento Total:** Um `student` registrado no Tenant A não deve existir, a princípio, no Tenant B, a menos que as instâncias compartilhem o mesmo pool de usuários base. O campo `tenant_id` será a âncora de isolamento.
- **Impersonação Segura:** A API permitirá a emissão de tokens especiais (Impersonate Token) para os `developers` analisarem os `tenants`, ou para o `tenant_admin` analisar `students`, usando Sanctum Abilities (`impersonating`).
- **White-Label Inicialização:** A SPA chamará `GET /tenant/config` via host header para carregar a interface *antes* do login, carregando logo, brand colors e links de termos de uso específicos do tenant.
