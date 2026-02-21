# Domain Spec: Core & Identity (API-First)

## Visão Geral
Este domínio é responsável pela Autenticação, Identidade do Usuário, Controle de Acesso (RBAC) e Multi-Tenancy. Ele serve como o alicerce fundamental, garantindo que usuários sejam devidamente identificados, autorizados e roteados para o inquilino (Tenant) correto.

## 1. Padrões Arquiteturais e API RESTful Estrito
- **Autenticação:** Tokens opacos gerenciados pelo `Laravel Sanctum`.
- **Formato da API:** JSON padrão focado no padrão REST (endpoints pragmáticos e semânticos). Todas as respostas seguirão envelopamento previsível (data/meta).
- **Controladores / Handlers:** Serão usados "Action classes" (Single Action Controllers) para isolar a responsabilidade de requisições. 
- **Validação & Transferência:** `FormRequests` do Laravel para validação estrita, mapeados para `DTOs` (Data Transfer Objects) tipados antes de tocarem os Actions/Services.
- **Tratamento de Exceções:** Formato unificado de erros de API.

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
- **Atributos Principais:** id, name, domain, database (se houver isolamento físico), is_active.
- Suporta relacionamentos para personalizações e integrações visuais do Tenant (`TenantCustomization`, `TenantIntegration`).

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
