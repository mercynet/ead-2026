# MZRT — Gap Analysis — 2026-09-05

## 1. Executive Summary

**Verdict:** `MZRT_COMPLETE_WITH_EVIDENCE_PENDING`

O repositório já contém um walking skeleton MZRT coerente com o contrato de
`FOUNDATION-0` + `MZRT-SKELETON`: um `developer` autentica, cria um tenant com o primeiro
admin, recebe o preset gratuito `cash`, consulta os entitlements e pode suspender/reativar o
tenant. A rota MZRT não exige contexto nem header de tenant e a resposta do entitlement não
expõe configuração ou credenciais.

A implementação é sustentada por código, rotas, migrations e testes Feature existentes. A
declaração de completude ainda não pode ser promovida a `MZRT_COMPLETE` porque a jornada HTTP
com banco dedicado não foi reexecutada nesta auditoria e a geração Scribe não terminou por
permissão do cache ignorado `.scribe/endpoints.cache`, conforme o relatório WS1. Essas lacunas
são de evidência/contrato operacional, não indicação de que o skeleton precise de novas
features de produto.

Marketplace, assinaturas, billing SaaS, `PlatformOrder*`, quotas e expansão de plugins são a
jornada futura `MZRT-PLATFORM`, explicitamente não pré-requisito para fechar o skeleton inicial.

## 2. MZRT Boundary

### Definição operacional

MZRT é a superfície global da equipe Mozart, para `UserType::Developer`, sem tenant resolvido,
com responsabilidade pelo control plane mínimo de tenants e pelo catálogo/entitlement global.
O guard de superfície é `area.guard:mzrt`; RBAC continua sendo a autorização da ação. A base
canônica é `docs/specs/00-architecture/areas-surfaces.md:46-48,64-76`, reforçada por
`docs/specs/00-architecture/decisions/006-planejamento-por-jornadas-de-area.md:29-36`.

O limite adotado nesta análise é:

| Classificação | Capacidades incluídas ou excluídas |
|---|---|
| `MZRT_CORE` | Login/entrada de developer; provisionar tenant + primeiro admin; suspender/reativar tenant; ler entitlements de um tenant; efeitos mínimos de onboarding necessários para isso. |
| `MZRT_SUPPORTING` | Categorias globais de sistema; preset `cash`; fundações do catálogo/plugin e gateway da plataforma que suportam a jornada futura; auditoria e observabilidade específicas da operação MZRT. |
| `SHARED_FOUNDATION` | Sanctum, `ApiContext`, `UserType`, teto efetivo de permissions, seeders/RBAC, resolução/isolamento de tenant, envelopes de erro, Scribe e invariantes de rota. São dependências de MZRT, não capacidades MZRT por si só. |
| `OUT_OF_SCOPE_ADMIN` | Convites e usuários do tenant; customização/integrações do tenant; configuração e seleção de gateway do tenant; cursos, conteúdo e operação dentro do tenant. O developer não consome a superfície Admin por herança. |
| `OUT_OF_SCOPE_INSTRUCTOR` | Conteúdo próprio, alunos próprios e avaliação do instrutor. Nenhuma rota Instructor existe hoje; pertence a `INSTRUCTOR-OWN`. |
| `OUT_OF_SCOPE_STUDENT` | Checkout, orders de venda, matrícula, consumo, progresso e certificados próprios. Pertence a `STUDENT-PAID` ou Learning/Assessment do estudante. |
| `UNCLEAR` | Listagem/detalhe global de tenants, CRUD de usuários developer, reporting MZRT, suporte operacional e feature flags não possuem contrato/Task atual inequívoco. Não são convertidos em MUST HAVE por serem comuns em SaaS. Impersonation tem intenção documentada, mas está explicitamente diferida. |

### O que não entra no fechamento inicial

`areas-surfaces.md:69-73` descreve billing Mzrt→tenant e provisionamento de plugins como
responsabilidades da área, mas o contrato operacional posterior delimita o primeiro resultado:
o roadmap chama marketplace, SaaS Mzrt→tenant, `PlatformOrder*`, assinaturas, quotas e plugins
amplos de `MZRT-PLATFORM` (`docs/ROADMAP.md:12-17`; ADR-006:33-36). Portanto, esses itens são
MZRT futuro, não gaps bloqueadores do walking skeleton.

## 3. Current Capability Matrix

`IMPLEMENTED` abaixo significa implementação observável no código atual. `TEST_VERIFIED` se
refere a testes/resultados existentes ou documentados; não significa execução runtime atual.
Nenhuma linha é marcada `RUNTIME_VERIFIED` nesta auditoria.

