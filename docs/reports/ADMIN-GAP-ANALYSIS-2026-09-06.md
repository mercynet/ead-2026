# ADMIN — Gap Analysis — 2026-09-06

Auditoria read-only da área Admin do Sistema de EAD. O contrato do repositório e o código atual
foram confrontados; quando divergem, o código executável é o estado atual. O encerramento MZRT foi
usado somente como contexto de método e não como escopo funcional.

## 1. Executive Summary

**Verdict atual: `ADMIN_PARTIAL`.**

Admin não está no início: há autenticação neutra, contexto de tenant, RBAC, políticas, Actions,
Resources e uma superfície area-first funcional para atualização/exclusão de usuários, categorias,
consulta/publicação de cursos, confirmação manual e configuração de gateway. O problema central é
de convergência e prova: a maior parte do fluxo operacional de usuários, cursos, conteúdo e
matrículas ainda está em `/api/v1/core/*` ou `/api/v1/learning/*`, sem `area.guard:admin`; esses
prefixos legado não são a superfície canônica para produto novo.

A implementação de Assessment Admin é parcial, configuração do tenant e roles customizadas ainda
estão pendentes, e não há reporting/dashboard ou API de auditoria administrativa prevista com
contrato suficiente para entrar no mínimo. Os testes Feature e os cenários HTTP encontrados são
evidência histórica de cobertura escrita, não validação desta auditoria.

Estimativa baseada nas jornadas mínimas: **55–65%**, confiança **média**. O restante é dominado por
rotas Admin area-first ausentes, uma jornada HTTP Admin ponta a ponta não executada e lacunas
concretas de control-plane.

## 2. Admin Boundary

A classificação é funcional/persona-oriented; a mera possibilidade técnica de um usuário Admin
chamar uma rota não torna a capability parte da área Admin.

| Classificação | Boundary funcional | Justificativa |
|---|---|---|
| `ADMIN_CORE` | Operar o próprio tenant: usuários student/instructor, convites, cursos, módulos, aulas, materiais/media, categorias, publicação e matrículas administrativas. | É o control-plane descrito em `docs/ROADMAP.md` (`ADMIN-OPS`) e em `areas-surfaces.md`. |
| `ADMIN_SUPPORTING` | Login/contexto, RBAC efetivo, configuração de gateway, confirmação manual de pagamento, efeitos de auditoria, emissão automática de certificado e consulta administrativa de progresso. | Sustenta a operação, mas parte é neutra, side effect ou não é necessária ao MVP de operação. |
| `SHARED_FOUNDATION` | Sanctum, `ApiContext`, resolução/isolation de tenant, `EnsureAreaAccess`, envelope JSON, Resources, Actions, policies, permission ceiling, Scribe, testes Architecture e runner E2E. | São costuras comuns; não abrem uma nova persona. |
| `OUT_OF_SCOPE_MZRT` | Provisionar/suspender tenant, categorias de sistema, catálogo de plugins, entitlements e ledger plataforma. | São operações globais do developer/MZRT; Admin apenas consome a capacidade liberada. |
| `OUT_OF_SCOPE_INSTRUCTOR` | Ser owner/autor do próprio conteúdo, operar progresso próprio e matrícula manual no papel de instructor. | A especificação separa ownership de Instructor do controle administrativo do tenant. |
| `OUT_OF_SCOPE_STUDENT` | Checkout, pedidos próprios, tentativas de questionário, respostas/finalização, progresso próprio, ratings e consumo pago. | São jornadas de consumo/compra do Student; não são critérios para Admin operar o tenant. |
| `UNCLEAR` | Reporting, dashboard/summary adicional, suporte operacional e marketplace/entitlement visível ao Admin. | Há menções conceituais, mas não há contrato funcional executável que permita exigir a capability. |

## 3. Capability Matrix

Na coluna de evidência, `STATIC_EVIDENCE_ONLY` significa que o estado foi confirmado por código,
rotas, specs e testes existentes, sem promover resultados históricos a runtime atual. “Testes” lista
artefatos presentes; “E2E” lista specs HTTP existentes, ainda não executadas nesta auditoria.

