# Instructor Gap Analysis & Closure Targeting — 2026-09-08

## 1. Executive Summary

**Verdict:** `INSTRUCTOR_PARTIAL`

**Completion estimado:** **40%**, com confiança **média-alta (0,82)** para o diagnóstico estático e **baixa para runtime atual**. O percentual não é uma medição de tasks concluídas; é uma estimativa de cobertura do mínimo funcional definido neste relatório.

Existe uma base legacy real: mutações de Course, Module, Lesson e LessonMedia já possuem alguns checks de ownership pedagógico, e há criação legacy de curso/matrícula para Instructor. Porém, não existe uma superfície canônica `/api/v1/instructor`, nem controllers/requests/resources separados para ela. A implementação atual mistura compatibilidade legacy, consumo de aluno e operação tenant-wide; portanto, permission nominal não pode ser tratada como closure.

Os blockers mais graves são de ownership dentro do mesmo tenant:

- `StoreEnrollmentAction` não confirma que o Course pertence ao Instructor autenticado antes de criar matrícula para terceiro (`app/Modules/Learning/Actions/Enrollment/StoreEnrollmentAction.php:36-49`).
- criação legacy de Questionnaire aceita `quizable_id` por existência global do ID, sem tenant nem ownership do parent (`app/Modules/Assessment/Actions/Questionnaire/StoreQuestionnaireAction.php:37-52`).
- listagem/edição legacy de Questionnaires e Questions é tenant-wide, sem filtrar `instructor_id` (`app/Modules/Assessment/Actions/Questionnaire/ListQuestionnairesAction.php:20-43`, `UpdateQuestionnaireAction.php:25-36`, `app/Modules/Assessment/Actions/Question/ListQuestionsAction.php:19-41`, `UpdateQuestionAction.php:27-56`).
- matrícula list/show também é tenant-wide para Instructor (`app/Modules/Learning/Actions/Enrollment/ListEnrollmentsAction.php:12-31`, `ShowEnrollmentAction.php:10-17`; `EnrollmentPolicy.php:42-73`).

O menor caminho para `INSTRUCTOR_COMPLETE` é de **4 slices verticais**: Learning próprio canônico; alunos/matrícula/progresso próprio; Assessment básico/resultados próprios; e fechamento de contrato/evidência. Assignment/reassignment, matrícula paga externa, upload/MediaProvider, quiz avançado e automação financeira permanecem fora ou dependem de decisão humana.

## 2. Instructor Boundary

O boundary usado nesta análise é o já canonicalizado:

| Regra | Aplicação ao closure |
|---|---|
| Admin | operador tenant-wide; pode criar Course sem Instructor (`instructor_id = null`) e não adquire ownership pedagógico |
| Instructor | owner pedagógico; só opera Course próprio e a árvore transitiva Module → Lesson → Media/Material |
| Permission | necessária, mas nunca substitui ownership |
| Tenant | sempre derivado do contexto; nenhum payload pode redefini-lo |
| Category | System global e Custom do tenant são somente leitura para Instructor; não há CRUD nem alteração canônica do pivô |
| Assessment | básico; próprio e com parent próprio; Admin-owned (`instructor_id = null`) não entra automaticamente no scope |
| Financial | Instructor não configura gateway, confirma cash, executa checkout nem opera webhook |

`INSTRUCTOR_COMPLETE` não significa todo o LMS. O mínimo funcional é o Instructor conseguir listar e visualizar seus Courses, administrar sua árvore de conteúdo, administrar metadados de mídia/material, consultar categorias disponíveis, administrar Assessment básico próprio e consultar alunos/progresso/resultados dos próprios Courses, sem ganhar visão tenant-wide.

Não entra no mínimo: taxonomia CRUD, upload/provider binário, quiz avançado/plugin, checkout/gateway/webhook, comissões, Student self-service, marketplace, reporting tenant-wide, assignment/reassignment sem decisão de produto e restore de Course arquivado.

## 3. Current Surface Inventory

### 3.1 Classificação por capability

