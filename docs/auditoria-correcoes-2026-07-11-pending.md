# Auditoria de Varredura — Plano de Correção

> **Status: PENDING** · Gerado em 2026-07-11 · Branch `harness/specs-foundation` (working tree, base `ca8f9fb`)
> Origem: varredura completa (docs + implementação) com verificação empírica — suite 297/299 verde,
> phpstan 394 erros, 4 auditorias paralelas (arquitetura, domínios, Learning, Core/Financial/Assessment).
> Achados P0 verificados em primeira mão (file:line conferidos manualmente).
> Remover este arquivo (ou renomear sem `-pending`) quando tudo abaixo estiver resolvido.

## Como usar

Cada item tem: severidade, evidência (file:line), correção objetiva e validação exigida.
Ordem de ataque: P0 → P1 → P2. Não misturar itens num mesmo commit/task.

---

## P0 — Crítico (corrigir antes de qualquer outra entrega)

### P0.1 Scoring de quiz é forjável pelo cliente — ✅ CORRIGIDO (2026-07-11)

> Snapshot de questões congelado no servidor em `StartAttemptAction` (`questions_snapshot`);
> `PATCH /attempts/{id}` aceita só `question_id` + `selected_options`; score calculado do
> snapshot do servidor (inclui fix do `maxPoints=0` em `FinishAttemptAction`); gabarito removido
> dos Resources. Bônus: fix de `QuizAttemptPolicy::create` que checava `attempts.view`.
> Cobertura: `tests/Feature/Api/Assessment/AttemptApiTest.php` (13 testes).

- **Evidência**: `app/Modules/Assessment/Http/Requests/SubmitAnswerRequest.php:21-22` exige
  `question_snapshot.correct_options` e `question_snapshot.points` **do request**;
  `app/Modules/Assessment/Actions/Attempt/SubmitAnswerAction.php:36-40` calcula
  `is_correct`/`points_earned` a partir desses campos.
- **Cenário de falha**: aluno envia `selected_options=[0]` + `correct_options=[0]` + `points=999`
  → sempre correto, pontuação arbitrária, `finish` aprova. Anula a integridade do Assessment.