| Capability | Módulo; rota(s) atuais | Controller → Action/service | Model/migration; auth e tenant scope | Testes / E2E existentes | Spec/task; estado; evidência; gap |
|---|---|---|---|---|---|
| Login, sessão e acesso Admin | Core; canônico `/api/v1/auth/login`, `/auth/me`, `/auth/logout`; compatibilidade `/api/v1/core/auth/*` (`Core/Routes/api.php:15-41`) | `AuthController` → login/logout/me Actions | `User`, Sanctum tokens, tenants; login resolve contexto e operações autenticadas exigem auth + tenant access | `AuthApiTest.php`; admin-users usa `/core/auth/me`; sem E2E de login Admin | `auth.md`; **IMPLEMENTED**; `STATIC_EVIDENCE_ONLY`; falta prova runtime/E2E atual do contexto Admin. |
| Contexto e isolamento do tenant | Shared/Core; `X-Tenant-ID`/domínio/host via middleware, stack Admin em `Core/Routes/admin.php:11-19` | `ApiContext`, middleware de tenant e `tenant.access` | `tenant_id` é âncora; Actions usam scope explícito; Admin só próprio tenant | `TenantScopingTest`, `TenantIsolationSmokeTest`, testes de auth/área | `multi-tenancy.md`; **IMPLEMENTED** estaticamente; `STATIC_EVIDENCE_ONLY`; falta validação atual contra banco/app rodando. |
| Listar e consultar usuários do tenant | Core; `/api/v1/core/users`, `/api/v1/core/users/{user}` (`Core/Routes/api.php:61-70`) | `UserController` → `ListUsersAction`, `ShowUserAction` | `users`/roles/tenant migrations; gates list/view; `ListUsersAction` usa `where('tenant_id')` e oculta developer (`:12-28`) | `UserAdminApiTest.php`; sem E2E HTTP correspondente | Core User spec; **PARTIAL**: comportamento existe, superfície é legado sem `area.guard:admin`; `STATIC_EVIDENCE_ONLY`. Gap de area-first e prova. |
| Convidar student/instructor e aceitar convite | Core; emissão `/api/v1/core/invitations`, aceite público `/api/v1/core/invitations/accept` (`Core/Routes/api.php:47-59`) | `InvitationController` → `CreateInvitationAction`, `AcceptInvitationAction` | `invitations` + `users`; gate `core.invitations.create`, throttle, tenant/email/role fixos e sem escalada | `InvitationApiTest.php`, `InvitationOnboardingTest.php`; nenhum E2E HTTP Admin de convite | Core tasks `:48`, `:101`; **PARTIAL**: regra forte, rota de emissão ainda legado; `STATIC_EVIDENCE_ONLY`. |
| Atualizar/excluir student/instructor do tenant | Core; `PATCH/DELETE /api/v1/admin/users/{user}` (`Core/Routes/admin.php:21-25`) | `Core\Http\Controllers\Admin\UserController` → `UpdateProfileAction`, `DeleteUserAction` | `User`, soft delete, tokens revogados, policy/gates impedem self/outro Admin/cross-tenant; fields de identidade protegidos | `AdminUserManagementApiTest.php`; `tests/e2e-http/core/admin-users.php` | Core User spec/tasks; **IMPLEMENTED**; `STATIC_EVIDENCE_ONLY`; E2E existe, mas não foi executado agora. |
| Roles customizadas do tenant | Não há rota Admin, controller ou Action | Não encontrado | Spatie roles têm migration de `scope`/`tenant_id`; permission ceiling e seeder existem | `PermissionCeilingTest`, seeder/shape tests; não há teste de CRUD | Core tasks `:101`; **PLANNED**; `DOCUMENTATION_ONLY` para a capability; gap concreto de CRUD. Não bloqueia o mínimo se roles base forem suficientes. |
| Gestão de categorias do tenant e vínculo em curso | `POST/PUT/DELETE /api/v1/admin/categories`; `PUT /api/v1/admin/courses/{id}/categories` (`Learning/Routes/admin.php:22-35`) | Admin `CategoryController` → category Actions; Admin `CourseController` → `SyncCourseCategoriesAction` | `Category`, `Course`, `category_course`; gates de categoria/curso; escopo de tenant e categorias de sistema somente leitura para Admin | `AdminCourseCategoriesApiTest.php`; `admin-categories.php`, `admin-course-categories.php` | Learning tasks/areas; **IMPLEMENTED**; `STATIC_EVIDENCE_ONLY`; redesign de árvore/normalização é gap posterior, não blocker. |
| Criar, consultar, editar, excluir, publicar e despublicar cursos | Area-first só `GET /api/v1/admin/courses/{id}`, publish/unpublish e categorias (`Learning/Routes/admin.php:22-28`); CRUD create/update/delete está em `/api/v1/learning/courses*` (`Learning/Routes/api.php:47-54`) | Admin `CourseController` → get/publish/unpublish/sync; `CourseController` → store/update/delete Actions | `Course`, price history e migrations; `courses.*` gates, tenant explícito, status draft/published | `CourseCrudApiTest.php`; E2E `courses-show/store/publish/unpublish.php` | Learning courses spec/tasks; **PARTIAL**: falta listagem/CRUD completo na superfície Admin; legacy também serve Instructor por policy; `STATIC_EVIDENCE_ONLY`. |
| Gerenciar módulos e ordenação | `/api/v1/learning/modules*`, sem área Admin (`Learning/Routes/api.php:38-45`) | `ModuleController` → get/store/update/reorder/delete Actions | `CourseModule`, tenant/course relations; `modules.*` policy com tenant/ownership | `ModuleApiTest.php`; E2E `modules-store.php` cobre Instructor, não Admin | Learning module tasks; **PARTIAL**: lógica existe, persona/surface Admin não está fechada; `STATIC_EVIDENCE_ONLY`. |
| Gerenciar aulas, ordenação e preview | `/api/v1/learning/lessons*`, sem área Admin (`Learning/Routes/api.php:77-85`) | `LessonController` → lesson Actions; preview/access no fluxo legado | `Lesson`, module/course relations; `lessons.*` policy e tenant scope | `LessonApiTest.php`; E2E `lessons-store.php` cobre Instructor | Learning lesson tasks; **PARTIAL**: Admin route e jornada não existem; `STATIC_EVIDENCE_ONLY`. Progresso/ratings são Student/Instructor e ficam fora. |
| Materiais, media e downloads | `/api/v1/learning/courses/{courseId}/materials*` e `/api/v1/learning/lessons/{lessonId}/media*` (`Learning/Routes/api.php:56-60,92-97`) | `CourseMaterialController`, `LessonMediaController` → material/media Actions | `CourseMaterial`, `LessonMedia`; policies e escopo via course/lesson; upload real/`MediaProvider` ainda alvo | `CourseMaterialApiTest.php`, `LessonMediaApiTest.php`; nenhum E2E Admin | Learning tasks `:112-115`; **PARTIAL**: metadata/CRUD existe, upload/provider real e Admin surface faltam; `STATIC_EVIDENCE_ONLY`. |
| Matrícula administrativa e acompanhamento do tenant | `/api/v1/learning/enrollments*`, sem área Admin (`Learning/Routes/api.php:67-75`) | `EnrollmentController` → list/show/store/update/delete Actions | `Enrollment`, `Order` mirror/event; `enrollments.*` policy, `ListEnrollmentsAction` filtra tenant (`:12-31`) | `EnrollmentApiTest.php` cobre Admin/tenant/permission; sem E2E HTTP Admin | Learning enrollment spec/tasks; **PARTIAL**: comportamento manual existe, rota Admin e jornada externa não; `STATIC_EVIDENCE_ONLY`. |
| Questionários e banco de questões para Admin | `/api/v1/assessment/questionnaires*` e `/api/v1/assessment/questions*`, sem `area.guard:admin` (`Assessment/Routes/api.php:9-31`) | `QuestionnaireController`, `QuestionController` → CRUD Actions | `Questionnaire`, `QuizQuestion`; permissions `assessment.*`, Actions tenant-scoped | `QuestionnaireApiTest.php`, `QuestionApiTest.php`; sem E2E | Assessment tasks/spec; **PARTIAL**: CRUD de questionário existe, questão não tem DELETE na rota/controller e falta vínculo/listagem completa de questões; `STATIC_EVIDENCE_ONLY`. Admin assessment é supporting/SHOULD neste critério mínimo. |
| Tentativas, respostas e progresso | `/api/v1/assessment/attempts*`, legado; create/answer/finish são Student | `AttemptController` → attempt/scoring Actions | `QuizAttempt`; policy restringe o estudante e leitura administrativa quando aplicável | `AttemptApiTest.php`; E2E de fluxo Student pendente | Assessment attempts spec; **OUT_OF_SCOPE_STUDENT** para operação Admin; não é gap Admin. |
| Certificados | `/api/v1/assessment/certificates`, `/{id}` e verify público (`Assessment/Routes/api.php:43-52`) | `CertificateController` → list/show/verify Actions; não há revoke | `Certificate`; `ListCertificatesAction` filtra `user_id = contexto atual` (`:21-28`); permission Admin existe em config, mas não há listagem tenant-wide/revoke | `CertificateApiTest.php`; `lessons-progress.php` testa emissão/idempotência, sem E2E Admin | Assessment certificate spec/tasks; **PARTIAL**: Admin não obtém visão tenant-wide por esta Action e revoke não existe; `STATIC_EVIDENCE_ONLY`. SHOULD. |
| Configuração/white-label do tenant | Não há rota atual de `GET/PATCH /api/v1/core/tenant/config` | Não há controller/Action completos | Models `TenantCustomization`/`TenantIntegration` existem; sem contrato HTTP entregue | Não há Feature/E2E do endpoint | Core tenant-config/tasks `:101-106`; **PLANNED**; `DOCUMENTATION_ONLY`; SHOULD, não blocker do MVP operacional. |
| Configurar gateways de pagamento do tenant | `GET /api/v1/admin/payment-gateways`, `PUT /api/v1/admin/payment-gateways/{plugin}` (`Ecosystem/Routes/api.php:7-15`) | `PaymentGatewayController` → gateway list/update Actions | `Plugin`, `PluginActivation`, `TenantPluginConfig`; permissions financial gateway; config secreta encrypted/hidden e tenant-scoped | `AdminPaymentGatewayApiTest.php`; sem E2E HTTP | Ecosystem/Financial specs/tasks; **IMPLEMENTED**; `STATIC_EVIDENCE_ONLY`; falta prova atual e runtime entitlement, não nova rota Admin. |
| Confirmar pagamento manual/cash e produzir efeitos | `POST /api/v1/admin/orders/{id}/confirm-manual-payment` (`Financial/Routes/api.php:7-14`) | `ConfirmManualPaymentController` → confirm Action | `Order`, `Payment`, outbox/`OrderPaid`; permission financeira, tenant/eligibility/idempotência | `ConfirmManualPaymentApiTest.php`; não há E2E HTTP Admin | Financial tasks/orders; **IMPLEMENTED** para cash; `STATIC_EVIDENCE_ONLY`; precisa E2E de jornada e banco atual. |
| Reporting, dashboard, suporte e leitura de auditoria | Não há rota/controller/service suficientemente especificado | Não encontrado | Activitylog cobre efeitos sensíveis, mas não há API Admin de consulta; não há model/contrato de reporting | Nenhum conjunto dedicado localizado | `areas-surfaces.md` chama dashboard de conceito Admin, mas sem contrato operacional; **UNCLEAR/NOT_FOUND**; não exigir antes de definir escopo. |

