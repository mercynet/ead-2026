# Student Commercial Gap Closure Targeting — 2026-09-08

## 1. Executive Summary

**Verdict atual: `STUDENT_PARTIAL`.**

O backend já possui peças funcionais de autenticação, catálogo legado, acesso por matrícula,
consumo de aula, mídia assinada, material, progresso, attempts com snapshot/scoring server-side,
certificados e matrícula manual. Porém, a superfície comercial canônica Student ainda não existe:
há somente `POST /api/v1/student/checkout` na área Student. O restante opera em rotas
domínio-first/legacy sem `area.guard:student`.

Estimativa do mínimo Student: **~35%** quando medida contra o contrato comercial; a cobertura
legada é material, mas a convergência canônica é praticamente zero. Confiança: **alta (0,88)**,
baseada em inspeção do código/rotas/testes e nos testes atuais, com confiança runtime limitada porque
nenhum E2E HTTP foi executado nesta discovery.

Os bloqueadores de receita são: superfície `/student`, lista própria de cursos, árvore de consumo
segura, conteúdo/material sem metadata interna, progresso próprio legível, Assessment condicional
com acesso ao Course pai, e uma jornada E2E HTTP integrada com side effects/cleanup.

## 2. Paid Pilot Student Boundary

O paid pilot é **assisted onboarding**: Admin/operator pode matricular manualmente e confirmar
pagamento cash/manual. Student não precisa fazer checkout ou administrar Enrollment nesta etapa.

O contrato mínimo é:

1. autenticar no tenant correto;
2. listar somente meus Courses acessíveis;
3. abrir Course e navegar Modules/Lessons publicados;
4. consumir conteúdo, mídia permitida e material com URL temporária;
5. persistir e consultar meu progresso;
6. iniciar, responder, finalizar e consultar resultado próprio quando o Course usar Assessment;
7. emitir/consultar certificado somente se ele for promessa comercial do Course.

Catálogo público não substitui “meus cursos”. Student self-enrollment, checkout automático,
gateway novo, upload, MediaProvider novo, quiz avançado, grading manual, ratings e marketplace
ficam fora do mínimo.

## 3. Current Surface

| Capability | Classificação | Evidência atual | Gap comercial |
|---|---|---|---|
| Auth neutral `/api/v1/auth/*` | `IMPLEMENTED_AND_CANONICAL` | `app/Modules/Core/Routes/api.php`; `AuthController`/`LoginAction` | É compartilhada e pronta para Student, mas ainda não prova a jornada Student completa. |
| Auth `/api/v1/core/auth/*` | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | Mesmo controller, middleware e throttling da superfície neutral | Manter compatibilidade; não usar como nova superfície Student. |
| Student area | `MISSING` | Só `Financial/Routes/api.php:16-20` registra `/api/v1/student/checkout`; Learning e Assessment providers não registram `student.php` | Bloqueador principal: faltam rotas, controllers/resources próprios e E2E canônico. |
| My Courses | `MISSING` | Não há `GET /api/v1/student/courses` | Não há lista própria, regra explícita de Enrollment/open course nem payload comercial. |
| Course tree | `PARTIAL` | `GET /api/v1/learning/courses/{courseId}/modules` e `GetCourseModulesAction` | Legacy sem guard; não filtra Course ativo nem módulo ativo; avaliação de acesso por Lesson cria N+1. |
| Lesson consumption | `PARTIAL` | `GET /api/v1/learning/lessons/{id}` e `LessonController` | Legacy; `LessonDetailResource` não retorna `content`; aula inativa pode devolver metadata com `can_access=false`. |
| LessonMedia | `PARTIAL` | `ResolveLessonMediaUrlAction`, URLs temporárias e `LessonMediaResource` | Autorização precede resolução na rota legacy, mas o Resource expõe `storage_path`/`metadata`; falta Student Resource seguro. |
| CourseMaterial | `PARTIAL` | Download scoped por tenant/course e URL temporária em `CourseMaterialController` | Não há listagem Student; `CourseMaterialResource` expõe `file_path` e `instructor_id` se reutilizado. |
| Progress | `PARTIAL` | `POST /api/v1/learning/lessons/{id}/progress`, `UpdateProgressAction` | Escrita própria e idempotência existem; falta rota Student, leitura/aggregate comercial e prova E2E integrada. |
| Assessment attempt | `PARTIAL` | Start/answer/finish/show e Feature tests | Legacy sem guard Student; start não valida Enrollment/acesso ao Course pai; `show_results` não é aplicado no Resource. |
| Results own | `PARTIAL` | Actions de show filtram `user_id`; scoring usa snapshot server-side | Falta Student route/E2E e escopo explícito de tenant nos queries de attempt, hoje coberto por allowlist estática. |
| Certificates | `PARTIAL` | Issue listener, list/show próprio e verify público | Sem área Student; PDF/revogação/eventos continuam pendentes; runtime atual não confirmado. |
| Enrollment access | `IMPLEMENTED_LEGACY_NEEDS_CONVERGENCE` | Admin/Instructor manual enrollment, status/expiry e listener `OrderPaid` | Conceder acesso manualmente é suficiente para pilot; leitura Student precisa ser própria e canônica. |
| Student checkout | `IMPLEMENTED_AND_CANONICAL` | `POST /api/v1/student/checkout` com guard exato | Não fecha consumo; gateway/checkout pago automático não é requisito do pilot assistido. |