| Capability | Estado | Evidência e leitura |
|---|---|---|
| Área `/api/v1/instructor` | `MISSING` | Não há `Routes/instructor.php`, nem registro correspondente nos providers. Learning registra somente `api.php`, `admin.php` e `mzrt.php` (`app/Modules/Learning/Providers/LearningServiceProvider.php:154-159`); Assessment registra `api.php` e `admin.php` (`app/Modules/Assessment/Providers/AssessmentServiceProvider.php:36-40`). |
| Course próprio: mutar/criar/preview | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | Legacy `/api/v1/learning` tem POST/PATCH/DELETE/preview (`app/Modules/Learning/Routes/api.php:47-54`); `CoursePolicy` confirma ownership para preview/update/publish/delete, mas não para `list`/`show` (`CoursePolicy.php:11-37,39-70,100-170`). |
| Course próprio: listar/show administrativo | `MISSING` | Não existe endpoint Instructor próprio; o action de lista disponível é Admin/tenant-wide. A árvore legacy é de consumo e não substitui uma listagem de drafts/ativos próprios. |
| Modules próprios | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | CRUD e reorder existem em legacy (`Routes/api.php:38-45`) e têm checks transitivos; faltam superfície canônica, Resources/contrato Instructor e evidência runtime atual. |
| Lessons próprias | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | CRUD/reorder existem (`Routes/api.php:77-85`) e mutações usam ownership transitivo; show é consumer-oriented e não é uma leitura administrativa completa. |
| LessonMedia própria | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | POST/PATCH/DELETE existem (`Routes/api.php:92-97`) e a policy verifica a Lesson; falta list/show administrativo e contrato canônico. Múltiplas mídias são suportadas. |
| CourseMaterial próprio | `PARTIAL` | Legacy só oferece create e download (`Routes/api.php:56-60`); não há list/show/update/delete Instructor completo. O Admin tem material tenant-wide e `instructor_id = null`. |
| Categorias leitura | `IMPLEMENTED_AND_CANONICAL` | GET de catálogo existe (`Routes/api.php:18-34`), com System/Custom; a decisão canônica proíbe CRUD e alteração do pivô pelo Instructor. |
| Categorias seleção/associação | `OUT_OF_SCOPE` / `HUMAN_DECISION_REQUIRED` | A decisão canônica atual mantém vínculo `category_course` Admin-only; “selecionar ao editar o próprio Course” ainda precisa de decisão explícita se for requisito do closure, para não conflitar com ADR-002. |
| Matrícula manual free | `PARTIAL` | Existe no legacy, condicionado à configuração do tenant, mas sem ownership do Course antes da criação (`StoreEnrollmentAction.php:23-49`). |
| Lista de alunos próprios | `MISSING` | A lista existente filtra apenas `tenant_id`, com filtros livres de `course_id`/`user_id` (`ListEnrollmentsAction.php:14-31`). |
| Progresso próprio de alunos | `MISSING` | Há heartbeat de progresso do próprio aluno, mas nenhuma rota/action Instructor para leitura de progresso dos alunos do Course. `learning.progress.view` existe no RBAC, mas não há superfície consumidora correspondente. |
| Assessment próprio | `PARTIAL` / `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | CRUD legacy existe, mas list/show/update são tenant-wide e não validam ownership do recurso/parent. Não existe `/api/v1/instructor` de Assessment. |
| Attempts/results de alunos próprios | `MISSING` | Instructor tem permission nominal de view/list, mas não há endpoint de listagem de attempts por Course/Questionnaire e `ShowAttemptAction` é filtrado pelo próprio `user_id`; não entrega resultados dos alunos ao Instructor. |
| Certificates de alunos próprios | `MISSING` | As actions existentes listam/show filtrando `Certificate.user_id` pelo usuário autenticado (`ListCertificatesAction.php:21-28`, `ShowCertificateAction.php:19-24`). |
| Assignment/reassignment | `MISSING` / `HUMAN_DECISION_REQUIRED` | `instructor_id` nullable é suportado, mas não há operação explícita de atribuição/transferência. O Course sem Instructor permanece Admin-owned/tenant-operated. |
| Scribe | `PARTIAL` | Scribe documenta legacy e Admin; não existe endpoint Instructor canônico para documentar. Não foi encontrado `api/v1/instructor` em `.scribe`/`public/docs`. |
| E2E HTTP Instructor | `MISSING` | Existem E2E Admin, Learning legacy e Student, mas não spec `tests/e2e-http/instructor/*`. |

### 3.2 Rotas legacy e superfície canônica

Learning legacy é montado em `v1/learning` com auth/tenant, mas sem `area.guard:instructor` (`app/Modules/Learning/Routes/api.php:15-17`). Assessment legacy é montado em `v1/assessment` e também não possui guard de área (`app/Modules/Assessment/Routes/api.php:9-15,23-40`). Isso é compatibilidade existente, não contrato de uma área Instructor.

Os testes arquiteturais passam isoladamente para a superfície que existe (`AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest` e `PermissionDriftTest`), mas não podem provar uma área que ainda não foi criada.

## 4. Ownership Audit

### 4.1 Matriz de ownership

| Recurso/operação | Tenant | Parent/ownership | Payload spoofing | Resultado |
|---|---|---|---|---|
| Course update/delete/preview | Validado em policy | `instructor_id === actor` para Instructor | Requests legacy devem ser revistos na convergência | **Parcialmente correto, legacy** |
| Course list/show | Tenant/permission | Não exige owner | Risco de escopo amplo | **Blocker de leitura própria** |
| Module CRUD/reorder | Actions tenant-scoped + policy transitiva | Course do Module deve ter `instructor_id` do actor | `course_id` deve vir da URL/contexto validado | **Base correta, precisa canonicalizar** |
| Lesson CRUD/reorder | Tenant + Module | Module → Course → Instructor | Parent deve ser derivado/validado, não aceito livremente | **Base correta, precisa canonicalizar** |
| LessonMedia mutation | Tenant + Lesson | Lesson → Module → Course → Instructor na policy | `lesson_id`/`media_id` devem ser validados juntos | **Base correta para mutação; leitura/lista ausente** |
| CourseMaterial create/download | Course tenant-scoped | Create usa CoursePolicy update, download usa view sem own check | Material/parent precisa ser composto | **Partial; update/delete/list ausentes** |
| Enrollment list/show | Tenant | Nenhum vínculo com Course owner | `course_id` é filtro, não boundary | **HIGH: Instructor A pode ver alunos de B** |
| Enrollment create | Course tenant-scoped | Não verifica Course owner | `course_id` controla alvo sem ownership | **HIGH: Instructor A pode matricular em Course de B** |
| Questionnaire create | Tenant no novo registro | `quizable_id` só tem `exists()` por ID | `quizable_type/id` pode apontar parent fora do tenant/owner | **HIGH** |
| Questionnaire list/show/update/delete | Tenant | Não filtra `instructor_id` nem parent owner | ID de outro Instructor dentro do tenant é operável | **HIGH** |
| Question create | Tenant + próprio `instructor_id` | Não há parent pedagógico validado nesta action | `category_ids` são anexados sem validação de tenant/escopo | **HIGH/MEDIUM**, depende da categoria ligada |
| Question list/show/update | Tenant | Não filtra owner | ID de outro Instructor dentro do tenant é operável | **HIGH** |
| Attempts | Tenant no lookup e permission nominal | Não há leitura de tentativa por Course owner | IDs precisam ser validados pelo parent próprio | **MISSING/HIGH se exposto** |

### 4.2 Caso crítico: Instructor A contra Instructor B

O caso está confirmado estaticamente em mais de uma trilha:

1. **Matrícula:** o Course é buscado pelo tenant, mas sem `where('instructor_id', actor)`; a permissão de matrícula para Instructor permite alcançar terceiro quando o switch free ou external paid é aplicável (`StoreEnrollmentAction.php:23-49`).
2. **Questionnaire:** o parent é validado com `ModelClass::where('id', $quizableId)->exists()`, sem tenant e sem Course owner (`StoreQuestionnaireAction.php:37-51`).
3. **Questionnaire/Question existentes:** list/update usam somente `tenant_id`; um Instructor consegue selecionar ou editar o ID de outro Instructor no mesmo tenant (`ListQuestionnairesAction.php:20-43`, `UpdateQuestionnaireAction.php:25-36`, `ListQuestionsAction.php:19-41`, `UpdateQuestionAction.php:27-56`).
4. **Enrollment read:** `EnrollmentPolicy::view` retorna true para qualquer Instructor após tenant/permission, sem relacionar Enrollment ao Course owned (`EnrollmentPolicy.php:42-73`).

Esses achados são **HIGH** porque satisfazem exatamente o cenário “A opera recurso de B dentro do mesmo tenant”. A existência de `InstructorOwnershipTest` com 18 negativos para Course/Module/Lesson (`tests/Feature/Api/Learning/Rbac/InstructorOwnershipTest.php:10-15,46-242`) é evidência positiva somente dessas mutações; não cobre Enrollment, Assessment, listagens, progresso ou resultados.

## 5. Learning

### Courses

O modelo suporta `instructor_id` nullable e distingue Course Admin-owned de Course próprio (`docs/specs/20-catalog-learning/subspecs/courses-modules-lessons.md:13-27,75-78`). A criação legacy atribui o actor; a criação Admin canônica grava `null`. O mínimo Instructor precisa de:

- `GET /api/v1/instructor/courses` com apenas Courses em que `instructor_id = actor`;
- show administrativo próprio incluindo drafts, módulos, aulas, materiais e metadata permitido;
- create/update/delete próprios, com Course/tenant/owner derivados do contexto;
- preview próprio de draft;
- lifecycle explicitamente definido.

A publicação canônica hoje é Admin (`courses-modules-lessons.md:60-66,99-106`). Embora `learning.courses.publish` e a policy nominalmente incluam Instructor (`config/permissions.php:140-143`, `CoursePolicy.php:121-140`), essa combinação é uma contradição de contrato: não deve ser exposta numa nova rota sem decisão de produto. `archive` é terminal no MVP e não há operação Instructor de archive. Portanto publish/unpublish/archive são `HUMAN_DECISION_REQUIRED` para o boundary Instructor, não um gap que possa ser resolvido por inferência.

### Modules e Lessons

O fluxo estrutural Course → Module → Lesson está implementado no legacy com CRUD/reorder. As policies transitivas são a melhor parte da base atual: Module resolve Course owner e Lesson resolve Module → Course owner. O fechamento ainda exige:

- endpoints area-first e Resources dedicados;
- listagem de drafts/inativos para o owner, sem usar a árvore consumer que filtra conteúdo publicado;
- parent validation por contexto, inclusive IDs de outro Course do mesmo tenant;
- delete seguro, reorder com conjunto fechado do mesmo parent;
- decisão explícita para publish/unpublish de Lesson, atualmente exposto canonicamente ao Admin (`courses-modules-lessons.md:132-137`).

### Media

LessonMedia é relação 1:N, e a superfície legacy suporta múltiplas mídias e metadata (`Routes/api.php:92-97`). Não há Provider/adapter binário a implementar neste closure; o gap é superfície/ownership: list/show completo, create/update/delete próprios, URL/metadata permitidos e validação composta Lesson → Course → Instructor. Upload real e `MediaProvider` permanecem `LATER`/gate humano.

### Materials

CourseMaterial é distinto de LessonMedia. Hoje o legacy oferece apenas criação e download (`Routes/api.php:56-60`); o Controller usa CoursePolicy para criar, mas o contrato Instructor ainda não tem list/show/update/delete completos (`CourseMaterialController.php:32-74`). O slice deve manter `CourseMaterial.instructor_id` separado do Course Admin-owned e não converter material Admin em ownership Instructor.

## 6. Assessment

O contrato canonicalizado é claro: Instructor é owner pedagógico; Admin cria com `instructor_id = null`; Instructor acessa somente Assessment próprio e parents próprios; atribuição futura é explícita (`docs/specs/30-assessment/spec.md:64-75` e `docs/reports/ADMIN-DECISIONS-CANONICALIZATION-2026-09-06.md`).

### Questionnaire e Question

O código legacy ainda atribui o usuário autenticado em `instructor_id` na criação, o que é compatível com criação Instructor, mas não há proteção completa do parent. Listagem, show, update e delete são tenant-wide; Question update também sincroniza categorias sem comprovar que os IDs pertencem ao escopo permitido. Isso é `PARTIAL` para capacidade e **HIGH** para isolamento.

O mínimo Assessment básico para closure é:

- Questionnaire próprio ligado a Course/Lesson própria;
- Question própria, com categories disponíveis e sem taxonomia CRUD;
- list/show/update/delete own;
- attach/list de Questions em Questionnaire, se a regra de “gerir Assessment” exigir composição completa;
- imutabilidade após attempts concluídas;
- snapshots/scoring exclusivamente server-side;
- 404 defensivo/403 coerentes para outro tenant e outro Instructor.

O banco de questões Admin tenant-wide e Admin-created Assessment sem owner estão implementados e cobertos por E2E Admin, mas essa evidência não transfere acesso para Instructor (`docs/reports/ADMIN-CLOSURE-SLICE-5-2026-09-06.md:13-17,53`).

### Attempts, scoring e certificates

Instructor não cria, responde ou finaliza attempts; essas são operações do Student. Deve poder consultar somente attempts/results/certificates dos alunos dos seus Courses, caso essa leitura faça parte do mínimo pedagógico. Hoje não há endpoint de listagem de attempts por Course, e `ShowAttemptAction`/certificates filtram pelo aluno autenticado. Logo, resultados próprios são `MISSING`, não “já cobertos” pela permission `assessment.attempts.view`.

Quiz avançado/plugin não entra. O boundary core/advanced permanece gate humano no capability context.

## 7. Students / Progress

O contrato Learning já admite matrícula manual por Instructor, com free enrollment controlada por switch do tenant e `external` paid entrando como `pending` (`docs/specs/20-catalog-learning/spec.md:82-88`; `docs/reports/ADMIN-HUMAN-DECISIONS-2026-09-06.md:269-276`). Isso não autoriza visão tenant-wide.

### Boundary mínimo recomendado

| Operação | Target mínimo | Estado atual |
|---|---|---|
| Listar alunos | somente matrículas de Courses próprios | `MISSING`; lista atual é tenant-wide |
| Ver progresso | somente progresso de alunos em Courses próprios | `MISSING`; só há heartbeat Student |
| Ver attempts/results | somente Assessment/parent próprios | `MISSING` |
| Matricular free | permitir apenas se switch do tenant e Course próprio | `PARTIAL`, hoje sem owner check |
| Matricular paid external | `pending`, aprovação/reconciliação explícita | `HUMAN_DECISION_REQUIRED`/deferred |
| Remover/cancelar matrícula | Admin-only no contrato atual | `OUT_OF_SCOPE` para Instructor |

O Resource de Enrollment inclui user id, nome e tenant id (`EnrollmentResource.php:23-34`). Enquanto a query permanecer tenant-wide, esse dado é exposto fora do ownership pedagógico. O slice de roster deve filtrar Course owner antes de qualquer eager-load e limitar os campos de aluno ao contrato necessário.

## 8. Financial Boundary

Instructor não deve:

- configurar ou alterar gateway;
- iniciar checkout ou criar Order paga;
- confirmar manual cash;
- processar webhook/reconciliação;
- operar ledger, refund ou comissão.

O caminho Admin cash/manual e o checkout Student permanecem separados e já foram fechados no escopo Admin (`docs/reports/ADMIN-CLOSURE-SLICE-6-2026-09-06.md`; `docs/reports/ADMIN-AUTONOMOUS-WORK-2026-09-06.md:34-35,57-59`). Para Instructor, o único contato mínimo é consultar estado de matrícula/eligibility dos próprios Courses e, se mantida a decisão atual, iniciar matrícula manual free. `billing_type=external` para paid é uma oportunidade futura, não deve disparar gateway nem automação nesta closure.

Não há blocker de package: MediaLibrary já existe; nenhum package novo é necessário para resolver ownership. Pennant, Horizon, Pulse, Scout, laravel-pdf e laravel-data não devem substituir decisões de domínio.

## 9. Categories

O contrato System/Custom está fechado por ADR-002 e pela canonicalização Admin:

- System: global/MZRT; Instructor lê e pode usá-la como referência;
- Custom: pertence ao tenant e é gerenciada pelo Admin;
- Instructor: lista/lê categorias disponíveis, mas não cria, edita, exclui nem altera `category_course` na decisão atual (`docs/reports/ADMIN-HUMAN-DECISIONS-2026-09-06.md:137-149`; `docs/specs/20-catalog-learning/subspecs/catalog.md`).

O GET de categorias está presente em Learning legacy e não há rota Instructor de escrita. O risco atual é principalmente drift de metadata: `config/permissions.php` ainda concede permissions nominais de create/update/delete de categories a Instructor. Como as rotas de escrita estão Admin/MZRT, isso não confirma uma escalada ativa, mas é `MEDIUM` e deve ser corrigido/alinhado no slice de RBAC da superfície Instructor, sem abrir CRUD.

“Selecionar categoria ao editar Course próprio” não pode ser assumido como MUST: a decisão canônica mantém o vínculo Admin-only. Se o produto realmente exigir seleção pelo Instructor, será necessária decisão explícita e um contrato de associação próprio.

## 10. Security / RBAC / Tenant

### Confirmado

- tenant context e middleware existem;
- routes Admin usam stack e guard Admin corretos;
- checks arquiteturais estáticos focados passaram: `AreaRouteGuardTest` (1), `RouteSecuritySurfaceTest` (4/4), `ScribeAuthAnnotationMatchesMiddlewareTest` (1) e `PermissionDriftTest` (1);
- Course/Module/Lesson mutation legacy têm evidência de testes negativos de Instructor contra outro owner (`tests/Feature/Api/Learning/Rbac/InstructorOwnershipTest.php:46-242`);
- `Instructor` não acessa a área Admin canônica, conforme Feature/E2E Admin já reportados.

### Blockers de segurança

1. **Ownership ≠ tenant isolation:** tenant filtering sozinho deixa Instructor A operar/ver recursos B no mesmo tenant em Enrollment e Assessment.
2. **Legacy sem area guard:** `v1/learning` e `v1/assessment` não são superfícies area-first; não devem ser tratados como closure. Devem permanecer compatibility mapping ou ser convergidos gradualmente.
3. **Policy permissiva para read:** `CoursePolicy::list/show` e `EnrollmentPolicy::list/view` usam tenant/permission sem own scope para Instructor.
4. **Payload/parent validation:** Questionnaire `quizable_id`, Question categories e enrollment `course_id` precisam validar parent, tenant e owner no mesmo caminho.
5. **Error semantics:** a nova superfície deve usar 404 defensivo para recurso de outro tenant/owner quando essa for a convenção do endpoint, e envelope central para 401/403/404/422; não pode revelar existência por corpo diferente.

O permission ceiling continua necessário, mas não é suficiente. A matriz em `rbac.md:161-189` já descreve `own` para Learning e `view` para Enrollment/Assessment; o código legacy ainda aplica parte disso como simples permission tenant-wide.

## 11. Generic LMS Drift

**Risco consolidado: HIGH.**

| Drift | Risco | Evidência |
|---|---|---|
| Instructor tratado como Admin com menos permissions | HIGH | Actions legacy de Enrollment/Assessment usam tenant como limite principal; policies retornam por permission nominal |
| Permission substituindo ownership | HIGH | Enrollment list/show e Assessment list/update não relacionam recurso ao Course owner |
| Categories editáveis por Instructor | MEDIUM, latente | Permissions nominais ainda incluem Instructor, embora rotas de escrita atuais sejam Admin/MZRT |
| Assessment tenant-wide para Instructor | HIGH | Querys `where('tenant_id')` sem `instructor_id`/parent owner |
| Course sem ownership claro | MEDIUM | `instructor_id = null` é válido e correto para Admin, mas falta contrato de assignment e visibilidade Instructor explícita |
| Student data fora do Course próprio | HIGH | Enrollment query tenant-wide eager-loads user e Resource retorna nome/user id |
| Financial leakage | LOW no caminho atual; MEDIUM se expandido sem decisão | checkout/confirm estão separados, mas `external` Instructor paid ainda é pending/deferred |
| Conteúdo fora da árvore Course → Module → Lesson | MEDIUM | Assessment parent validation legacy não valida ownership/tenant do parent; novos endpoints devem fechar a árvore |

O drift não justifica abrir Student nem WS2/WS3; ele define os guardrails dos slices Instructor.

## 12. MUST / SHOULD / LATER

### MUST para `INSTRUCTOR_COMPLETE`

- superfície `/api/v1/instructor` com routes/controller/request/resource próprios e stack tenant-scoped exata;
- list/show de Courses próprios, incluindo drafts e estrutura de authoring;
- Course create/update/delete/preview próprios, sem `tenant_id`, owner, parent ou status spoofable;
- Module CRUD/reorder transitivo ao Course próprio;
- Lesson CRUD/reorder e lifecycle que for decidido para Instructor, transitivo ao Course próprio;
- LessonMedia metadata CRUD/list/show para Lesson própria; sem exigir MediaProvider;
- CourseMaterial CRUD/list/show/download conforme contrato próprio, distinto de LessonMedia;
- categoria read/list System + Custom do tenant, sem taxonomia CRUD;
- correção de Enrollment list/show/create para scope de Courses próprios; free enrollment somente no boundary decidido;
- roster de alunos próprios e leitura de progresso próprio;
- Assessment básico Questionnaire/Question próprio, com parent próprio e category IDs válidos;
- leitura de attempts/results/certificates de alunos próprios, se “visualizar progresso/alunos” incluir avaliação — requisito recomendado para closure pedagógico;
- isolamento explícito entre Instructor A/B no mesmo tenant e entre tenants;
- 401/403/404/422 em envelope canônico e 404 defensivo quando aplicável;
- Feature discriminante por endpoint, Architecture para rota/RBAC/controller/boundary e E2E HTTP com side effects;
- Scribe gerado e alinhado aos middlewares reais;
- receipt final com testes atuais, E2E e estado `RUNTIME_VERIFIED` somente se executado contra app/banco adequados.

### SHOULD

- operação de assignment/reassignment Admin → Instructor, com auditoria e sem auto-claim;
- filtros de roster/progresso por Course/Module/Lesson com paginação cursor;
- attach/detach/order completo de Questions em Questionnaire;
- download de CourseMaterial em superfície Instructor explicitamente documentada;
- unificar Resources administrativos de conteúdo e limitar PII de aluno ao necessário;
- alinhar permissions nominais de category/publish à semântica real, mantendo ceiling;
- relatório pedagógico agregado por Course, sem virar reporting tenant-wide.

### LATER

- MediaProvider/upload/proxy binário;
- matrícula paid `external` com aprovação/reconciliação;
- gateway, webhook, checkout operado por Instructor, refund e comissão;
- quiz avançado/plugin;
- restore de archived Course;
- Student surface e jornada de consumo própria;
- WS2/WS3 e packages deferred.

## 13. Human Decisions

Estas decisões não devem ser inventadas durante a implementação:

1. **Publish/unpublish de Course e Lesson pelo Instructor:** a documentação canônica chama publicação de Admin, mas permission/policy legacy incluem Instructor. Definir se Instructor apenas prepara conteúdo e Admin publica, ou se owner pode publicar próprio conteúdo.
2. **Assignment/reassignment:** quem atribui, se Admin pode reassign, o que ocorre com Course/Assessment/enrollment ao remover Instructor e se há auditoria/efeito de cache. Instructor não deve auto-claim.
3. **Category selection:** a decisão atual mantém alteração de `category_course` Admin-only; confirmar se “selecionar categorias” significa apenas ler/usar dados fornecidos pelo Admin ou criar associação própria no Instructor.
4. **Roster/progress/results:** confirmar o nível de detalhe de PII, progresso por aluno, attempts, resultados e certificados que o owner pedagógico pode ver.
5. **External enrollment:** confirmar se o Instructor pode iniciar paid external, quem aprova e qual ledger/evento conclui a matrícula. Até lá, manter pending/deferred.
6. **Assessment composition:** confirmar se attach/list/delete de Questions é obrigatório no MVP Instructor ou se Questionnaire/Question CRUD básico basta.

Não são decisões abertas: Instructor não ganha acesso a recurso Admin-owned sem atribuição explícita; Admin não vira Instructor; taxonomia CRUD e operações financeiras continuam fora.

## 14. Harness Readiness

| Item | Classificação | Estado |
|---|---|---|
| capability context Learning/Assessment | `BLOCKS_INSTRUCTOR` apenas como preparação de execução | Bundles existem e foram lidos; a implementação deve carregar esses contextos em cada slice. |
| routing/skill router | `DOES_NOT_BLOCK_INSTRUCTOR` | Router e mapa existem; skills de área/RBAC/security/API tests foram aplicadas ao targeting. |
| area guard probe | `BLOCKS_INSTRUCTOR` para closure | `AreaRouteGuardTest` passa para rotas existentes, mas nenhuma rota Instructor existe para provar guard exato. |
| ownership probes | `BLOCKS_INSTRUCTOR` | Há Feature de Course/Module/Lesson; faltam probes discriminantes para Enrollment, Assessment, roster, progress e attempts. |
| E2E | `BLOCKS_INSTRUCTOR` | Runner existe, mas não há spec `tests/e2e-http/instructor/*`. |
| Scribe | `BLOCKS_INSTRUCTOR` | Não há contrato Instructor gerado porque não há superfície canônica. |
| verify-changes | `DOES_NOT_BLOCK_INSTRUCTOR` agora; `BLOCKS_INSTRUCTOR` no fechamento de cada slice | Mapeará routes/policies/controllers aos testes Architecture quando os arquivos forem alterados. |
| validate-harness | `DOES_NOT_BLOCK_INSTRUCTOR` | Passou com um warning opcional: `.opencode/opencode.json` ausente; não abrir WS2 por isso. |
| runtime receipt | `BLOCKS_INSTRUCTOR` para declarar closure | Execuções anteriores de teste concorrente sofreram deadlock no banco compartilhado; não promover esse resultado a runtime evidence. |

Não há necessidade de alterar harness genérico nesta tarefa. O bloqueio é ausência de evidência específica Instructor, não uma falha do Codex harness.

## 15. Closure Slices

O menor conjunto é **4 slices verticais**. Cada slice deve nascer com RED discriminante e terminar com Feature + Architecture + Scribe; E2E é obrigatório para os fluxos que alteram ownership/estado.

| Slice | Objetivo e MUST fechado | Arquivos/áreas prováveis | Testes discriminantes | E2E | Risco/dependências |
|---|---|---|---|---|---|
| **I-01 — Instructor Learning own surface** | Criar área/guard/stack e entregar Course próprio list/show/create/update/preview, Module/Lesson CRUD/reorder, com ownership transitivo e payload não-spoofable. Fixar publish/unpublish conforme decisão humana antes de expor. | `Learning/Routes/instructor.php`, Controllers/Requests/Resources/Actions/Policies, provider, permissions; sem migrations | 401/403/404/422; A vs B same tenant; cross-tenant; Course null-owner invisível; parent spoof; area guard; controller leanness; module boundary | HTTP create Course → Module → Lesson; leitura/alteração própria; A/B e tenant B negados; side effects | Alto; depende da decisão de publish e da separação consumer/authoring |
| **I-02 — Instructor content attachments + roster** | LessonMedia e CourseMaterial metadata próprios; listar alunos/progresso próprios; corrigir matrícula free para Course owner; manter remove/cancel Admin-only. | Learning media/material/enrollment/progress Actions/Policies/Resources; contratos entre Learning/Assessment se necessário | múltiplas mídias; material distinto; Instructor A não alcança B; roster não vaza aluno; free enrollment só own; progresso de aluno own; PII/envelope | HTTP create media/material; matrícula free; consulta roster/progresso; side effects e isolamento | Alto; depende da decisão de PII/progresso e external enrollment deferred |
| **I-03 — Instructor Assessment basic/results** | Questionnaire/Question own; parent Course/Lesson own; categories válidas; attach/list questions; leitura própria de attempts/results/certificates quando aprovado. | Assessment Instructor routes/controllers/requests/resources/actions/policies; Learning Contracts; no model cross-module | A/B e cross-tenant; parent foreign rejeitado; Admin-owned null invisível; snapshot/scoring imutável; results só alunos próprios; category System/Custom read-only | HTTP criar Assessment básico em Course próprio, vincular Questions e consultar resultado próprio; negativas de parent/owner | Muito alto; depende de roster/results e decisão de composição/PII |
| **I-04 — Closure evidence and contract** | Consolidar Scribe, E2E journey, Architecture, receipt, mapping de legacy/deprecation e classificação de evidence. | `.scribe`, `tests/e2e-http/instructor`, `tests/Feature/Api/.../Instructor`, `tests/Architecture` apenas se necessário, relatório/roadmap quando autorizado | full targeted suite; verify-changes; no skips; envelope; permission/area/Scribe | jornada completa app real + DB, cleanup e receipt | Médio; depende de I-01–I-03 e app/banco E2E adequados |

O assignment/reassignment não é um quinto slice obrigatório para closure se o produto aceitar que Instructor só opere Courses já atribuídos e que Courses `instructor_id = null` permaneçam Admin-only. Se o onboarding Instructor exigir atribuição dentro do mesmo milestone, ele vira um slice adicional após decisão humana.

## 16. Recommended First Slice

Recomenda-se iniciar por **I-01**, com o menor vertical demonstrável dentro dele:

1. rota `GET /api/v1/instructor/courses` com guard exato e query owner-scoped;
2. `GET /api/v1/instructor/courses/{id}` para draft/authoring próprio;
3. negativos para outro Instructor, outro tenant e `instructor_id = null`;
4. Resource/envelope/Scribe e Feature test antes de expandir mutações;
5. só depois adicionar Course create/update e a árvore Module/Lesson no mesmo boundary.

Esse primeiro corte prova a diferença essencial entre tenant isolation e pedagogical ownership isolation. Também revela imediatamente se a lista/Resource existente pode ser reutilizada com segurança ou precisa de contrato Instructor próprio. Não deve começar por Student, Financial, package ou upload.

## 17. Final Verdict

`INSTRUCTOR_PARTIAL`

Há implementação legacy útil, principalmente para mutações Learning próprias, mas a área funcional ainda não existe como superfície canônica e os gaps de ownership em Enrollment/Assessment são blockers HIGH. O estado atual não sustenta `INSTRUCTOR_NEAR_COMPLETE` nem qualquer variante `INSTRUCTOR_COMPLETE`.

Para fechar, o projeto precisa dos 4 slices I-01–I-04, respeitando as decisões humanas listadas. A evidência atual deve ser considerada `STATIC_EVIDENCE_ONLY` para o diagnóstico; nenhuma capability Instructor é `RUNTIME_VERIFIED` nesta análise.

Esta análise foi read-only quanto ao produto. O único artefato deliberadamente criado é este relatório; não foram alterados specs, tasks, STATE, migrations, packages, Student, WS2, WS3 ou código de aplicação.