## 4. Admin Journeys

### J1 — Acesso, tenant e ciclo de usuários

- **Entrypoint:** `POST /api/v1/auth/login`, seguido de `/api/v1/auth/me`; `X-Tenant-ID` (ou
  resolução por host) identifica o tenant.
- **Pré-condições:** tenant ativo, Admin autenticado, papel/permissão canônica e contexto de
  tenant resolvido.
- **Passos:** login → confirmar identidade/permissions → listar/consultar usuários → emitir convite
  de student/instructor → aceite público do convite → atualizar/excluir usuário elegível.
- **Persistência/side effects:** token Sanctum, `invitations`, usuário com tenant/role fixos,
  soft-delete e revogação de tokens ao excluir.
- **Respostas/erros relevantes:** 401 `unauthenticated`/`invalid_credentials`, 403
  `area_forbidden`/`access_denied`, 404 defensivo cross-tenant, 422 `validation_error`, erro de
  tenant não resolvido e throttling.
- **Evidência:** código e testes Feature existentes; E2E HTTP cobre somente update/delete e
  negativos em `tests/e2e-http/core/admin-users.php`; não há prova atual de login/list/invite.
- **Estado:** parcialmente operável; bloqueada para closure pela superfície legado e ausência de
  prova externa atual.

### J2 — Curso, árvore de conteúdo, categorias e publicação