Evidência executada nesta auditoria: invariantes arquiteturais, 19 testes/703 assertions, e testes
Feature legacy Learning/Assessment/checkout, 124 testes/662 assertions, passaram. Isso é
`TEST_VERIFIED` da implementação existente, não `RUNTIME_VERIFIED` da jornada comercial.

## 4. Auth / Area / Tenant

Auth está funcional para um Student: login resolve tenant pelo contexto, verifica membership/status,
usa token Sanctum opaco e mantém throttling. `User` aplica o teto efetivo de permissions por
`user_type`; Student não pode elevar seu próprio papel.

O blocker é de superfície, não de login:

- não existe `/api/v1/student/courses`, `/student/lessons`, `/student/progress`, `/student/attempts`,
  `/student/results` ou `/student/certificates`;
- Learning e Assessment carregam as rotas somente como `/api/v1/learning/*` e
  `/api/v1/assessment/*`, sem `area.guard:student`;
- o único caminho área-first Student é checkout;
- a stack canônica precisa ser `resolve.tenant.optional`, `api.context`, `auth:sanctum`,
  `area.guard:student`, `tenant.required.unless.developer`, `tenant.access`, na ordem compatível
  com o contrato;
- os testes atuais de guard estão verdes porque não há novas rotas Student de consumo para falhar.

Há isolamento explícito por `tenant_id` nas principais Actions Learning. O novo Student deve usar
404 defensivo para Course/Lesson/Enrollment/Attempt que não pertençam ao usuário/tenant, não apenas
um 403 que permita inferir existência.

## 5. My Courses

`GET /api/v1/learning/enrollments` é administrativamente/legacy e não é uma lista Student:
`learning.enrollments.list` não inclui Student em `config/permissions.php`, e
`ListEnrollmentsAction` só filtra `user_id` quando o request fornece esse campo
(`app/Modules/Learning/Actions/Enrollment/ListEnrollmentsAction.php:30-42`). Se essa permission for
simplesmente adicionada ao Student, o request poderá enumerar matrículas do mesmo tenant. Isso é um
blocker de ownership.

O alvo mínimo é uma Action própria que fixe o usuário autenticado no servidor, use
`cursorPaginate`, carregue somente um resumo do Course/progresso e aceite somente:

- Enrollment `active` e não expirada para Course pago;
- regra explicitamente decidida para Course gratuito/open;
- nenhum `pending`, `cancelled` ou `expired` como acesso comercial ativo.

`ListCoursesAction` é catálogo, não “my courses”: ele lista somente `published` e remove cursos já
enrolled para usuários não-developer. `ShowCourseAction` filtra `published`, mas não verifica
`is_active`. Portanto nenhum dos dois é suficiente como Student own surface.