- **Correção**: congelar snapshot das questões (com gabarito) **no servidor** em
  `StartAttemptAction`, a partir do questionário; `PATCH /attempts/{id}` passa a aceitar somente
  `question_id` (ou índice do snapshot) + `selected_options`. Remover `question_snapshot` do
  FormRequest. A spec já promete isso (`docs/specs/30-assessment/spec.md` — "ao iniciar, congela
  cada questão").
- **Validação**: Feature test provando que request com gabarito forjado é ignorado/422 e que o
  score sai do snapshot do servidor.

### P0.2 URL assinada cross-tenant via `file_path` de CourseMaterial — ✅ CORRIGIDO (2026-07-11)

> Hardening portado de `ResolveLessonMediaUrlAction`: `StoreCourseMaterialRequest` valida
> `file_path` (`starts_with:tenants/{tenant_id}/`, `not_regex` para `..` e `\`);
> `GenerateCourseMaterialDownloadUrlAction` revalida path persistido contra o tenant do material +
> allowlist de disk antes de assinar (422); controller gera URL antes de registrar o download.
> Cobertura: datasets negativos em `CourseMaterialApiTest` e `CourseMaterialDownloadApiTest`.

- **Evidência**: `app/Modules/Learning/Http/Requests/Course/StoreCourseMaterialRequest.php:17`
  valida `file_path` só como `required|string|max:2048`;
  `app/Modules/Learning/Actions/Course/GenerateCourseMaterialDownloadUrlAction.php:27` assina o
  path cru no disco default.
- **Cenário de falha**: instrutor do tenant A grava `file_path="tenants/B/materials/x.pdf"` e
  `POST .../downloads` devolve URL temporária válida para arquivo de outro tenant (ou qualquer
  chave do bucket).
- **Correção**: portar o hardening já existente em
  `app/Modules/Learning/Actions/Lesson/ResolveLessonMediaUrlAction.php:95-108` (allowlist de disk,
  prefixo obrigatório `tenants/{tenant_id}/`, regex de charset, rejeição de `..` e `\`) para o
  fluxo de materiais — na entrada (FormRequest) e na geração da URL (defesa em profundidade).
- **Validação**: testes negativos de path traversal e prefixo de outro tenant (espelhar
  `LessonMediaApiTest.php:187-231`).

---

## P1 — Alto (quebra funcional ou autorização errada)

### P1.1 `access_days = 0` (vitalício) nasce expirado — ✅ CORRIGIDO (2026-07-11)

> `EnrollStudentInCourseAction`: `access_days` 0/null → `access_expires_at = null`;
> `StoreCourseRequest`/`UpdateCourseRequest` validam `access_days` contra os presets
> (0/30/90/180/365). Testes em `EnrollmentApiTest` (vitalício ativo) e `CourseCrudApiTest`.

- **Evidência**: `app/Modules/Learning/Actions/Enrollment/EnrollStudentInCourseAction.php:51` —
  `access_days === null ? null : now()->addDays($course->access_days)`; com `0`,
  `access_expires_at = now()` e `Enrollment::isActive()` devolve `false` de imediato.
- **Spec**: preset `0 = vitalício` (`docs/specs/20-catalog-learning/subspecs/courses-modules-lessons.md`).
- **Correção**: `0` (e `null`) → `access_expires_at = null`. Validar `access_days` contra a lista
  fechada de presets (30/90/180/365/0) em `StoreCourseRequest`/`UpdateCourseRequest`.
- **Validação**: teste de matrícula em curso `access_days=0` com acesso a conteúdo pago OK.

### P1.2 `QuizAttemptPolicy::create` checa permissão errada — ✅ CORRIGIDO (2026-07-11, junto do P0.1)

- **Evidência**: `app/Modules/Assessment/Policies/QuizAttemptPolicy.php:13,21` — `create()` checa
  `assessment.attempts.view`. No config, `.create` é `[developer, student]`, `.view` inclui
  admin/instructor → admin/instructor iniciam tentativa sem ter `.create`.
- **Correção**: checar `assessment.attempts.create`.
- **Nota de guard**: `PermissionDriftTest` não detecta "permissão errada checada" (só valida
  usado ⊆ declarado). Avaliar teste por-endpoint da matriz RBAC.

### P1.3 Certificados: emissão inexistente + relação quebrada

- **Evidência**: nenhum `Certificate::create` no código; Assessment não registra listener para
  `LessonCompletedEvent`; `CourseCompletedEvent` não existe.
  `app/Modules/Assessment/Models/Certificate.php:43` define `belongsTo(Course::class)` mas a
  migration de `certificates` **não tem coluna `course_id`** → `->with('course:id,title')` em
  List/Show/VerifyCertificateAction resolve sempre `null`.
- **Correção mínima** (antes do fluxo automático): adicionar `course_id` (ou derivar de
  `enrollment->course`) e corrigir os 3 Actions de leitura. Fluxo automático de emissão é task de
  domínio (depende de P1.5 e de `CourseCompletedEvent`).
- **Validação**: verificação pública retorna `course_title` real.

### P1.4 `LessonPolicy` fora do padrão de autorização

- **Evidência**: `app/Modules/Learning/Policies/LessonPolicy.php:10-38` usa
  `$user->tenant_id === $tenant->id` cru — developer (tenant_id null) recebe 403 em todos os
  endpoints de lesson; matriz `learning.lessons.*` de `config/permissions.php:160-188` nunca é
  consultada (decide por `!isStudent()`).
- **Correção**: alinhar ao padrão das demais policies (developer bypass → `belongsToTenant` →
  permission do config → match de tenant do recurso).
- **Validação**: adicionar casos `UserType::Developer` em `LessonApiTest`/`ModuleApiTest` (hoje: zero).

### P1.5 Progresso de curso: denominador conta drafts e `completed_at` nunca é escrito — ✅ CORRIGIDO (2026-07-11)

> `UpdateProgressAction::updateEnrollmentProgress`: numerador e denominador filtram lessons
> `published + is_active`; `completed_at` estampado (set-once) ao atingir 100%. Testes em
> `LessonApiTest` (draft/inativa fora do denominador; asserção antiga de `completed_at` null
> atualizada — documentava o bug).

- **Evidência**: `app/Modules/Learning/Actions/Lesson/UpdateProgressAction.php:270-272` conta
  todas as lessons (sem filtro `published`/`is_active`); `enrollment.completed_at` existe no
  schema e nunca é escrito.
- **Cenário**: 8 aulas publicadas + 2 drafts → aluno trava em 80%, nunca elegível a certificado
  com `certificate_min_progress=100`.
- **Correção**: denominador = lessons `published + is_active`; setar `completed_at` ao atingir
  100% (spec `enrollment-progress.md`).

### P1.6 Isolamento de tenant é 100% convenção manual

- **Evidência**: spatie/multitenancy só carrega contexto (`config/multitenancy.php` —
  `switch_tenant_tasks` todo comentado); nenhum global scope/trait; isolamento =
  `where('tenant_id')` repetido por Action; `EnsureTenantAccess.php:29` valida usuário↔tenant,
  nunca recurso↔tenant; backstop = 1 sonda (`TenantIsolationSmokeTest`).
- **Risco**: cada Action nova é uma chance de vazamento cross-tenant. Assessment já diverge
  (`SubmitAnswerAction.php:25`/`FinishAttemptAction.php:21` filtram só `user_id`).
- **Correção (decisão de arquitetura — ADR)**: introduzir trait `BelongsToTenant` com global
  scope (+ `creating` hook) nos models tenant-scoped, mantendo os `where` explícitos como defesa
  em profundidade. Alternativa mínima: ampliar `TenantIsolationSmokeTest` para matriz por-recurso.

---

## P2 — Médio

| # | Problema | Evidência | Correção |
|---|---|---|---|
| P2.1 | Catálogo esconde curso com enrollment `cancelled`/`expired` → recompra impossível pela vitrine | `ListCoursesAction.php:51-55` (`whereDoesntHave` sem `currentStatuses()`) | filtrar por statuses correntes; teste de recompra |
| P2.2 | Landing pública vaza aulas draft/inativas | `ShowCourseAction.php:19-21` + `CourseDetailResource.php:25` | filtrar `published + is_active` como `GetCourseModulesAction.php:31-34` |
| P2.3 | Slug de curso sem unicidade efetiva (unique com `deleted_at` NULL) | `create_courses_table.php:27`; `ShowCourseAction.php:27` usa `first()` | coluna gerada estilo enrollment ou verificação + sufixo na geração |
| P2.4 | `abort(422)` em Actions fura o envelope de erro canônico | `StartAttemptAction.php:28,37`, `SubmitAnswerAction.php:28`, `FinishAttemptAction.php:25`, `StoreQuestionnaireAction.php:47` | exceções de domínio renderizadas em `bootstrap/app.php`; adicionar render para `HttpException` |
| P2.5 | `StartAttemptAction` 500 com developer sem tenant | `StartAttemptAction.php:24,84` (`$context->tenant->id` sem null-check; idem `SubmitAnswerAction.php:43`) | `requiredTenant()` ou tratamento explícito |
| P2.6 | Email/CPF unique **global** contradiz modelo tenant-scoped | `RegisterUserRequest.php:21` + migrations de `users` | migração para `unique(tenant_id, email/cpf)` — já pendente em `10-core-identity/tasks.md` |
| P2.7 | N+1 em `GET /courses/{id}/modules` (~2 queries/aula) + sem teste IDOR/permissão | `GetCourseModulesAction.php:45-52` (eager sem `courseModule`) | eager load completo; adicionar testes cross-tenant/403 |
| P2.8 | Listener OrderPaid aborta pedido inteiro se 1 item falhar | `EnrollStudentFromOrderPaidListener.php:35-41` + `firstOrFail` | isolar falha por item; logar e seguir |
| P2.9 | Progresso sem mídia regride percentual (heartbeat fora de ordem) | `UpdateProgressAction.php:106-110` (sem `max()`; caminho com mídia é monotônico em `:131`) | `max()` com valor existente; idem `watched_seconds` (`:251`) |

## P3 — Baixo / dívida

- **Testes não-herméticos**: `CourseMaterialDownloadApiTest.php:43` e `LessonApiTest.php:682`
  escrevem no disco real (`Storage::disk(config(...))->put`) → falhas ambientais (dir
  `storage/app/private/tenants` ficou `root:700` após execução como root). Trocar por
  `Storage::fake()`; limpar ownership do diretório.
- **phpstan: 394 erros, 357 `property.notFound`** — models sem `@property` PHPDoc → análise cega
  para typo de atributo Eloquent. Gerar annotations (ide-helper ou blocos manuais) e baixar o
  baseline.
- **`ModuleBoundaryTest` só regexa `use`** — FQN inline (`\App\Modules\X\...`) escapa; 6 entradas
  `knownDebt`. Endurecer regex; plano de conversão da allowlist (Events/Contracts).
- **`PiiAuditTest` cobre só `User`** (8 campos em `config/lgpd.php`) — inventariar PII de
  `Enrollment`, `Order`, `Certificate`, `QuizAttempt` (ver seção de auditoria LGPD abaixo).
- **PATCH lesson media substitui `metadata` inteira** (`UpdateLessonMediaAction.php:11`) — merge
  ou validação de consistência por provider.
- **`LessonMediaResource` expõe `storage_path`/`storage_disk`/`metadata` integral a alunos**
  (`LessonMediaResource.php:38,62-66`) — paths de infra como dado público; filtrar por área/role.
- **Dead code**: `EnrollStudentInCourseData::manual()` sem uso; `vehiculation_started_at/ended_at`
  (`Course.php:49-50`) sem lógica; `CourseRatingResource.php:14` expõe `tenant_id` sem necessidade.
- **Reorder sem transação e aceita subconjunto** (`ReorderLessonAction.php:42-54`).
- **`LessonView` sem dedupe/throttle** (`TrackLessonViewAction.php:15-20`) — estatística inflável.

## Docs — correções de prosa (rápidas, alto retorno)

1. `overview.md`: corrigir path de Actions (`app/Modules/<M>/Actions/...`) e URLs área-first;
   linkar `areas-surfaces.md`.
2. `40-financial/tasks.md`: remover rótulo "não iniciado" (há Done substancial).
3. `20-catalog-learning/tasks.md`: reestruturar seções `## Pending` cheias de `[x]`.
4. `40-financial/spec.md` Quick Reference: marcar endpoints inexistentes como **planejados**
   (`POST /financial/checkout` etc. — 0 rotas hoje, `Financial/Routes/api.php` vazio).
5. Definir dono único do ledger "Plataforma" (task em 40 ou 50, não ambos por referência).
6. `security-privacy-lgpd.md`: resolver referência órfã "invariante #9" (criar lista canônica de
   invariantes ou remover numeração).
7. Convenção nova: specs de futuro ganham banner `> Status: PLANEJADO — nada implementado` no
   topo (aplicar em `dependency-supply-chain-security.md`, seções de plataforma do Financial,
   todo o 50-ecosystem).
8. `30-assessment`: marcar na tabela de endpoints os pendentes
   (`GET/POST /questionnaires/{id}/questions`, `DELETE /questions/{id}`) — hoje só nota de rodapé.
9. `multi-tenancy.md`: fechar a decisão single-DB + `tenant_id` (já assumida em
   `performance-scalability.md`) e remover o hedge de connection-per-tenant.

---

## Auditoria de segurança / LGPD

> Metodologia: source→sink com evidência `file:line`. Invariantes de arquitetura rodados
> (15/15 verdes) — autoritativos; os findings abaixo são gaps que os invariantes **não** cobrem.
> Cada finding classificado por severidade e exploitabilidade (`confirmado`/`provável`/`teórico`)
> e por **EXPLORÁVEL HOJE** vs **LATENTE** (surge quando Financial/Ecosystem/self-enroll existirem).

### Vetores de fraude de negócio (a pergunta central: pagar burlado, forjar nota, plugin grátis)

Verificados em primeira mão (linhas conferidas manualmente):

| Vetor | Hoje | Cadeia |
|---|---|---|
| **Modificar a própria nota / aprovação** | 🔴 **EXPLORÁVEL — crítico/confirmado** | student tem `assessment.attempts.create/answer/finish` (`config/permissions.php:305-318`) → inicia tentativa → `PATCH /attempts/{id}` com `question_snapshot.correct_options`+`points` forjados (`SubmitAnswerRequest.php:21-22` exige do cliente) → `SubmitAnswerAction.php:36-40` calcula `is_correct`/`points_earned` a partir do body → `finish` grava `passed=true` com score arbitrário. Ver **P0.1**. |
| **Acessar curso pago sem pagar** | 🟡 **bloqueado por RBAC hoje, MINA LATENTE — alto/teórico** | `learning.enrollments.create` é só `developer/admin/instructor` (`config/permissions.php:199-202`); student não passa no Gate (`EnrollmentController.php:65`) e não há endpoint de checkout. **PORÉM** `StoreEnrollmentAction` não tem **nenhuma** guarda de preço para auto-matrícula (as checagens em `:60-73` só cobrem instrutor) — a única proteção é a permissão RBAC. No dia em que criarem auto-matrícula de aluno em curso grátis (produto vai precisar), curso pago vaza sem uma checagem `price_cents===0`. Ver **SEC-06**. |
| **Ativar plugin sem pagar** | ⚪ **LATENTE — não há código** | Domínio 50-ecosystem 0% implementado. Modelo declarado ("ativar free bypassa cobrança mas gera `PlatformOrder amount=0`", `PluginGrant` de comp) já convida a furo: se a ativação confiar em flag do request em vez de recomputar preço/grant no servidor, replica exatamente o padrão do P0.1. Travar por design antes de implementar. |
| **Forjar certificado** | 🟡 emissão não existe | Nenhum `Certificate::create` no código (P1.3). Latente: quando o gatilho automático for criado, ele depende de progresso (manipulável, ver abaixo) + score (forjável, P0.1). |
| **Manipular progresso p/ destravar certificado** | 🟠 **provável** | heartbeat aceita `progress_percentage` do request; denominador conta drafts (P1.5); sem gatilho de certificado hoje o impacto é contido, mas a base de cálculo é confiável no cliente. |
| **Submeter resposta em tentativa de outro usuário** | 🟢 mitigado | `SubmitAnswerAction.php:24` filtra por `user_id` do contexto (não aceita user alheio); risco residual é só a ausência de escopo de `tenant_id` (baixo — usuário pertence a 1 tenant). |
| **Rating sem matrícula / inflar views** | 🟠 médio | rating em curso pago exige matrícula ativa (ok); `LessonView` sem dedupe/throttle infla estatística (P3). |

**Invariantes de fraude que deveriam existir** (propor além de corrigir):
- teste provando que student **não** cria matrícula em curso pago (nem via `enrollments.create` nem futura auto-matrícula) sem Order paga;
- teste provando que score/`passed` de tentativa é derivado do snapshot **do servidor**, ignorando gabarito do request;
- teste provando que ativação de plugin pago exige subscription/grant ativo (quando Ecosystem existir).

### Auth / Sanctum / resolução de tenant (source→sink)

Superfícies **SEGURAS** confirmadas em código: throttle de login `5,1` (`Core/Routes/api.php:13`);
login não diferencia email inexistente de senha errada (`LoginAction.php:46-59`); cross-tenant no
login barrado (`LoginAction.php:62-79`); logout revoga token (`LogoutAction.php:11`); `GET /auth/me`
não vaza secret (`password`/`remember_token` em `$hidden`, `User.php:61-64`); tenant só resolve com
`is_active=true` (`RequestTenantFinder.php:18`); stack de middleware na ordem correta com
`tenant.access` em todas as rotas autenticadas; mass assignment contido (Actions montam array
explícito e forçam `tenant_id`/`status` server-side); filtro/sort do catálogo com allowlist
(`ListCatalogCoursesRequest.php:29` → `Rule::in(['top_rated'])`); registro sempre atribui `student`.

Findings (nenhum crítico no tree atual — são guardrail incompleto + hardening):

| id | título | sev | exploit | evidência | source→sink | mitigação |
|---|---|---|---|---|---|---|
| SEC-06 | `StoreEnrollmentAction` sem guarda de preço p/ auto-matrícula (proteção contra curso-pago-grátis é só RBAC) | alto | teórico | `StoreEnrollmentAction.php:45-73`; `config/permissions.php:199-202` | (latente) endpoint futuro de self-enroll dá `enrollments.create` a student → sem checar `price_cents` → matrícula active em curso pago sem Order | adicionar guarda explícita: auto-matrícula de student só se `course.price_cents===0`; matrícula paga só via `OrderPaidEvent` |
| SEC-02 | Sem invariante exigindo `tenant.access` em rota autenticada (defesa cross-tenant só por convenção) | médio | teórico | `RouteSecuritySurfaceTest.php:42`; `EnsureTenantAccess.php:29` | rota futura com `auth:sanctum` sem `tenant.access` + header `X-Tenant-ID:B` → Action escopa por tenant B → cross-tenant | estender `RouteSecuritySurfaceTest` p/ exigir `tenant.access` em toda rota autenticada, salvo allowlist |
| SEC-04 | Enumeração de usuário + criação em massa sem throttle no registro | baixo | confirmado | `Core/Routes/api.php:29`; `RegisterUserRequest.php:21` | body `email` → `unique` global → 422 determinístico revela email de qualquer tenant; sem rate-limit | `throttle` na rota `POST /users`; unicidade escopada por tenant |
| SEC-01 | `User::$fillable` inclui `tenant_id` e `user_type` (footgun de escalada) | baixo | teórico | `User.php:42-54`; `UpdateProfileAction.php:11` | (latente) regra futura em `UpdateProfileRequest` → `fill(validated())` → `user_type=admin` | remover `tenant_id`/`user_type` do `$fillable`, atribuir explícito |
| SEC-03 | Enumeração de usuário por timing no login | baixo | provável | `LoginAction.php:40-60` | `Hash::check` só roda se user existe → latência distingue email válido | `Hash::check` contra hash dummy quando `user===null` |
| SEC-05 | Token Sanctum all-powerful (`*`) e sem expiração | baixo/info | teórico | `LoginAction.php:26`; sem `config/sanctum.php` (expiration default `null`) | token roubado válido indefinidamente com abilities totais | definir `sanctum.expiration`; abilities escopadas via `tokenCan` |

### LGPD / exposição de PII (source→sink)

Dois fatos estruturais (verificados por grep): **nenhum `Log::`/`logger()` em `app/`** (bom — sem
PII em logs de aplicação) e **nenhum cast `encrypted`/`encrypted:json` em todo o projeto** (raiz de
LGPD-07/08). `PiiAuditTest` passa mas só cobre o que está em `config/lgpd.php` (só `User`) — cego a
PII não inventariado.

| id | título | sev | exploit. | evidência | source→sink | mitigação |
|---|---|---|---|---|---|---|
| LGPD-03 | unique `cpf`/`email` **global** vs per-tenant (spec diverge) | alto | confirmado (atual) | `create_users_table.php:17`, `add_identity_fields_to_users_table.php:20` | mesma pessoa não cadastra em 2 tenants + erro de unicidade revela existência de PII cross-tenant | `unique(tenant_id, cpf)`/`(tenant_id, email)`; corrigir spec |
| LGPD-07 | `TenantIntegration.configuration` sem `encrypted` (spec exige `encrypted:json`) | alto | teórico (latente) | `TenantIntegration.php:25` | escrita futura de token de integração → claro no banco | `'configuration' => 'encrypted:array'` **antes** de qualquer escrita real |
| LGPD-08 | `Payment.gateway_response`/`metadata` + `Order.metadata` sem `encrypted` | alto | teórico (latente) | `Payment.php:31-33`, `Order.php:59` | checkout/webhook futuro → segredo/PII de cobrança em claro | casts `encrypted:array` antes de ativar Financial |
| LGPD-01 | `UserResource` expõe `cpf` de todos numa listagem | médio | confirmado (atual) | `UserResource.php:19` + `UserController.php:41-48` | `GET /users` (admin) → cpf de terceiros em massa (viola minimização) | remover `cpf` do resource de lista; expor só em `show` com finalidade |
| LGPD-02 | verify público de certificado retorna `user_name` completo | médio | confirmado (atual) | `VerifyCertificateAction.php:45,58` | `GET /certificates/verify/{n}` sem auth → nome completo do titular | reduzir a iniciais / 2º fator; registrar como decisão consciente |
| LGPD-04 | enumeração de email no registro público (422 determinístico) | médio | confirmado (atual) | `RegisterUserRequest.php:21` + rota `@unauthenticated` | `POST /users` público → 422 revela email existente em qualquer tenant; sem throttle | mensagem genérica/confirmação assíncrona; escopar por tenant (= SEC-04) |
| LGPD-05 | `MaterialDownload` grava IP+user_agent, não inventariado, sem retenção | médio | confirmado (atual) | `MaterialDownload.php:16-17`, `StoreMaterialDownloadAction.php:25-26` | download → IP+UA+user persistidos sem base legal/TTL | inventariar; definir retenção |
| LGPD-06 | `LessonView`/`LessonMediaProgress` rastreiam comportamento, não inventariado | baixo | confirmado (atual) | `LessonView.php:17-22`, `LessonMediaProgress.php:52-54` | perfil comportamental do aluno sem retenção/base legal | classificar como dado comportamental; retenção |
| LGPD-09 | activitylog grava `cpf`/`email` em claro em `activity_log.properties` | baixo | confirmado (by design) | `User.php:32` + `config/activitylog.php:44` | audit trail → CPF cru na tabela (retenção 365d) | registrar no DPIA; avaliar cifrar/mascarar `properties` |
| LGPD-10 | retenção 365d configurada mas SEM schedule `activitylog:clean` | baixo | confirmado (atual) | `config/activitylog.php:14` vs `routes/console.php` (só `inspire`) | trilha com PII cresce indefinidamente | agendar `activitylog:clean` |
| LGPD-11 | `QuizAttempt` (`score`/`passed`) — dado de desempenho não classificado | info | confirmado | `QuizAttempt.php:18-31` | dado pessoal de desempenho sem classificação | avaliar inclusão no inventário |

**Ordem LGPD**: bloqueante p/ lançamento público → LGPD-03 + LGPD-04; bloqueante antes de ativar
Financial/Integrações → LGPD-07 + LGPD-08 (adicionar `encrypted` **agora**, custo baixo, corrige o
modelo antes de existir dado real); curto prazo → LGPD-01/02/05/10; DPIA → LGPD-09/11.

### Invariantes de segurança/LGPD propostos (gaps sem teste)

1. `RouteSecuritySurfaceTest` exigir `tenant.access` (não só `auth:sanctum`) em toda rota
   autenticada — fecha SEC-02.
2. Teste: score/`passed` de tentativa deriva do snapshot do **servidor**, ignora gabarito do
   request — fecha P0.1.
3. Teste: student não obtém matrícula em curso pago sem Order paga — fecha SEC-06.
4. Invariante complementar ao `PiiAuditTest`: varrer models por colunas de alto sinal
   (`cpf`,`email`,`ip_address`,`user_agent`,`phone`,`birth*`) e falhar se não classificadas em
   `config/lgpd.php` — fecha LGPD-05/06/11 como regressão executável.
5. Teste: colunas de segredo (`configuration`/`gateway_response`/`metadata`) declaram cast
   `encrypted` — fecha LGPD-07/08.

### Vetor de fraude aprofundado (source→sink)

**Contexto que muda a leitura**: pagamento real não existe (Financial só tem Models + evento;
`Financial/Routes/api.php` vazio; `OrderPaidEvent` **sem dispatcher de produção** — só em testes).
Emissão de certificado não existe (zero `Certificate::create` no `app/`). Logo várias "fraudes"
hoje **não destravam nada** porque o consumidor (certificado, acesso pago automático) está dormente
— mas o desenho já convida ao furo e as defesas são de camada única.

| # | Vetor | Hoje | Sev | Exploit | Evidência |
|---|---|:---:|:---:|:---:|---|
| F1 | Forjar `is_correct`/`points` da resposta | SIM (integridade) | Alta (bomba-relógio) | confirmado | `SubmitAnswerRequest.php:21-22` → `SubmitAnswerAction.php:36-40` |
| F2 | Marcar aula/curso 100% sem assistir | SIM | Média hoje / Alta c/ certificado | confirmado | `StoreProgressRequest.php:20-35` → `UpdateProgressAction.php:97-135` |
| F3 | Student auto-matricular em curso pago grátis | NÃO (RBAC barra) | Alta (latente/frágil) | teórico hoje | `permissions.php:199-202` vs `StoreEnrollmentAction.php:45-73` (= SEC-06) |
| F4 | Score sempre 0 / `passed` não gate nada | bug + gap | Média | confirmado (bug) | `StartAttemptAction.php:40-48` vs `FinishAttemptAction.php:28-39` |
| F5 | Inflar rating de curso grátis / `lesson_views` | SIM | Baixa | confirmado | `StoreCourseRatingAction.php:29`; `TrackLessonViewAction.php:15-20` |
| F7 | Iniciar tentativa de quiz sem matrícula/acesso | SIM | Baixa | confirmado | `StartAttemptAction.php:22-38` (sem check de enrollment) |
| F8 | Admin/dev comp matrícula paga ativa sem Order/audit | por design | Info | — | `StoreEnrollmentAction.php:45-52,82-84` |
| F9 | Plugin/Ecosystem sem pagar | Latente | — | não-verificável | só migration `create_plugin_billing_table` |

**Nuance decisiva do F1/F4 (bomba-relógio)**: a forja de `correct_options`/`points` por resposta
é real (F1), mas o "aprovar-se" ponta a ponta está **acidentalmente neutralizado hoje** por dois
acidentes: (a) `StartAttemptAction.php:40-48` monta `questionnaire_snapshot` **sem** a chave
`points` → `FinishAttemptAction.php:28-30` faz `collect([])->sum() = 0` → `maxPoints=0` →
`score=0` sempre → `passed=false`; (b) `passed`/`score` são consumidos **só** pelo
`AttemptResource` (resposta), ninguém emite certificado a partir deles, e
`Course.certificate_min_progress/_min_score/_requires_quiz` são gravados mas **nunca lidos**.
No dia em que corrigirem o scoring (colocar `points` no snapshot) e/ou plugarem certificado em
`passed`, vira aprovação forjada de ponta a ponta. **Corrigir a raiz (não confiar em
`correct_options`/`points` do cliente) independentemente** — ver P0.1.

**Não há vazamento de conteúdo pago**: catálogo público expõe só `id/title/sort_order/is_free`
da aula, sem URLs de mídia (`Catalog/LessonResource.php:12-18`); mídia só via `LessonController::show`
atrás de `canAccess`. `canAccessLesson` libera aula `is_free=true` mesmo em curso pago (preview por
design). Rematrícula pós-cancel/expire nos testes é feita por admin (comp), não por student.

**Achados adicionais a tratar (não cobertos em P0-P3 acima):**
- **F7 — iniciar tentativa de quiz sem matrícula**: `StartAttemptAction.php:22-38` não verifica
  enrollment/acesso ao curso do questionário. Adicionar check de acesso (espelhar
  `LessonController::show`). Severidade baixa hoje (nada destrava), mas é porta aberta.
- **F8 — comp sem trilha financeira**: admin/dev cria matrícula `active` em curso pago sem Order
  nem registro de auditoria financeira (`StoreEnrollmentAction.php:45-52,82-84`). Por design, mas
  sem o "espelho financeiro" que a spec promete → buraco de contabilidade/auditoria.
- **F4 — `maxPoints=0` bug**: corrigir o snapshot para incluir `points` por questão (server-side),
  senão o scoring nunca funciona (e o teste `AttemptApiTest.php:130-158` só assere estrutura, não
  valor — reforçar com asserção de valor real).

Os **10 invariantes anti-fraude propostos** pelos auditores estão consolidados na seção
"Invariantes de segurança/LGPD propostos" acima (itens 2 e 3) e detalhados aqui:
travar self-enroll de student (403), guard de preço na Action independente do Gate, score derivado
do servidor, conclusão de aula não auto-declarável, `PluginGrant` só a partir de pagamento
confirmado, rating de curso grátis só com matrícula, dedupe de `lesson_views`, verify de
certificado sem PII enumerável.

---

## Placar de verificação empírica (2026-07-11)

- **Suite completa**: 297 passed / 2 failed — as 2 falhas são ambientais (testes escrevem em disco
  real; `storage/app/private/tenants` ficou `root:700`). Ver P3.
- **Invariantes de arquitetura**: 15/15 verdes (`RouteSecuritySurfaceTest`, `TenantIsolationSmokeTest`,
  `PermissionDriftTest`, `PiiAuditTest`, `ErrorEnvelopeShapeTest`, `ScribeAuth...`, `ModuleBoundaryTest`,
  `MoneyNeverFloatTest`).
- **phpstan**: 394 erros (357 = `property.notFound`, models sem `@property`). Ver P3.
- **graphify**: sem ciclos de import; god nodes `ApiContext`/`Tenant`/`User`/`Course` coerentes.