- **Entrypoint:** precisa ser um conjunto Admin area-first; hoje criação/edição/conteúdo entram por
  `/api/v1/learning/*`, enquanto consulta/publicação/categorias já usam `/api/v1/admin/*`.
- **Pré-condições:** Admin do tenant, permissões de course/module/lesson/category e entidades do
  mesmo tenant.
- **Passos:** criar draft → editar curso → criar/ordenar módulos → criar/ordenar aulas → anexar
  material/media → categorizar → consultar detalhe → publicar/despublicar.
- **Persistência/side effects:** `courses`, `course_modules`, `lessons`, material/media, histórico
  de preço, pivot de categorias, `status`/`published_at`.
- **Respostas/erros relevantes:** 401/403, 404 defensivo cross-tenant, 422 para estado/payload
  inválido; `is_system`, `tenant_id` e `instructor_id` não podem redefinir escopo.
- **Evidência:** Feature coverage ampla e E2E separados para store/show/publish/unpublish,
  categorias, módulos e aulas; os E2E de módulos/aulas exercitam Instructor e os demais são
  arquivos mutantes não executados nesta auditoria.
- **Estado:** conteúdo existe, mas a jornada Admin como persona não está fechada; é o maior gap
  funcional/API.

### J3 — Matrícula administrativa e operação cash

- **Entrypoint:** listagem/criação/alteração/cancelamento de enrollment e confirmação manual em
  `/api/v1/admin/orders/{id}/confirm-manual-payment`; os endpoints de enrollment ainda estão em
  `/api/v1/learning/enrollments*`.
- **Pré-condições:** usuário e curso do mesmo tenant, curso elegível, permissão Admin e Order/Payment
  em estado compatível para cash.
- **Passos:** selecionar curso/usuário → criar ou consultar enrollment → confirmar pagamento manual
  quando aplicável → efetivar evento/outbox → verificar matrícula única e estado.
- **Persistência/side effects:** enrollment, order/payment, `OrderPaid`/outbox e matrícula sem
  duplicação; falha deve ser idempotente e não deixar estado parcial.
- **Respostas/erros relevantes:** 401/403/404, 409 de estado/idempotência, 422 de elegibilidade,
  `gateway_unavailable` somente no fluxo de gateway, e isolamento cross-tenant.
- **Evidência:** `EnrollmentApiTest.php` e `ConfirmManualPaymentApiTest.php` têm cobertura
  histórica extensa; nenhum E2E HTTP Admin dessa jornada foi executado/encontrado.
- **Estado:** cash está implementado estaticamente; closure requer surface Admin e prova HTTP.

### J4 — Avaliação e certificado (supporting, não critério mínimo desta primeira closure)

- **Entrypoint:** questionários/questões hoje em `/api/v1/assessment/*`; tentativa é Student.
- **Pré-condições/steps:** Admin cria questionário e questões, deveria associá-las; Student tenta,
  finaliza e pode gerar certificado; Admin deveria consultar/revogar conforme a spec.
- **Persistência/side effects:** questionnaire/questions/attempt/scoring/certificate e emissão
  idempotente.
- **Estado:** CRUD base e emissão existem, mas associação, delete de questão, visão tenant-wide e
  revoke não. A jornada fica explicitamente em SHOULD por não fazer parte do mínimo `ADMIN-OPS`
  documentado no roadmap; isso não apaga os gaps do contrato Assessment.

## 5. Definition of ADMIN_COMPLETE

`ADMIN_COMPLETE` nesta auditoria significa o menor Admin operacional compatível com o roadmap e as
invariantes, não a conclusão de todo o produto EAD.

### MUST HAVE

1. Login canônico e contexto de tenant funcionando; toda rota de produto area-first Admin com a
   stack exata e `area.guard:admin`.
2. Isolamento defensivo: Admin só lê/escreve o próprio tenant, sem spoof de `tenant_id`, papel ou
   ownership; 401/403/404/422 no envelope canônico.
3. RBAC efetivo e permission ceiling comprovados; o Admin base consegue usar as roles canônicas e
   não pode elevar student/instructor a Admin.