Decisão necessária: se Course gratuito sem Enrollment entra em “meus cursos” ou apenas no catálogo
e se deve existir Enrollment ativa mesmo para acesso gratuito. A spec permite Course/Lesson free
aberto, mas o boundary comercial exige uma regra explícita.

## 6. Consumption

A regra de acesso existente é boa como base: Course publicado/ativo, Lesson publicada/ativa, Lesson
free aberta e conteúdo pago dependente de Enrollment ativa e não expirada
(`EvaluateCourseAccessAction.php:12-53`). Cancelled/expired não deve acessar conteúdo pago.

A convergência precisa fechar:

- Course detail Student com status/publication e resumo de Enrollment/progresso próprios;
- Modules/Lessons em `sort_order`, somente publicadas/ativas e pertencentes ao Course pai;
- 404 defensivo para draft, archived/inactive e foreign tenant, em vez de devolver metadata com
  `can_access=false`;
- conteúdo textual efetivamente presente: `LessonDetailResource` atualmente retorna título,
  slug, mídia e progresso, mas não `content` (`LessonDetailResource.php:22-55`);
- nenhum authoring metadata, ownership de Instructor ou tenant foreign.

`GetCourseModulesAction.php:23-50` filtra tenant/Course, mas não filtra Course ativo nem módulo
ativo e chama a avaliação de acesso novamente para cada Lesson. É reaproveitável somente após
adaptar o contrato Student e eliminar o risco de vazamento/N+1.

## 7. Media / Materials

O mínimo não requer upload nem MediaProvider novo. A implementação existente já gera URL temporária
para mídia interna/S3 e preserva URL externa, e a rota legacy resolve URL somente após validar o
acesso (`LessonController.php:72-97`). Material download também verifica Course/tenant/access
antes de criar a URL temporária (`CourseMaterialController.php:48-74`).

Há duas correções obrigatórias na superfície Student:

- `LessonMediaResource` inclui `provider_config.storage_path` e `metadata`; um Student Resource deve
  devolver apenas o contrato de consumo necessário, nunca path/configuração interna;
- `CourseMaterialResource.php:12-18` devolve `file_path` e `instructor_id`; não deve ser reutilizado
  para leitura Student. A resposta comercial deve expor identificador, nome/tipo se houver, e
  download URL temporária somente depois da autorização.

Material listado/baixado precisa continuar limitado ao Course correto, tenant correto e Enrollment
ou regra open decidida. Upload e lifecycle de MediaProvider são `DEFERRED`/`HUMAN_DECISION_REQUIRED`.

## 8. Progress

`UpdateProgressAction` já é a melhor peça reaproveitável: grava `tenant_id`, `user_id`, Course,
Enrollment e Lesson próprios; usa transaction/lock; impede regressão do progresso; atualiza
LessonMediaProgress; recalcula aggregate de Enrollment; dispara eventos de conclusão após commit.
Os testes legacy cobrem heartbeat, conclusão, replay/idempotência, mídia, invalidez e isolamento.

Ainda bloqueiam o pilot:

- endpoint canônico Student para heartbeat/update;
- endpoint ou projeção canônica para visualizar progresso próprio e aggregate Course;
- Feature de Student A vs Student B e cross-tenant na nova superfície;
- E2E com assert de side effect em LessonProgress/Enrollment progress;
- resolver a diferença entre `currentStatuses()` em `UpdateProgressAction.php:29-38` (inclui
  `pending`) e a regra comercial de acesso pago, que exige Enrollment `active`.

Rewatch/replay deve continuar permitido sem reduzir o maior progresso já salvo. Para o pilot não é
necessário um serviço de analytics ou progresso avançado.

## 9. Assessment

O núcleo básico está parcialmente pronto:

- `StartAttemptAction` congela questions/options/gabarito no servidor;
- Student envia apenas `question_id` e opções selecionadas;
- `FinishAttemptAction` calcula score/pass-fail a partir do snapshot;
- show/answer/finish restringem a tentativa ao `user_id` autenticado;
- Features cobrem snapshot, gabarito não exposto, forged answer key, duplicidade, score e outro
  usuário.

Os blockers são reais e condicionais ao Course usar Assessment:

- rotas são `/api/v1/assessment/attempts/*`, sem `area.guard:student`;
- `StartAttemptAction.php:23-26` valida tenant do Questionnaire, mas não valida que o
  `quizable` Course/Module/Lesson está publicado/ativo nem que o Student tem Enrollment/acesso;
- queries de show/answer/finish usam `user_id`, mas não incluem `tenant_id`; a exceção está na
  allowlist de `TenantScopingTest`, não em um contrato Student explícito;
- `AttemptResource`/`AttemptAnswerResource` devolvem score/pass e `is_correct`; a configuração
  `show_results` do snapshot não é aplicada para esconder resultado quando configurada como false;
- não existe resultado Student canônico nem E2E HTTP Student start → answer → finish → result.

Antes de vender um Course com quiz, fechar somente o core simples: parent access, own attempt,
snapshot, scoring server-side, pass/fail e política explícita de `show_results`. Quiz avançado,
randomização, banco de questões avançado, grading manual e complementares ficam fora.

## 10. Certificates

Certificado **não deve bloquear todo primeiro piloto por padrão**. Um Course sem certificado pode
ser vendido no paid pilot; se a oferta comercial não o promete, a decisão é `SHOULD`/deferred.

Se `certificate_enabled` estiver ligado ou a oferta prometer certificado, ele vira
`MUST_FOR_PAID_PILOT` para aquele Course. A emissão atual reage a `CourseCompletedEvent`, respeita
progresso mínimo e, quando configurado, exige attempt aprovado (`IssueCertificateAction.php:13-49`).
List/show já filtram o usuário autenticado e usam cursor pagination.

Não estão fechados como runtime comercial: rota Student, download/PDF, revogação/eventos e uma
prova E2E da emissão após progresso; o caminho de quiz necessário para certificado também depende da
correção de access descrita na seção Assessment. Verificação pública por número pode permanecer
separada e deve ser uma decisão consciente de PII, pois devolve nome do usuário.

## 11. Enrollment / Access

O onboarding assistido pode operar assim:

1. Admin/operator cria e publica Course;
2. Instructor/Admin cria conteúdo;
3. Admin ou Instructor autorizado cria Enrollment manual/free/cash;
4. somente Enrollment ativa e não expirada libera conteúdo pago;
5. Student apenas lê sua matrícula/progresso e consome.

Matrícula manual e listener `OrderPaid` já existem como base. Checkout Student e gateway automático
não são necessários para receita assistida v0.1; pagamento externo/reconciliação permanecem
deferred. Student não deve criar, alterar, cancelar ou mudar status de Enrollment.

Pontos a fechar na implementação canônica: escolher a matrícula ativa mais recente de forma
determinística, tratar pending como sem acesso pago, bloquear expiry/cancelled, e evitar que um ID
de Course/Enrollment force escopo diferente do tenant/usuário autenticado.

## 12. PII / Ownership

Auth/User possui teto de permission, tenant membership e campos sensíveis ocultos na serialização
base (`app/Modules/Core/Models/User.php:80-187`); a política LGPD/activitylog já é cross-cutting.
Attempts e Certificates próprios usam `user_id` no Action. Isso é uma base, não uma prova do novo
contrato.

Blockers de ownership/PII:

- não habilitar `learning.enrollments.list` para Student reutilizando `ListEnrollmentsAction`,
  pois o Action aceita filtro de `user_id` controlado pelo request e carrega User/árvore;
- não reutilizar `EnrollmentResource` sem revisar campos como `created_by_instructor_id` e relações
  `user`/`tenant_id` (`EnrollmentResource.php:12-34`);
- `showById` legacy encontra Enrollment por ID tenant-scoped e depois lança 403 para Student de
  outro usuário (`EnrollmentController.php:94-104`), em vez do 404 defensivo exigido;
- attempts devem acrescentar escopo tenant explícito no contrato Student, mesmo que `user_id` seja
  atualmente mais estreito para usuários tenant-bound;
- CourseMaterial `file_path`, LessonMedia `storage_path/metadata`, Instructor ownership e
  relações internas não podem entrar em Resources Student.