| Capability | Módulo | Rotas | Controller / Action / Service | Model / migration | Autorização | Testes existentes | E2E existente | Spec / task | Estado | Evidência | Gap restante |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Entrada autenticada de developer na superfície MZRT | Core / Shared | `POST /api/v1/auth/login` e compatibilidade `POST /api/v1/core/auth/login`; ações MZRT usam `POST/PATCH /api/v1/mzrt/*` | `AuthController` → `LoginAction`; `EnsureAreaAccess` | `User`, `Tenant`; migrations de users/tenants e Sanctum | Login público com throttle `login`; MZRT exige `auth:sanctum` + `area.guard:mzrt`; somente `developer` | `AuthApiTest`, `PasswordResetApiTest`, `AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `PermissionCeilingTest` | O E2E MZRT usa a URL legacy de auth; a compatibilidade é intencional, mas não prova a URL canônica atual | Core auth spec/tasks; WS1; `areas-surfaces.md:179-198` | IMPLEMENTED | TEST_VERIFIED | Reexecutar a jornada usando também `/api/v1/auth/*` no runtime dedicado. |
| Provisionamento de tenant e primeiro admin | Core | `POST /api/v1/mzrt/tenants` | `MzrtTenantCreateController` → `ProvisionTenantAction` → `TenantProvisioningParticipant` | `Tenant`, `User`, roles/permissions e artifacts Ecosystem; `create_tenants_table`, user/RBAC migrations e participant migrations | `core.tenants.create`; developer; sem tenant/header; guard de área antes de binding/validação | `tests/Feature/Api/Core/Mzrt/TenantCreateApiTest.php`; `TenantProvisionCommandTest`; Architecture relacionada | `tests/e2e-http/mzrt/tenant-lifecycle.php:29-70` | Core tasks `MZRT-SKELETON-CREATE`; tenant-config spec; `ROADMAP.md:12` | IMPLEMENTED | TEST_VERIFIED | Nenhum gap funcional confirmado; falta prova HTTP/DB atual. |
| Preset de onboarding `cash` e entitlement inicial | Ecosystem / Financial | Sem rota MZRT própria; efeito transacional do POST de tenant | `EcosystemDefaultGatewayProvisioner`; consumido via `TenantProvisioningParticipant` | `Plugin`, `PluginActivation`, `TenantPluginConfig`; migrations Ecosystem | Não é uma ação independente do cliente; provisioning global do developer; config cifrada e hidden | `TenantCreateApiTest`, `PluginCatalogTest`, `PluginActivationTest`, `TenantPluginConfigTest`, `TenantPluginConfigRevisionTest`, testes de resolvers Financial | Mesmo E2E MZRT verifica ativação `gateway.cash` e config enabled | Ecosystem tasks `MZRT-SKELETON-CASH`; ADR-005; Financial tasks | IMPLEMENTED | TEST_VERIFIED | Falta apenas reprodução atual e confirmação de limpeza no DB E2E. |
| Consulta global de entitlements por tenant | Ecosystem | `GET /api/v1/mzrt/tenants/{tenant}/entitlements` | `TenantEntitlementController` → `ListTenantEntitlementsAction` | `PluginActivation` + `Plugin`; `plugin_activations`, `plugins` | `ecosystem.entitlements.list`; developer; `auth:sanctum` + `area.guard:mzrt`; sem contexto/header | `TenantEntitlementApiTest` cobre auth, área, permission, 404, cursor e ausência de secrets | `tenant-lifecycle.php:72-89` verifica `gateway.cash`, status e omissão de config/credentials | Ecosystem spec `50-ecosystem-plugins/spec.md:50-53,81`; task `MZRT-SKELETON-ENTITLEMENTS` | IMPLEMENTED | TEST_VERIFIED | Falta prova HTTP/DB atual; não há quotas, por contrato. |
| Suspensão/reativação e efeito de acesso | Core | `PATCH /api/v1/mzrt/tenants/{tenant}/status` | `MzrtTenantStatusController` → `UpdateTenantStatusAction` | `Tenant.is_active`; `create_tenants_table`; activity log | `core.tenants.update-status`; developer; MZRT global; status `active|suspended`; transição idempotente | `TenantStatusApiTest` cobre 403/404/422, idempotência, audit log, login/token bloqueados, tenant vizinho preservado e reativação | `tenant-lifecycle.php:105-173` cobre suspend, login/token negados, reactivate e acesso restaurado | Core spec `spec.md:73-82`; tenant-config `:50-67`; task `MZRT-SKELETON-STATUS` | IMPLEMENTED | TEST_VERIFIED | Falta execução atual contra app + DB; a auditoria de status tem teste, mas não há receipt runtime atual. |
| Gestão de categorias globais de sistema | Learning, na borda MZRT | `POST/PUT/DELETE /api/v1/mzrt/categories` | `Learning\Http\Controllers\Mzrt\CategoryController` → ações Catalog | `Category`; migrations de categories e pivôs | `learning.categories.system.manage`; developer; `is_system` é derivado da área e não aceito no payload | `CategoryApiTest`, `CategoryAuthorizationTest`; testes de envelope/área; E2E de categorias inclui casos MZRT | `tests/e2e-http/learning/admin-categories.php:78-107` contém update/404/área; não é parte da jornada MZRT skeleton | Learning tasks: re-slot system → Mzrt; ADR-002; `areas-surfaces.md:71-74` | IMPLEMENTED | TEST_VERIFIED | Supporting; não bloqueia o skeleton de tenant. |
| Base de catálogo/plugin, activation e config por tenant | Ecosystem | Não há CRUD MZRT de plugin; apenas a leitura de entitlement MZRT. Configuração de gateway existente está em `/api/v1/admin/payment-gateways` e é Admin | `Plugin`, `PluginActivation`, `TenantPluginConfig`; resolvers/provisioner; controller de gateway é Admin | Migrations de `plugins`, activations, configs e revisions | Catalogo futuro é developer; consumo/configuração atual de gateway é `area.guard:admin` + tenant access | `PluginCatalogTest`, `PluginActivationTest`, `TenantPluginConfig*`, `AdminPaymentGatewayApiTest` | Não há E2E dedicado de catálogo/plugin MZRT | Ecosystem spec/tasks; ADR-005 | PARTIAL | TEST_VERIFIED | CRUD/catalogo MZRT e marketplace estão em `MZRT-PLATFORM`, não no MUST inicial. |
| Gateway de pagamento da plataforma | Financial | Não há rota de configuração MZRT | `PlatformGatewayResolver`; `PlatformPaymentGateway::makeDefault()` | `PlatformPaymentGateway`; `platform_payment_gateways` | Base global do Mozart; sem entitlement de tenant | `PlatformGatewayResolutionTest`, unit tests de gateway | Não há E2E; nenhum fluxo de cobrança de plataforma usa o gateway ainda | Financial tasks `:14`; ADR-005:42-53 | IMPLEMENTED (fundação) | TEST_VERIFIED | Supporting de `MZRT-PLATFORM`; ledger/checkout de plataforma ainda não existe. |
| Visão/listagem global de tenants | Core | Nenhuma rota executável; o metadata do E2E declara `GET /api/v1/mzrt/tenants`, mas não há case nem rota correspondente | Nenhum controller/action de listagem encontrado | `Tenant` existe e tem migration | Nenhuma permission de listagem; somente `core.tenants.create` e `core.tenants.update-status` no config | Nenhum teste de endpoint de listagem | Nenhum case efetivo; metadata é inconsistente/stale | Exemplos em `areas-surfaces.md:169`; não há Task Core equivalente | UNCLEAR / NOT_FOUND | STATIC_EVIDENCE_ONLY | Definir se list/show faz parte do próximo control plane; não bloqueia a jornada atual porque o POST devolve o ID. |
| Usuários developer/superadmin | Core | Nenhuma superfície MZRT de CRUD; APIs `/core/users` são legacy/mistas e Admin tem apenas operação tenant de instructor/student | `UserController`/`UserPolicy` existem para superfícies Core; sem controller MZRT developer | `User`, RBAC e seed de developer | `UserType::Developer`, role global seedada; user type só developer pode alterar segundo contrato | `PermissionCeilingTest`, seeder tests, users Feature tests, `UserPolicy` | Nenhum E2E de gestão de developer | RBAC `:49-61`; Core tasks Pending não incluem CRUD developer | UNCLEAR | STATIC_EVIDENCE_ONLY | Não inventar CRUD; decisão de produto necessária se suporte operacional de developer for exigido. |
| Configuração global / feature flags / reporting / suporte | Core / Platform | Nenhuma rota/model de `SystemSetting`, feature flag, reporting ou suporte MZRT encontrado | Nenhum serviço de produto correspondente | `SystemSetting` está apenas na spec; nenhum model/migration | Sem permission/contract atual executável | Nenhum teste de endpoint | Nenhum E2E | `SystemSetting` está Pending em Core tasks; feature flags/reporting/suporte não têm Task canônica | PLANNED / UNCLEAR | DOCUMENTATION_ONLY | Tratar `SystemSetting` como próximo control-plane; os demais só entram após contrato explícito. |
| Marketplace, planos, assinaturas e billing Mzrt→tenant | Ecosystem / Financial | Nenhuma rota `ecosystem/marketplace` ou dashboard global executável | Models/actions de marketplace/subscription não encontrados; só `PlatformPaymentGateway` existe | `PluginVersion`, `PluginPricing`, `PluginFeature`, `PluginPermission`, `PlatformOrder*`, `PluginSubscription` etc. não encontrados | Contrato futuro: developer administra catálogo; tenant admin consome; platform billing global | Nenhum teste de endpoint | Nenhum E2E | `MZRT-PLATFORM`; Ecosystem tasks `:38-58`; Financial spec `:48-55` | PLANNED | DOCUMENTATION_ONLY | LATER; não é pré-requisito de `MZRT-SKELETON`. |
| Impersonation segura | Core / Sanctum | Nenhuma rota/controller encontrado | Nenhuma action encontrada | Nenhum token flow `impersonating` encontrado | Intenção documentada como ability Sanctum explícita e auditada | Nenhum teste de fluxo | Nenhum E2E | Security spec `:14`; Core tasks `:106` | PLANNED | DOCUMENTATION_ONLY | LATER; não usar hierarquia developer como impersonação implícita. |

### Observação sobre Admin gateway

`GET/PUT /api/v1/admin/payment-gateways` é uma capability real e testada de configuração do
gateway **do tenant**, não uma capability MZRT. Está corretamente classificada como
`OUT_OF_SCOPE_ADMIN`: a rota usa `area.guard:admin`, tenant obrigatório e `tenant.access`
(`app/Modules/Ecosystem/Routes/api.php:7-15`). O fato de um developer aparecer no teto global
de permissions não muda a superfície da persona.

## 4. MZRT Journeys

### Jornada mínima que o código suporta

`login developer → provisionar tenant → ler entitlement → suspender → observar negação → reativar → observar restauração`

1. **Entrypoint:** `POST /api/v1/auth/login` com email/senha do developer, sem tenant. A rota
   neutra é a superfície canônica WS1; `/api/v1/core/auth/login` permanece compatibilidade.
2. **Autorização de entrada:** token Sanctum; em toda rota MZRT, `EnsureAreaAccess` aceita
   somente `developer`. A permission continua sendo verificada no controller.
3. **Provisionamento:** `POST /api/v1/mzrt/tenants`, sem `X-Tenant-ID`, com identidade do tenant
   e `admin{name,email,password}`. `core.tenants.create` é autorizado antes da Action.
4. **Side effects/persistência:** uma transação cria `Tenant` ativo, primeiro `User` admin,
   role `admin` e, via contrato `TenantProvisioningParticipant`, o plugin `cash`, seu
   `PluginActivation` ativo e `TenantPluginConfig` enabled. Falha de admin, role ou participante
   deve reverter todos os artefatos. Domínio duplicado retorna `409 tenant_already_exists`.
5. **Resposta:** HTTP 201 com `data.tenant` e `data.admin`; a senha não é retornada. O Resource
   expõe `status`, não `is_active`.
6. **Leitura de resultado:** `GET /api/v1/mzrt/tenants/{id}/entitlements`, sem contexto de
   tenant, usa cursor pagination e retorna somente `capability` e `status`.
7. **Suspensão:** `PATCH .../status` com `suspended` persiste `is_active=false`, retorna
   `data.status`, e o `LogsActivity` do Tenant registra a alteração. Repetir o estado não cria
   novo log.
8. **Efeito de segurança:** tenant suspenso deixa de resolver; novo login tenant-bound falha e
   token tenant-bound não recebe contexto. Outro tenant continua acessível.
9. **Reativação:** `active` restaura resolução, login e uso do token existente; a transição é
   idempotente.

### Erros e rollback relevantes

- domínio duplicado: `409 tenant_already_exists`, sem criar admin nem alterar tenant existente;
- payload inválido: `422 validation_error` pelo renderer central;
- persona errada: `403 area_forbidden` antes de binding/validação;
- developer sem permission: `403 access_denied`;
- tenant inexistente: `404 not_found`;
- falha durante a transação: rollback integral; o código tem testes Feature para admin, role e
  participant failure;
- ausência de runtime atual: não é erro de produto, mas deixa o fechamento operacional em
  `EVIDENCE_PENDING`.

## 5. Definition of MZRT_COMPLETE

O alvo mínimo é fechar `MZRT-SKELETON`, não implementar o backlog inteiro de `MZRT-PLATFORM`.

### MUST HAVE

1. Developer consegue entrar pela superfície canônica de auth e alcançar somente as rotas MZRT;
   admin/instructor/student não atravessam o guard.
2. Provisionamento cria tenant ativo + primeiro admin + role + preset `cash` atomically, sem
   header de tenant, sem senha na resposta e com conflito/rollback corretos.
3. MZRT consegue ler entitlements do tenant, com cursor pagination, sem config/credentials ou
   outros campos sensíveis.
4. MZRT consegue suspender/reativar tenant; a mudança é idempotente, auditada e altera login e
   tokens do tenant alvo sem afetar tenants vizinhos.
5. Os quatro pontos acima têm Feature/Architecture evidence e uma execução E2E HTTP atual em
   banco dedicado `e2e`, incluindo limpeza sem resíduos. A execução precisa cobrir a rota
   canônica de auth, além da compatibilidade legacy quando desejado.
6. Scribe consegue gerar a documentação atual das rotas MZRT e as anotações de auth coincidem
   com o middleware real. O contrato consumível não pode depender do cache `.scribe` bloqueado.

### SHOULD HAVE

- `GET` global de tenants (list/show) com contrato próprio MZRT, caso a equipe confirme que
  “visão de tenants” é requisito operacional do painel;
- `SystemSetting` e endpoints de configuração global, se a plataforma precisar de configuração
  editável fora de deploy;
- auditoria/observabilidade explícita para todas as operações de catálogo/entitlement, além da
  auditoria já provada para mudança de status;
- prova runtime de permissions provenientes de plugins (`hasActivePluginPermission`) e CRUD de
  roles tenant no Core/Ecosystem quando essa superfície for necessária à operação.

### LATER

- marketplace completo e store pages;
- `PluginVersion`, pricing/features/permissions/ratings e CRUD de catálogo developer;
- `PlatformOrder`, `PlatformOrderItem`, `PlatformPayment` e cobrança SaaS;
- subscriptions, billing recorrente, grants, cron de inadimplência, quotas/licenses/usage;
- adapters externos Stripe/Pix/Mercado Pago/PagSeguro/Asaas;
- impersonation explícita, auditada e com ability Sanctum `impersonating`;
- reporting, feature flags ou suporte operacional somente quando houver contrato e prioridade
  aceitos.

## 6. Gaps

Os gaps abaixo são únicos; ausência de um item `LATER` não é contada como deficiência do
skeleton. Severidade/impacto são da área, não uma crítica geral ao projeto.

| ID | Capability afetada | Evidência | Tipo | Impacto | Prioridade | Dependências | Risco se adiado | Esforço | Bloqueia `MZRT_COMPLETE`? |
|---|---|---|---|---|---|---|---|---|---|
| `MZRT-MUST-01` | Jornada skeleton em runtime | Docker não estava disponível para `migrate:status`; nenhum HTTP E2E foi executado nesta auditoria. A spec E2E existe em `tests/e2e-http/mzrt/tenant-lifecycle.php`, mas claims de 9/9 em tasks/reports são históricos. | E2E / TEST | Não há prova atual de side effects, isolamento, rollback, suspensão ou limpeza no DB. | HIGH | `.env.e2e`, DB dedicado com nome contendo `e2e`, servidor e runner | A área pode estar implementada, mas não pode ser declarada operacional com confiança. | Pequeno/médio | SIM |
| `MZRT-MUST-02` | Contrato Scribe das rotas MZRT | WS1 registra que `composer docs` enumerou rotas, mas falhou ao limpar `.scribe/endpoints.cache` por owner `nobody:nogroup`; a geração não foi concluída. | API_CONTRACT / DOCUMENTATION / DEVEX | Consumidores não têm um artefato Scribe final validado; a anotação existe, mas o pipeline documental permanece não confirmado. | HIGH | Corrigir permissões do cache no ambiente; rodar Scribe e conferir auth annotations | Documentação API pode divergir ou ficar não reprodutível no fechamento. | Pequeno | SIM |
| `MZRT-SHOULD-01` | Visão/listagem de tenants | `route:list` atual mostrou POST e PATCH/entitlements, mas nenhum `GET /api/v1/mzrt/tenants`; o campo `endpoint` do E2E é stale e não tem case GET. | PRODUCT / API_CONTRACT | Operador não tem visão global no painel sem conhecer IDs; a jornada atual não depende disso. | MEDIUM | Decisão de contrato MZRT; Core list Action/Resource/test | Operação diária fica menos utilizável e pode levar a endpoints ad-hoc fora da área. | Médio | NÃO |
| `MZRT-SHOULD-02` | Configuração global da plataforma | `SystemSetting` aparece na spec e Core tasks Pending, mas não há model, migration, controller ou rota. | PRODUCT / DATA_MODEL | Configurações globais exigem deploy/config manual; não afeta create/status/entitlement atual. | MEDIUM | Core model/schema, permission developer, Resource e testes | Configuração pode se espalhar por env/config sem contrato. | Médio | NÃO |
| `MZRT-SHOULD-03` | Auditoria/observabilidade de operações MZRT | `Tenant` audita `is_active` e `TenantStatusApiTest` prova esse log; `Plugin`, activation e entitlement read não têm trilha MZRT explícita observada. | SECURITY | Investigação de quem publicou/ativou/consultou capabilities fica incompleta quando o control plane crescer. | MEDIUM | Definir eventos/audit fields sem registrar secrets/PII; `PiiAuditTest`/logging policy | Menor accountability e diagnóstico operacional; não invalida o log de status existente. | Médio | NÃO |
| `MZRT-SHOULD-04` | Permissions de plugin em runtime e roles de tenant | Core tasks mantém `hasActivePluginPermission` e CRUD de roles em Pending; o teto canônico de UserType já existe. | AUTHORIZATION / TEST | Uma capability plugin pode ter modelo/entitlement sem prova completa de autorização efetiva no consumo. | MEDIUM | Contrato Ecosystem, cache/invalidation e testes de permission | Risco de feature gated inconsistente quando plugins reais forem ativados; não é usado pelo skeleton além da leitura. | Médio | NÃO |
| `MZRT-LATER-01` | Marketplace/catalogo developer | Tasks Ecosystem `:38-46`; ausência dos models/version/pricing/rating e rotas marketplace/admin. | PRODUCT / API_CONTRACT / DATA_MODEL | Não há descoberta/curadoria/compra de plugins. | LOW | `MZRT-PLATFORM`, catálogo, Resource e E2E | Plataforma não pode vender capabilities opcionais. | Grande | NÃO |
| `MZRT-LATER-02` | Billing SaaS e ledger plataforma | Tasks Ecosystem `:50-56`; `PlatformOrder*` não encontrado, embora `PlatformPaymentGateway` foundation exista. | PRODUCT / DATA_MODEL / FINANCIAL | Não há cobrança Mzrt→tenant nem assinaturas pagas. | LOW | Marketplace, pricing, PlatformOrder ledger, webhook/outbox | Não monetiza planos/plugins; não afeta preset free. | Grande | NÃO |
| `MZRT-LATER-03` | Subscriptions, grants e suspensão por inadimplência | Models/actions `PluginSubscription`, `PluginBilling`, `PluginGrant`, cron e instalação não encontrados. | PRODUCT / FINANCIAL / SECURITY | Entitlements pagos/recorrentes não têm ciclo de vida. | LOW | Platform ledger, jobs/cron, idempotência, policy | Estado de capability paga pode ficar manual/inconsistente. | Grande | NÃO |
| `MZRT-LATER-04` | Quotas, licenses, usage e rate tier | Documentados como entidades/tasks futuras; nenhum model/endpoint executável encontrado. | PRODUCT / DATA_MODEL | Não há enforcement de limites por plano. | LOW | Pricing/subscription e modelo de uso | Tenants não terão limites comerciais; fora do skeleton explicitamente. | Grande | NÃO |
| `MZRT-LATER-05` | Adapters externos first-party | Financial tasks listam Stripe, Mercado Pago, PagSeguro, PIX e Asaas como Pending; `cash` é o único preset observado. | PRODUCT / FINANCIAL | Não há cobrança automática de plataforma/plugin com PSP externo. | LOW | Platform ledger, gateway contracts, webhook/E2E | Limita opções de cobrança, sem impacto no onboarding `cash`. | Grande | NÃO |
| `MZRT-LATER-06` | Impersonation auditada | Security spec e Core tasks registram ability futura; ausência de flow/controller/teste. | SECURITY / AUTHORIZATION | Suporte não pode atuar como outra persona de forma explícita; não se deve simular isso com herança developer. | LOW | Decisão de segurança, token abilities, audit trail e E2E | Operações de suporte exigem login separado; é comportamento seguro enquanto diferido. | Médio/grande | NÃO |

### Finding de segurança verificado e não convertido em gap

Não foi confirmado IDOR no endpoint de entitlements: o parâmetro de rota é aceito apenas após
guard MZRT/permission developer, e a Action filtra `PluginActivation` por `tenant_id` do tenant
alvo (`app/Modules/Ecosystem/Actions/Mzrt/ListTenantEntitlementsAction.php:12-18`). Não foi
confirmado vazamento de secret: o Resource retorna somente `capability` e `status`, e a config
usa `encrypted:array` + `$hidden`. A conclusão semântica completa ainda depende da execução
runtime pendente.

## 7. Harness Readiness for MZRT

### Avaliação

O Codex consegue inspecionar e modificar MZRT com roteamento de skills e o repositório possui
invariantes de área, superfície pública, permission drift, tenant scoping, PII e controller
leanness. `verify-changes.sh` mapeia rotas/middleware para `AreaRouteGuardTest`,
`RouteSecuritySurfaceTest` e Scribe auth; models/migrations também entram nos scans de tenant,
PII e fronteira.

Há, contudo, três limitações relevantes:

- `.codex/hooks.json:2-22` só registra Graphify e router em `PreToolUse`; não instala o guard
  destrutivo/path, Pint pós-edição, SessionStart, PreCompact ou Stop de Claude;
- `verify-changes.sh:46-93` seleciona Architecture tests, mas não seleciona automaticamente os
  Feature MZRT de create/status/entitlements;
- M-06 registra que os scripts/hook têm pouca prova comportamental discriminante, e os testes de
  Architecture usam heurísticas/allowlists em partes da superfície.

### Classificação por gap do harness

| Gap | Classificação | Justificativa para MZRT |
|---|---|---|
| M-05 — enforcement Codex menor que Claude | `SHOULD_FIX_DURING_MZRT` | É uma proteção importante para futuras alterações de rotas, RBAC e migrations MZRT. Não bloqueia esta auditoria nem a execução manual dos critérios, desde que o agente rode os Feature tests e o E2E explicitamente. |
| Seleção automática não inclui Feature MZRT | `SHOULD_FIX_DURING_MZRT` | Um fechamento da jornada não deve depender apenas dos invariantes estruturais; a seleção automática atual pode deixar create/status/entitlement sem execução. |
| M-06 — ausência de probes comportamentais dos scripts | `CAN_WAIT_FOR_WS2` | Melhora confiança do harness global, mas não há evidência de que impeça o skeleton se os checks MZRT forem executados manualmente e registrados. |
| M-04 — dependency audit advisory na CI | `CAN_WAIT_FOR_WS2` | Supply chain é relevante, mas não é um bloqueio específico da jornada MZRT atual. |
| M-07/M-09 — legacy/boundary debt | `CAN_WAIT_FOR_WS2` | Rotas MZRT novas já estão área-first; a dívida legacy/Eloquent não precisa ser aberta para fechar o skeleton. |

**Gaps `BLOCKS_MZRT`: 0.** M-05 e M-06 não bloqueiam o fechamento por si; o bloqueio atual é a
ausência de evidência runtime/Scribe, classificada em `MZRT-MUST-01/02`, não um workstream
genérico de harness.

## 8. Cross-cutting Dependencies

| Dependência | Por que é necessária | Escopo mínimo | Pode ser resolvida sem iniciar a próxima área? |
|---|---|---|---|
| Core Auth + Sanctum + `ApiContext` | Developer precisa autenticar sem tenant e cada controller precisa do usuário/contexto. | Manter `/api/v1/auth/*`, compatibilidade legacy, throttle e renderer; validar login/me/logout. | Sim; é Shared Foundation/Core, não Admin. |
| `Area::Mzrt` + `EnsureAreaAccess` | Impede Admin/Instructor/Student de consumir payload global e antecede binding 404. | Exigir exatamente `area.guard:mzrt` nas seis rotas atuais e conservar prioridade antes de bindings. | Sim. |
| Config/seeder/teto de RBAC | `core.tenants.*`, `ecosystem.entitlements.list` e `learning.categories.system.manage` precisam existir e ser elegíveis só para developer. | `config/permissions.php`, seed derivado e Permission/Area tests. | Sim. |
| Tenant resolution/isolation | MZRT precisa não herdar um tenant do header e, ao mesmo tempo, consultar somente o tenant explicitamente indicado no entitlement. | Rotas MZRT sem middleware tenant; Action com `where('tenant_id')`; prova de tenant vizinho e status. | Sim. |
| Ecosystem provisioning contract | Core cria tenant sem importar internals Ecosystem, mas precisa do participante para `cash`. | `TenantProvisioningParticipant` síncrono dentro da transação, activation/config e rollback. | Sim; não inicia Admin marketplace. |
| Learning system categories | A categoria global é supporting MZRT e o dono técnico é Learning. | Manter a borda `Learning/Routes/mzrt.php`, permission e isolamento system-vs-tenant. | Sim; não inicia jornada Admin/Instructor. |
| Activitylog/LGPD | Status é operação sensível; secrets e PII não podem sair em Resource/log. | Confirmar audit log de status, Resource redacted e invariantes PII; não criar catálogo amplo. | Sim. |
| Scribe + ambiente E2E dedicado | É parte do DoD API-first/jornada e o único caminho para transformar claims em evidência atual. | Corrigir cache `.scribe`, rodar docs e `e2e:run mzrt/tenant-lifecycle` em DB descartável. | Sim; é validação transversal. |

Financial `PlatformPaymentGateway` é dependência somente da futura `MZRT-PLATFORM`; não precisa
ser expandido para fechar `MZRT-SKELETON`.

## 9. Validation Evidence

### Checks executados nesta auditoria

| Comando | Resultado | O que prova / limite |
|---|---|---|
| `graphify query "MZRT area capabilities routes tenants provisioning plugins RBAC platform operations" --budget 2500` | PASS | Localizou relações entre MZRT, Core, Ecosystem, Financial, rotas, Actions, entitlements e testes; é navegação estrutural, não prova de comportamento. |
| `./vendor/bin/sail artisan route:list --path=api/v1/mzrt --json` | PASS | Runtime/boot atual enumerou exatamente seis rotas MZRT: categories POST/PUT/DELETE, tenants POST, entitlements GET e status PATCH. Todas carregam `auth:sanctum`, `area.guard:mzrt` e `api.context`. Não há `GET /mzrt/tenants`. |
| `./vendor/bin/sail artisan migrate:status --no-interaction` | NÃO EXECUTADO | O wrapper respondeu `Docker is not running`; não houve leitura do DB. |
| `docker ps` | BLOQUEADO | `permission denied` no socket Docker. |
| `git diff --check` | PASS (`exit=0`) | Não encontrou whitespace error no working tree pré-existente; não é teste de produto. |
| Pest/Architecture/Feature | NÃO EXECUTADO | Testes Feature/Architecture usam banco e podem alterar artefatos; a instrução desta auditoria proibiu executar checks que mutem estado. |
| E2E HTTP MZRT | NÃO EXECUTADO | O runner cria/suspende/reativa fixtures e limpa o DB; além do Docker indisponível, não é read-only. |
| Scribe | NÃO EXECUTADO | WS1 já documenta falha de geração ao limpar cache ignorado com owner `nobody:nogroup`; não repetir nem alterar o cache. |

### Evidência disponível, sem promoção indevida

- `TenantCreateApiTest`, `TenantStatusApiTest` e `TenantEntitlementApiTest` contêm asserts de
  resposta, autorização, persistência, rollback, cursor, redaction, suspensão e reativação.
- `tests/e2e-http/mzrt/tenant-lifecycle.php` contém nove casos declarativos para create,
  entitlement, login, suspend, negação e reactivate, com asserts de DB e cleanup. O seu conteúdo
  é evidência de existência de E2E, não de execução atual.
- `docs/specs/10-core-identity/tasks.md:72-76` e o relatório WS1 registram resultados históricos;
  esses claims não foram usados como `RUNTIME_VERIFIED`.
- A rota listagem atual foi conferida pelo boot da aplicação, mas isso não confirma status HTTP,
  banco, envelopes ou side effects.

## 10. Estimated Completion

**MZRT estimated completion: 90% (confidence: medium).**

Racional para o número:

1. O escopo medido é o skeleton, com pesos por jornada: auth/guard/RBAC 15%; provisionamento e
   rollback 30%; ciclo de status e efeito de acesso 25%; entitlement read/redaction 15%;
   contrato/auditoria mínima 15%.
2. A inspeção de código/rotas/testes cobre os cinco blocos de capacidade: 100% de implementação
   observável do skeleton.
3. O desconto de 10 pontos representa a falta de duas provas obrigatórias de fechamento: runtime
   E2E/DB atual e Scribe final. A inspeção de rota reduz a incerteza estrutural, mas não substitui
   essas provas.
4. `MZRT-PLATFORM` foi deliberadamente excluído do denominador; incluir marketplace e billing
   futuro faria a métrica medir backlog roadmap, não completude da jornada escolhida.

Os 10% restantes são quase inteiramente `MZRT-MUST-01` e `MZRT-MUST-02`, não ausência de
controller/model do walking skeleton. Se a equipe chamar “completo” somente após execução
runtime, o status deve permanecer `MZRT_COMPLETE_WITH_EVIDENCE_PENDING` até esses dois itens
serem selados.

## 11. Minimal Completion Plan

Não implementar nesta auditoria. A sequência mínima proposta é:

| Slice | Resultado verificável | Testes necessários | Runtime/E2E | Dependências | Modelo |
|---|---|---|---|---|---|
| `MZRT-CLOSE-01` — receipt de rotas/contrato | Conferir as seis rotas MZRT, middleware exato, ausência de `GET /tenants` não contratado e Scribe annotations; resolver o cache bloqueado no ambiente | `AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest`, focused route list | Não precisa DB; Scribe precisa ambiente permitido | WS1 cache ownership; nenhuma área seguinte | `CHEAP/MECHANICAL` com revisão Premium somente se houver mudança de contrato |
| `MZRT-CLOSE-02` — Feature evidence atual | Executar create/status/entitlement Feature e Architecture focados em banco de teste, sem usar claims históricos | `TenantCreateApiTest`, `TenantStatusApiTest`, `TenantEntitlementApiTest`, permission/tenant/Pii related | Não é E2E, mas usa DB de teste; registrar resultado atual | Docker/DB testing | `CHEAP/MECHANICAL` |
| `MZRT-CLOSE-03` — E2E skeleton canônico | Rodar `e2e:run mzrt/tenant-lifecycle` em DB descartável, preferindo auth canônica `/api/v1/auth/*`; confirmar 9 casos, side effects e zero resíduos | Runner assertions + DB assertions da spec | **Obrigatório**; HTTP real e DB `e2e` | `.env.e2e`, servidor, Docker, Scribe/route contract | `LUNA_HIGH` |
| `MZRT-CLOSE-04` — revisão de closure | Comparar receipts com MUST HAVE, confirmar working tree/HEAD e registrar verdict final; não puxar MZRT-PLATFORM | `git diff --check` e seleção de invariantes do diff | Revisão dos receipts, sem nova mutação | Slices 01–03 | `PREMIUM_REVIEW_ONLY` |

São quatro slices de fechamento. Nenhum inicia Admin, Instructor, Student ou Home; o único uso de
Learning/Ecosystem permanece como dependência do recurso já existente.

## 12. Deferred Work

Permanece visível, mas não deve ser usado para bloquear o skeleton:

- `MZRT-SHOULD-01` a `MZRT-SHOULD-04` — próximo endurecimento/ergonomia do control plane;
- `MZRT-LATER-01` a `MZRT-LATER-06` — jornada `MZRT-PLATFORM` e capacidades comerciais/operacionais;
- Admin gateway tenant, usuários tenant, convites e customização — pertencem à área Admin ou
  superfície neutra/tenant conforme o roadmap;
- Instructor, Student e Home — jornadas próprias, explicitamente fora desta análise.

Não há razão para abrir um workstream genérico de “MZRT perfeito” antes de validar os MUST HAVE.

## 13. Unknowns / Evidence Pending

- O MySQL atual não foi consultado; não há confirmação do estado real das migrations/tabelas,
  incluindo o banco dedicado E2E.
- Nenhum status HTTP, envelope, side effect ou rollback foi observado em servidor vivo nesta
  rodada.
- A rota listagem de tenants é uma decisão pendente/unclear: há exemplo/metadata, mas não há
  contrato Task nem rota executável.
- Não existe contrato atual para reporting, suporte operacional ou feature flags; não foram
  tratados como gaps funcionais obrigatórios.
- A geração Scribe atual continua pendente por permissão preexistente no cache ignorado.
- A completa cobertura de audit trail para operações futuras de catálogo/entitlement não foi
  demonstrada; status `Tenant.is_active` possui evidência de teste.
- Codex não possui hoje o conjunto de hooks Claude; isso é gap de harness classificado como
  `SHOULD_FIX_DURING_MZRT`, não `BLOCKS_MZRT`.

## 14. Verdict

`MZRT_COMPLETE_WITH_EVIDENCE_PENDING`

O MZRT skeleton está implementado e estruturalmente utilizável, mas a área ainda não deve ser
rotulada `MZRT_COMPLETE` sem o receipt E2E/runtime atual e a geração Scribe concluída. Marketplace,
billing SaaS e plugins comerciais não mudam este verdict porque pertencem à jornada futura
`MZRT-PLATFORM`, fora do critério mínimo de parada adotado.

Este relatório foi criado em modo read-only. O working tree já estava sujo antes da auditoria,
com alterações em `AGENTS.md`, `docs/STATE.md`, `docs/ROADMAP.md`, código e testes de outros
domínios; essas alterações foram preservadas e não foram usadas como autorização para editá-las.