4. Ciclo de usuários: list/show do tenant, convite de student/instructor, update/delete dos tipos
   elegíveis, com revogação/soft-delete.
5. Ciclo de operação Learning: criar/editar/excluir/consultar/publicar curso, administrar módulos,
   aulas, material/media básico e categorias; publicação continua exclusiva do Admin.
6. Matrícula administrativa e caminho cash/manual com efeitos idempotentes e isolamento.
7. Testes focados atuais e pelo menos uma jornada E2E HTTP atual cobrindo auth → tenant → usuário →
   curso/conteúdo → matrícula/confirmção, incluindo negativos de persona e cross-tenant.
8. Scribe/contrato de rotas e invariantes Architecture coerentes com a superfície entregue; nenhum
   `EVIDENCE_PENDING` material para esses MUST.

Roles customizadas, white-label, upload real, Assessment completo, reporting e vendas automáticas
ficam fora do mínimo apenas porque o tenant consegue operar com as roles base, defaults de
configuração, conteúdo não dependente de upload, caminho cash e avaliações opcionais. Essa é uma
decisão de escopo de closure, não uma alegação de que essas capacidades estejam completas.

### SHOULD HAVE

- CRUD de roles de tenant com `scope=tenant`.
- `GET/PATCH /api/v1/core/tenant/config` e integração/white-label administrativa.
- Questionários e questões em área Admin, incluindo associação/listagem/delete.
- Visão tenant-wide e revoke de certificados para Admin.
- Upload real via `MediaProvider`/Media Library.
- Runtime verification de permissões/entitlements de plugins e E2E do gateway.
- API de auditoria e reporting, depois que o contrato de negócio for definido.

### LATER

- Webhook público, `ProcessPaymentWebhookJob`, adapters externos e fluxo pago automático completo.
- Comissões de Instructor e segundo ledger `PlatformOrder*`.
- Marketplace, pricing/subscriptions e entitlements MZRT.
- Migração/depreciação final das superfícies legacy após inventário de consumidores.
- i18n/SEO/árvore avançada de categorias e demais pendências de produto não necessárias ao MVP.

## 6. Gaps

| ID | Capability / evidência | Tipo | Impacto | Prioridade | Dependências | Esforço relativo | Bloqueia `ADMIN_COMPLETE` |
|---|---|---|---|---|---|---|---|
| `ADM-01` | Usuários: list/show e emissão de convite estão em `/v1/core`, sem guard Admin (`Core/Routes/api.php:43-74`). | `API_CONTRACT`, `AUTHORIZATION` | Persona Admin não tem superfície canônica; acesso legado pode ser exercido por outros tipos conforme policy. | `HIGH` | Core routes, controller, Scribe, Feature/Architecture | Médio | **yes** |
| `ADM-02` | Curso create/update/delete, módulos, aulas, material/media e seus reorder/CRUD estão em `/v1/learning`, sem guard Admin (`Learning/Routes/api.php:15-98`). | `API_CONTRACT`, `AUTHORIZATION`, `SECURITY` | Mistura Admin/Instructor e deixa a jornada Admin dependente de prefixo legado não coberto pelo guard de área. | `BLOCKER` | Learning routes/controllers, ownership policies, tests de área/tenant | Alto | **yes** |
| `ADM-03` | Matrículas Admin existem em código/testes, mas somente em `/v1/learning/enrollments*`; não há E2E HTTP Admin cash. | `API_CONTRACT`, `E2E`, `TENANCY` | Não há prova externa de operação tenant → matrícula → efeito idempotente. | `HIGH` | Learning + Financial outbox, runner E2E, banco dedicado | Alto | **yes** |
| `ADM-04` | Nenhuma validação runtime/E2E atual foi executada; Docker está parado e os cenários HTTP têm setup/cleanup mutantes. | `TEST`, `E2E`, `HARNESS` | Impossível promover as capabilities a `RUNTIME_VERIFIED`/`E2E_VERIFIED`. | `BLOCKER` | App/banco e2e adequados; execução controlada e receipt | Médio | **yes** |
| `ADM-05` | Fechar seleção de testes para todas as novas superfícies Admin: area guard, tenant, RBAC, envelopes e negativos persona/cross-tenant. | `TEST`, `SECURITY` | Código existente tem cobertura por fatia, mas não há prova atual da jornada integrada. | `HIGH` | `AreaRouteGuardTest`, `TenantScopingTest`, PermissionCeiling, Feature | Médio | **yes** |
| `ADM-06` | CRUD de roles customizadas do tenant não existe; explicitamente pending em Core tasks `:101`. | `PRODUCT`, `AUTHORIZATION` | Limita delegação interna, mas roles canônicas já permitem operar o tenant. | `MEDIUM` | Spatie role scope, policy, permission metadata | Médio | no |
| `ADM-07` | Tenant config GET/PATCH não existe, embora especificado em `tenant-config.md:73-80`. | `PRODUCT`, `API_CONTRACT` | Impede white-label/configuração administrativa; defaults ainda permitem operação básica. | `MEDIUM` | Core models, public surface, RBAC | Médio | no |
| `ADM-08` | Assessment Admin não tem area-first; faltam delete de questão e associação/listagem completa. | `PRODUCT`, `API_CONTRACT`, `TEST` | Não permite montar/administrar avaliação completa pela superfície Admin. | `MEDIUM` | Assessment Actions/permissions, Scribe, E2E | Alto | no |
| `ADM-09` | Certificados: listagem atual é do usuário do contexto e não há revoke (`ListCertificatesAction.php:21-28`). | `PRODUCT`, `AUTHORIZATION` | Admin não tem visão administrativa tenant-wide prevista no contrato. | `MEDIUM` | Certificate policy/action, Assessment E2E | Médio | no |
| `ADM-10` | Upload real/`MediaProvider` ainda é pending em Learning tasks `:112-115`. | `PRODUCT`, `DEVEX` | Conteúdo binário não pode ser gerenciado de ponta a ponta; metadata ainda funciona. | `MEDIUM` | Media seam, storage, security upload | Alto | no |
| `ADM-11` | Runtime de entitlement/plugin e E2E de gateway não estão comprovados no estado atual. | `SECURITY`, `HARNESS` | Configuração de gateway pode não representar capability efetivamente liberada em runtime. | `MEDIUM` | Ecosystem provider, runtime app, E2E | Médio | no para cash; yes para venda automática |
| `ADM-12` | Webhook/job/adapters de pagamento automático estão pending em Financial tasks `:29`. | `FINANCIAL`, `E2E` | Bloqueia o caminho pago automático, não o caminho Admin cash/manual. | `HIGH` | Student checkout, gateway adapters, queue, outbox | Alto | no para Admin MVP; yes para jornada paga |
| `ADM-13` | Reporting/dashboard/support não tem contrato ou implementação suficiente para inventário fechado. | `UNKNOWN`, `PRODUCT` | Não é possível exigir ou estimar sem decisão funcional. | `LOW` | Definição de produto e dados | Alto/indeterminado | no |