Testes necessários: Student A/B no mesmo tenant, Student A contra tenant B, IDs enumeráveis,
parent foreign, material/media foreign, attempt foreign e certificate foreign. Nenhum campo novo de
PII é necessário para o mínimo.

## 13. Performance

Blockers de performance para o pilot são limitados, mas existem na reutilização direta da legacy:

- `GetCourseModulesAction.php:45-50` avalia acesso por Lesson; cada avaliação pode consultar a
  Enrollment atual, gerando N+1 conforme a árvore cresce;
- `ListEnrollmentsAction.php:19-27` eager-loads Course → Modules → Lessons para cada Enrollment,
  payload e custo inadequados para uma lista “my courses”;
- Course tree deve carregar progresso próprio em lote, não resolver enrollment por Lesson;
- lista Student deve usar `cursorPaginate`, payload resumido e Course tree/lesson detail separados.

Uma Lesson individual e URLs temporárias são aceitáveis para o pilot. Não há blocker para CDN,
cache amplo, busca, analytics ou MediaProvider novo nesta etapa; são otimizações posteriores.

## 14. Commercial E2E

Ao final deve existir uma spec E2E HTTP integrada, contra app real e DB descartável, cobrindo:

1. provisionar tenant;
2. Admin criar/configurar Course;
3. Instructor adicionar Module/Lesson/content/media/material;
4. Admin publicar Course;
5. Admin/operator matricular Student, inclusive cenário manual/cash;
6. Student autenticar em `/api/v1/auth/login` com tenant;
7. Student listar somente seu Course;
8. Student abrir Course/tree e verificar ordem/publication/access;
9. Student abrir Lesson e receber content/media permitidos sem paths internos;
10. Student atualizar progresso e verificar LessonProgress/Enrollment aggregate;
11. se aplicável, iniciar Assessment;
12. responder/finalizar e verificar resultado próprio, score server-side e pass/fail;
13. Instructor consultar progresso/result próprio do Course sem receber contrato Admin;
14. cleanup/provenance: zero órfãos e evidência do tenant/IDs usados.

O runner existente (`app/Console/Commands/E2eRunCommand.php:59-141`) já exige ambiente local/testing/e2e,
recusa DB não descartável, possui canário de alinhamento, HTTP externo, dois tenants e falha cleanup.
As specs Student atuais são isoladas e legacy (`tests/e2e-http/learning/student-*.php`); não há spec
canônica/integrada com `/student/*`.

## 15. MUST / SHOULD / MANUAL / DEFERRED

### MUST_FOR_PAID_PILOT

- `/api/v1/student/*` canônico para my courses, Course/tree, Lesson/content, material download e
  progress;
- stack de middleware e `area.guard:student` exatos;
- own scope, tenant isolation, 404 defensivo e Resources sem authoring/internal metadata;
- active/published Course/Lesson e Enrollment active/non-expired para conteúdo pago;
- leitura de progresso próprio e aggregate suficiente para a oferta;
- Feature + Architecture + E2E HTTP + Scribe para a jornada Student;
- Assessment core somente quando algum Course vendido o exigir: start/access, answer, finish,
  result próprio, snapshot/scoring server-side e `show_results` coerente;
- certificado somente quando explicitamente prometido ou habilitado para o Course;
- receipt atual da jornada contra app/DB corretos, side effects e cleanup.

### SHOULD_BEFORE_PAID_PILOT

- endpoint dedicado de matrícula própria/read projection, se o Course detail não carregar essa
  informação;
- progress aggregate otimizado e contrato uniforme de resume/rewatch;
- Student certificate list/show se certificados não forem vendidos no primeiro cohort;
- hardening de N+1 antes de cadastrar Courses grandes;
- documentação Scribe refinada para filtros e exemplos de Student.

### CAN_OPERATE_MANUALLY

- cobrança externa/cash/manual e confirmação Admin;
- criação/cancelamento de Enrollment por Admin/Instructor autorizado;
- onboarding/invitation e provisionamento de tenant;
- suporte operacional para reenrolamento, expiry e emissão manual apenas se a promessa comercial
  não exigir automação imediata.