## 7. Cross-cutting Dependencies

| Módulo/fundação | Motivo | Escopo mínimo para Admin | Estado | Abre indevidamente outra área? |
|---|---|---|---|---|
| Core/Auth | Login, `ApiContext`, usuários, convites e RBAC | Migrar somente surfaces de list/show/invite para Admin; preservar `/auth/*` neutro e compatibilidade explicitamente inventariada | Base entregue; Admin surface parcial | Não, se `/auth` permanecer neutro e o guard for aplicado só a `/admin`. |
| Core/Tenancy | Resolver tenant e negar cross-tenant | Provar `tenant_id` em cada Action Admin e 404 defensivo | Implementado estaticamente; runtime pendente | Não. |
| Shared/Area + API contract | Guard exato, ordem 403 antes de binding, envelope, Resource e Scribe | Toda rota nova Admin deve entrar em `Routes/admin.php`/área equivalente e passar Architecture | Implementado para as rotas já area-first | Não, mas legacy não é substituto. |
| Learning | Cursos, árvore de conteúdo, categorias, enrollments | Separar superfície Admin da ownership de Instructor, sem mudar regras de Student | Implementação ampla, superfície parcial | Pode abrir Instructor se a rota compartilhada continuar ambígua; deve ser evitado. |
| Assessment | Avaliações/certificados opcionais | Nenhuma alteração é necessária ao mínimo cash/content; tratar em slice separado | Parcial | Não deve abrir Student attempts além do contrato. |
| Financial | Order/Payment, manual cash, `OrderPaid`/outbox | Provar confirmação manual e matrícula uma vez; não puxar webhook automático | Cash implementado; automático pending | Não, desde que checkout/attempts permaneçam Student. |
| Ecosystem | Plugins/gateway do tenant | Manter apenas GET/PUT Admin de gateways e segredos protegidos | Implementado estaticamente; entitlement runtime pending | Não; catálogo/entitlements globais permanecem MZRT. |
| Test/Scribe/harness | Provar contrato atual | Receipt Feature + E2E + Architecture e documentação gerada | E2E/runtime atual pending | Não; é fundação de prova. |

## 8. Financial Boundary

### Pertence ao Admin

- Configuração de gateways de venda do tenant (`Ecosystem`, GET/PUT area-first).
- Confirmação manual de pagamento/cash (`Financial`, `POST /admin/orders/{id}/confirm-manual-payment`).
- Operação administrativa de enrollment e seus efeitos `OrderPaid`/outbox.
- Consulta/gestão financeira do tenant somente na medida em que o contrato atual explicitamente
  expõe essas operações.

### Pertence ao Student ou ao fluxo neutro/público

- `POST /api/v1/student/checkout`, pedido iniciado pelo comprador e consulta de pedidos próprios.
- Webhook de gateway é público/cego e o processamento automático pertence ao pipeline Financial,
  não à superfície Admin.

### Pertence ao MZRT

- `PlatformOrder*`, assinaturas de plugins, ledger plataforma, marketplace e cobrança
  MZRT→tenant.

### Dependência bloqueante

Não há dependência financeira bloqueante para o critério mínimo se a jornada aceita for **cash/manual**:
gateway config e confirmação manual já têm rotas, Actions, autorização, tenant scope e testes
escritos. Há dependência bloqueante somente para declarar uma jornada **paga automática**: webhook,
`ProcessPaymentWebhookJob`, adapters externos e E2E pago ainda estão pendentes. Essa jornada é
Student-PAID/Financial e deve continuar fora do primeiro closure Admin.

## 9. Instructor Boundary

O código atual mistura a capacidade de Admin e Instructor em `/api/v1/learning/*`: a policy concede
ao Admin controle tenant-wide e ao Instructor ownership do curso próprio, enquanto o mesmo
controller/rota atende ambos. Isso é dívida de surface/persona, não autorização suficiente para
considerar a fronteira resolvida.

Para Admin, a regra mínima é: Admin administra qualquer recurso do próprio tenant; Instructor
continua owner/autor apenas dos cursos próprios; Student não alcança escrita. A migração de rotas
Admin não deve conceder ao Instructor publicação, gestão de usuários, categorias de sistema ou
configuração financeira Admin.

Não há dependência Instructor bloqueante para o mínimo Admin. O conteúdo pode ser criado/gerido
administrativamente sem abrir a jornada de autoria do Instructor; a separação de ownership deve ser
testada como negativo durante `ADM-02`.

## 10. Harness Readiness

| Controle | Estado observado | Classificação |
|---|---|---|
| `area.guard:admin` | Stack exata existe em `Core/Routes/admin.php`, `Learning/Routes/admin.php`, Financial e Ecosystem. O guard não cobre prefixos legacy por decisão arquitetural. | `BLOCKS_ADMIN` para closure enquanto recursos Admin continuarem somente em legacy; não bloqueia desenvolvimento das rotas já corretas. |
| Tenant isolation | Actions e testes estáticos/Feature existem; nenhum runtime atual foi executado. | `BLOCKS_ADMIN` para evidência de closure. |
| RBAC/permission ceiling | Config, seeder, Gates/policies e testes existem; custom-role CRUD está pending. | `SHOULD_FIX_DURING_ADMIN`; base RBAC não precisa ser refeito. |
| Routes/security surface | Há testes Architecture para surface/guard e rotas area-first existentes; Assessment/Core/Learning legacy não são área Admin canônica. | `BLOCKS_ADMIN` para novas surfaces; `SHOULD_FIX_DURING_ADMIN` para inventário de compatibilidade. |
| Controller/Action boundary | Controllers Admin observados são finos; há Actions reutilizáveis nas rotas legacy. | `SHOULD_FIX_DURING_ADMIN`: manter o padrão ao re-slotar, sem refatoração transversal. |
| Test selection | `scripts/ai/verify-changes.sh` e suítes Architecture existem; não foram executados testes mutantes nesta auditoria. | `SHOULD_FIX_DURING_ADMIN`. |
| E2E/evidence lifecycle | Runner `E2eRunCommand` e specs Admin existem; receipt atual não existe para Admin. | `BLOCKS_ADMIN`. |
| Codex harness hardening (WS2) | Não avaliado como workstream nem alterado; a auditoria pode continuar manualmente com escopo read-only e invariantes existentes. | `CAN_WAIT` como WS2; a falta de evidência Admin continua sendo `BLOCKS_ADMIN`. |

Conclusão de harness: **`BLOCKS_ADMIN = yes` para declarar closure**, especificamente por falta de
prova runtime/E2E e pela superfície legacy não convergida. Não há finding que impeça iniciar slices
Admin com a disciplina atual; não é necessário executar WS2 nesta área.

## 11. Validation Evidence

### Evidência obtida nesta auditoria

- Leitura de `AGENTS.md`, `docs/STATE.md`, `docs/ROADMAP.md`, `docs/specs/README.md`, arquitetura de
  áreas, RBAC, tenancy, API, segurança/LGPD e specs/tasks dos cinco módulos.
- Inspeção estática de rotas, controllers, Actions, models/migrations, permissions, policies e
  testes/E2E relacionados.
- Consulta Graphify para localizar relações de Admin, rotas, dependências e artefatos.
- Tentativa de inspeção por `sail artisan route:list`; no estado final da auditoria o comando
  reportou **`Docker is not running.`**. Portanto não é uma prova runtime atual.
- O working tree estava limpo antes da criação deste relatório; `docs/STATE.md` não foi alterado.

### Evidência não promovida

- Nenhum Feature, Unit ou Architecture test foi executado, pois o harness de testes usa banco e a
  instrução desta auditoria restringe checks que possam mutar dados/artefatos.
- Nenhum E2E HTTP foi executado; todos os specs relevantes têm setup/cleanup de banco.
- Resultados dos relatórios anteriores, inclusive MZRT/WS1, foram tratados como históricos e não
  promovidos a `RUNTIME_VERIFIED`.
- Scribe não foi regenerado: isso criaria artefatos e relatórios anteriores registram problema de
  ownership no cache de endpoints.

## 12. Estimated Completion

**ADMIN estimated completion: 55–65%, confidence medium.**

Racional por jornada, sem contar arquivos ou quantidade de testes:

- J1 usuários/acesso: base forte, mas list/show/invite ainda legacy e sem prova atual.
- J2 curso/conteúdo: domínio amplamente implementado; a surface Admin e o percurso completo de
  módulos/aulas/material ainda não estão fechados.
- J3 enrollment/cash: lógica manual e idempotência implementadas, mas enrollment é legacy e falta
  E2E externo atual.
- J4 assessment/certificates: deliberadamente supporting/SHOULD, parcial e não contado como MUST.

A faixa não é maior porque a única área-first não mede a jornada inteira; não é menor porque os
modelos, policies, Actions, Resources, testes e boa parte das regras de domínio já existem.