### DEFERRED_AFTER_REVENUE

- Student self-enrollment e checkout/gateway automático completo;
- webhook pago/reconciliação externa se o pilot usar confirmação manual;
- MediaProvider/upload/library de mídia;
- quiz avançado, randomização, grading manual, banco de questões avançado;
- ratings, marketplace, plugins, analytics, CDN/cache amplo, PDF/revogação avançada de certificado;
- WS2/WS3 e capabilities avançadas.

## 16. Human Decisions

`HUMAN_DECISION_REQUIRED` antes de implementar:

1. Course gratuito sem Enrollment é “my course” ou apenas catálogo/preview?
2. O primeiro Course pago terá Assessment? Se sim, qual tipo mínimo e `show_results`?
3. Certificado está na promessa de venda do primeiro pilot? Quais Courses têm `certificate_enabled`?
4. Se certificado for prometido, PDF/download e verificação pública entram no v0.1?
5. Questionnaire pode ser Course, Module ou Lesson para o primeiro pilot, e como cada parent prova
   Enrollment/access?
6. Pending Enrollment deve ser sempre sem consumo/progresso ou há fluxo explícito de preview?
7. Quais campos de content/material/media são parte do contrato Student, especialmente para
   providers internos/S3, sem expor paths?

Sem essas respostas, o alvo seguro é o pilot assistido com Course pago, Enrollment ativa manual,
Assessment/certificado opcionais por Course e sem checkout automático.

## 17. False-Success Risks

- Feature verde da rota `/learning/*` ser tratada como Student pronto;
- checkout área-first ser confundido com área Student completa;
- adicionar permission de listagem ao Student e abrir enumeração de matrículas do tenant;
- usar catálogo público como “meus cursos”, omitindo enrolled courses e estados de access;
- aceitar Course/Lesson draft, archived ou inactive porque o endpoint devolve `can_access=false`;
- retornar `content`/media sem autorização ou devolver `file_path`, `storage_path`, `metadata` e
  ownership de Instructor;
- aceitar `pending` no progresso quando o conteúdo pago exige active;
- Assessment Feature provar score, mas não provar parent Course access, guard de área ou E2E HTTP;
- `show_results=false` existir na configuração, mas score/is_correct continuar na resposta;
- queries de attempt/certificate parecerem isoladas por usuário e não terem tenant explícito;
- certificado ter código/Feature/histórico E2E, mas não haver receipt atual da emissão no app correto;
- specs E2E individuais passarem sem uma jornada Admin → Instructor → Enrollment → Student;
- Scribe gerar documentação de legacy e nenhum contrato `/student/*` estar documentado;
- cleanup não validar órfãos, ou runner/app apontarem para bancos diferentes.

## 18. Harness Readiness

O harness suficiente para Student está **parcialmente pronto**:

- `AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest`,
  `TenantIsolationSmokeTest`, `TenantScopingTest`, permissions e PII passaram nesta sessão;
- o router/capability context cobre Learning, Assessment, Financial e a persona Student nas regras,
  mas não há bundle dedicado “Student commercial” isolado;
- o runner E2E tem os guards de DB descartável, canário HTTP, dois tenants e cleanup failure;
- existem E2Es Student legacy para modules/lesson/progress, mas nenhum `/api/v1/student/*` nem fluxo
  integrado;
- não há receipt runtime atual Student nesta task e não foi executado E2E HTTP;
- Scribe está protegido por teste de annotation/middleware, mas a ausência de rotas Student significa
  ausência de documentação Student a validar.

Não há motivo para abrir WS2: somente os gaps de Student, Scribe, Architecture, E2E e receipts que
bloqueiam o paid pilot devem entrar nos slices.

## 19. Closure Slices

O menor plano é **3 slices se o primeiro Course usar Assessment/certificado; 2 slices se não usar**.
Para manter um alvo único conservador, seguem 3:

### Slice 1 — Student own access surface

**Objetivo:** criar a superfície `/api/v1/student` para my courses, Course detail/tree e projeção de
Enrollment própria.

**Fecha:** auth/tenant/guard, Course active/published, Enrollment active/expiry, free/open decision,
own scope, cursor pagination, ordem Module/Lesson, 401/403/404/tenant isolation, Resources e Scribe.