## 13. Minimal Closure Plan

São **4 slices mínimos**, orientados a jornadas, não microtarefas artificiais.

### Slice 1 — Admin identity/control plane

- Fechar `/api/v1/admin/users` list/show, emissão de convite e a jornada auth → tenant → users;
  manter `/auth/*` neutro e mapear compatibilidade legacy.
- Done: exact area stack, tenant isolation, no Admin escalation, envelopes/Resources/Scribe,
  Feature atual e E2E HTTP com list/show/invite/update/delete/negative cases.
- Dependências: Core user/invitation Actions, RBAC, tenant middleware.
- Runtime/E2E: obrigatório contra app/banco dedicados.
- Modelo: **`LUNA_HIGH`** (surface + authorization + IDOR review).

### Slice 2 — Admin Learning operation

- Fechar area-first para course list/create/update/delete e para module/lesson/material/media
  management; preservar ownership Instructor e deixar publish/unpublish/categorias no Admin.
- Done: draft → conteúdo → categorias → publish/unpublish em uma surface Admin coerente, sem
  payload redefinir tenant/owner; Feature, Architecture e E2E HTTP Admin.
- Dependências: Learning Actions/models/policies, media atual; upload real pode continuar SHOULD.
- Runtime/E2E: obrigatório, incluindo foreign tenant/persona e publicação.
- Modelo: **`LUNA_XHIGH_REVIEW`** (fronteira Admin/Instructor e mass assignment/IDOR).

### Slice 3 — Admin enrollment + cash closure

- Re-slotar CRUD/listagem de enrollment para Admin e comprovar matrícula manual → confirmação cash
  → `OrderPaid`/outbox idempotente; gateway config atual recebe smoke E2E.
- Done: uma matrícula por order, retries sem duplicar, cross-tenant/estado inválido negados,
  contrato e receipt HTTP atual.
- Dependências: Learning enrollment Actions, Financial Order/Payment/outbox, Ecosystem gateway.
- Runtime/E2E: obrigatório; não incluir webhook automático.
- Modelo: **`LUNA_XHIGH_REVIEW`** (dinheiro, side effects, idempotência).

### Slice 4 — Closure evidence and contract seal

- Executar seleção focada de Architecture/Feature, E2E HTTP Admin completo, Scribe e receipt de
  closure; selar SHOULD/LATER/unknowns.
- Done: MUST HAVE = 0, runtime/E2E atual, tenant/RBAC/security verdes, documentação de rotas
  coerente e nenhum `EVIDENCE_PENDING` material.
- Dependências: Slices 1–3, app/banco e2e, ownership/permissões de artefatos Scribe.
- Runtime/E2E: obrigatório.
- Modelo: **`PREMIUM_REVIEW_ONLY`**.

Roles customizadas, tenant config e Assessment não entram nesses quatro slices mínimos; entram em
SHOULD salvo decisão posterior que promova explicitamente uma dessas jornadas a MUST.

## 14. SHOULD/LATER

### SHOULD

1. CRUD de roles tenant-scoped e atribuição segura sem elevar user type.
2. Configuração pública/administrativa de tenant e integração/white-label.
3. Surface Admin de questionnaires/questions com attach/list/delete e cobertura E2E.
4. Consulta tenant-wide e revoke de certificados.
5. Upload real e `MediaProvider`, com path validation e storage seguro.
6. Runtime entitlement/plugin verification, auditoria consultável e reporting após contrato.

### LATER

1. Webhook/job/adapters para pagamento automático e E2E pago cross-module.
2. Orders/Payments do Student, commissions e ledger plataforma.
3. Marketplace, subscriptions, catálogo/entitlements MZRT e operações de developer.
4. Migração final/depreciação de legacy após inventário de consumidores.
5. Melhorias de categoria/i18n/SEO e funcionalidades não previstas no mínimo Admin.

## 15. Unknowns / Evidence Pending

- `EVIDENCE_PENDING`: execução atual de auth, tenant isolation, RBAC, Admin routes, envelopes e
  side effects financeiros.
- `EVIDENCE_PENDING`: uma jornada HTTP Admin completa contra app real e banco e2e dedicado.
- `EVIDENCE_PENDING`: Scribe regenerado com artefato atual e ownership resolvido.
- `EVIDENCE_PENDING`: comportamento runtime de plugin entitlement/gateway e disponibilidade dos
  adapters.
- `UNCLEAR`: requisitos de dashboard/reporting/support e se Assessment deve ser promovido a MUST.
- `UNCLEAR`: política de migração/depreciação para list/show users, course CRUD e conteúdo legacy;
  o código atual ainda mantém essas superfícies.
- Não há base para chamar qualquer capability Admin de `RUNTIME_VERIFIED` nesta auditoria.

## 16. Verdict

`ADMIN_PARTIAL`

Admin tem fundação e fatias area-first reais, mas não satisfaz ainda o critério de operação do
tenant de ponta a ponta. O caminho mínimo é convergir identidade, Learning e enrollment/cash para
surfaces Admin protegidas, executar a prova HTTP/runtime e selar a evidência. Financial automático,
Assessment completo, tenant config, roles customizadas, reporting e todas as capacidades MZRT,
Instructor e Student ficam explicitamente fora do primeiro closure.