**TDD applicability:** Feature para happy/unauth/area/ownership/status; Architecture para guard,
tenant/permission/controller/envelope; E2E HTTP Student A/B + cross-tenant para list/open.

**Dependências:** decisão de free/open e pending; Admin/Instructor upstream já entregue.

**Risco:** contrato de payload e reaproveitamento de `ListEnrollmentsAction` podem reabrir ownership.

### Slice 2 — Consumption, media/material and progress

**Objetivo:** Student Lesson/content/media, material download autorizado e update/read progress.

**Fecha:** media/material URL temporária, sanitização de paths/metadata, Lesson published/active,
heartbeat/completion/rewatch, aggregate próprio, idempotência e N+1 do tree.

**TDD applicability:** Feature de access states, side effects e A/B/cross-tenant; Architecture para
surface/resource/controller; E2E HTTP com download/progresso e asserts DB.

**Dependências:** Slice 1 e decisão de contrato de content/media.

**Risco:** `LessonDetailResource` atual não contém content; usar Resources legacy pode vazar internals.

### Slice 3 — Conditional Assessment/certificate and commercial evidence

**Objetivo:** fechar Assessment básico e certificado somente para a oferta que os promete, e selar
a jornada comercial integrada.

**Fecha:** parent access/enrollment, Student attempt routes, result policy/`show_results`, scoring
server-side, own/tenant scope, certificate trigger/list/show/download se decidido, Scribe, E2E
Admin → Instructor → enrollment → Student → result/certificate → cleanup e receipt.

**TDD applicability:** Feature de start/answer/finish/result/certificate; Architecture para guard,
boundary e Scribe; E2E HTTP real com side effects e sem mocks.

**Dependências:** decisão humana de Assessment/certificate; Slices 1–2; app/DB E2E disponível.

**Risco:** parent polymorphic Questionnaire e promessa de certificado expandirem o escopo.

## 20. Timeline

Partindo de 2026-09-08, com uma pessoa focada, upstream Admin/Instructor estável, decisões humanas
rápidas e ambiente E2E disponível:

| Cenário | Data alvo para `PAID_PILOT_READY` | Premissa |
|---|---:|---|
| Best case | **2026-09-22** | Dois slices sem Assessment/certificado ou core simples reaproveitado, sem regressão de contrato. |
| Realistic | **2026-10-06** | Três slices, correções de access/Resources/N+1, Scribe, E2E integrado e uma rodada de hardening. |
| Conservative | **2026-10-27** | Decisões atrasadas, parent Assessment/certificado incluído, regressões legacy ou falha de ambiente/receipt. |

Principal risco: o Student não está “quase pronto” apenas porque as Actions legacy existem; a maior
incerteza é a convergência segura de access/ownership/payload e a primeira prova E2E integrada.

## 21. Recommended First Slice

Começar pelo **Slice 1 — Student own access surface**. Ele resolve o blocker estrutural que torna
todos os testes legacy insuficientes: `/api/v1/student/*`, guard exato, own Course list, Course/tree,
Enrollment/access e contrato de Resources. Antes de escrever código, registrar as decisões de
free/open, pending e o payload mínimo de Course/tree; depois transformar a lista de casos em Feature,
Architecture e E2E.

Não começar por checkout, certificate ou quiz: sem a superfície própria e a prova de ownership, essas
capacidades produziriam outra evidência parcial.

## 22. Final Verdict

`STUDENT_PARTIAL`

O mínimo comercial ainda não está pronto para `PAID_PILOT_READY`. O pilot pode continuar com
onboarding manual e Course sem Assessment/certificado prometido, mas só depois de fechar os Slices 1
e 2 e obter E2E/runtime receipt atual. Se houver Assessment ou certificado na oferta, o Slice 3 é
obrigatório antes de vender aquele Course.

Estado de evidência: **`TEST_VERIFIED` para peças legacy e invariantes executadas; `STATIC_EVIDENCE_ONLY`
para targeting; `UNVERIFIED` para a jornada Student comercial runtime**.
