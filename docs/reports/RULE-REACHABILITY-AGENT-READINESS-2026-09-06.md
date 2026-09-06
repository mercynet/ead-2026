# Rule Reachability & Agent Readiness — 2026-09-06

Auditoria **READ-ONLY** da experiência de um Codex entrando frio no repositório para trabalhar em
Learning, Assessment, Ecosystem, Financial, Admin e Instructor.

Esta auditoria mede **alcançabilidade de contexto**, não a correção da implementação. Os relatórios
datados foram usados como evidência histórica e de reconciliação, nunca como regra automática. A
conclusão abaixo é `STATIC_EVIDENCE_ONLY`: não houve execução do app, Feature test, E2E, Scribe ou
consulta ao banco nesta auditoria.

## 1. Executive Summary

### Verdict

**`AGENT_READY_WITH_GAPS`**

O Codex consegue continuar o trabalho de forma controlada quando a task é estreita e o agente segue
manualmente `AGENTS.md` → `docs/specs/README.md` → spec/ADR do domínio. Ele não está pronto para
assumir que uma task normal, sozinha, carregará as regras de produto que impedem o desvio para um LMS
genérico.

O que está bem alcançável:

- API-only, modular monolith, shared kernel, fluxo Route → Controller → Action → Model → Resource,
  tenant scope, envelope, Resources, money em cents e princípio de área/guard (`AGENTS.md:15-24,
  106-151;` `docs/specs/00-architecture/areas-surfaces.md:50-62`).
- A distinção básica de persona: MZRT global, Admin no tenant, Instructor em `own`, Student em
  consumo próprio (`AGENTS.md:134-151;` `areas-surfaces.md:50-94`).
- Gateway/pagamento recebe um skill roteado quando a linguagem da task contém `gateway`, `pagamento`
  ou `checkout`; RBAC recebe um skill quando a task menciona permission/policy/gate.

O que fica escondido ou manual:

- A ownership funcional fina — Admin opera conteúdo do tenant, mas não se torna autor pedagógico;
  Instructor mantém ownership próprio e as rotas legacy de authoring (`courses-modules-lessons.md:71-74,
  113-136;` `ADMIN-CLOSURE-SLICE-2-2026-09-06.md:16-24`).
- O conjunto de invariantes de categorias, mídia, Assessment e plugins. O router não possui skills de
  domínio para Learning, Assessment, Ecosystem ou ownership.
- A separação tenant gateway × platform gateway, activation × config × entitlement e venda
  plataforma → tenant × venda tenant → student, embora essas regras existam nas specs/ADRs.

O risco real de **generic LMS drift é `HIGH`** para Assessment, plugins, gateways, ownership e
matrícula/pagamento; `MEDIUM` para categorias, mídia e publicação. O maior problema é de
reachability/contexto (categoria B abaixo), agravado por decisões ainda não fechadas (categoria C).

## 2. Codex Context Entry Points

| Entrada | Alcançabilidade | O que entrega | Limite observado |
|---|---|---|---|
| `AGENTS.md` | `AUTO_REACHABLE` | Contrato global, arquitetura atual, áreas, stacks, invariantes, fontes de verdade e links para specs. | Não contém as regras detalhadas de cada domínio nem ownership fino por recurso. |
| `.codex/skills` | `AUTO_REACHABLE` | Symlink para `.agents/skills`; skills podem ser lidas pelo agente. | Symlink não equivale a injeção automática do conteúdo do `SKILL.md`. |
| `.codex/hooks.json` | `AUTO_REACHABLE`, mas parcial | `PreToolUse` chama `graphify hook-check` para Bash e `skill-router.sh tool` para `Edit|Write|MultiEdit|Bash` (`.codex/hooks.json:2-22`). | Não há `SessionStart`, `UserPromptSubmit`, `PreCompact`, `PostToolUse` ou `Stop` para entregar contexto, persistir handoff ou fechar invariantes. |
| `scripts/ai/skill-router.sh` | `ROUTED_REACHABLE` | Informa skills por regex de prompt/caminho. | O router informa e sai 0; não bloqueia e não lê/injeta spec de produto. |
| `routing.json` | `ROUTED_REACHABLE` | Cobre money, área/guard, RBAC, vertical slice, testes, E2E, logging, security e planejamento. | Não roteia Learning, categorias, Assessment, plugins, ownership, media ou lifecycle de curso como contextos de negócio. |
| Laravel Boost em `.codex/config.toml` | `MANUAL_SEARCH_REQUIRED` | MCP de `search-docs`/inspeção do Laravel e do ecossistema. | Ajuda a resolver API de framework, não a descobrir a regra do produto. |
| `docs/specs/README.md` | `MANUAL_SEARCH_REQUIRED` | Explica a hierarquia: `spec.md`, `tasks.md`, `subspecs`, arquitetura, roadmap e STATE (`README.md:6-33,68-87`). | É um índice de navegação, não um pre-task context bundle. |
| `docs/ROADMAP.md` | `MANUAL_SEARCH_REQUIRED` | Jornadas por área, gates, legacy map e dependências (`ROADMAP.md:7-21,30-50,83-104`). | Status de jornada não é regra de domínio nem prova runtime. |
| `docs/STATE.md` | `MANUAL_SEARCH_REQUIRED` | Handoff da sessão corrente; hoje alerta que `ADM-03` está fechado até autorização e que ownership Instructor segue aberto (`STATE.md:7-28`). | É efêmero e não deve decidir contrato durável. |
| `docs/reports/**` | `HISTORICAL_ONLY` | Reconciliações e slices recentes ajudam a explicar decisões e deltas. | A própria reconciliação classifica reports como contexto datado, não fonte de contrato (`RECONCILIATION-DECISIONS-2026-09-05.md:43-69,157-175`). Não existe hook Codex que os selecione automaticamente. |
| Grafo `graphify-out/` | `MANUAL_SEARCH_REQUIRED` | O grafo local encontra símbolos, specs e relações quando consultado. | É navegação estrutural; não transforma uma regra em instrução de task. O query usado nesta auditoria encontrou 401 nós para os domínios, mas isso não é context injection. |

### Resultado observável do routing

No texto desta própria task, o router de prompt acionou apenas:

- `financial-money-flows`, por `gateway`/`pagamento`;
- `rbac-permission-wiring`, por `regras`/authorization context.

Nos caminhos simulados:

- categoria, curso, módulo, aula, mídia, Assessment e plugin → somente `vertical-slice`;
- arquivo de rota → `api-area-routing`;
- checkout → `financial-money-flows` + `vertical-slice`;
- teste de API → `pest-api-tests`.

Isso é adequado para a mecânica da implementação, mas insuficiente para carregar as regras de
produto antes da primeira decisão arquitetural.

## 3. Rule Reachability Matrix

| Regra importante | Classificação | Onde está | Diagnóstico de alcance |
|---|---|---|---|
| API-only, módulos canônicos, `app/Shared`, Resources, Actions, tenant explícito e invariantes de resposta | `AUTO_REACHABLE` | `AGENTS.md:15-24,106-132` | É contrato global e aparece antes de qualquer domínio. |
| MZRT global; Admin tenant-scoped; Instructor `own`; Student consumo próprio | `AUTO_REACHABLE` | `AGENTS.md:134-151`; `areas-surfaces.md:50-94` | A distinção básica é clara. A ownership funcional por recurso ainda exige leitura adicional. |
| Área/guard exato, stack tenant, legacy domain-first e separação de superfície | `ROUTED_REACHABLE` | `AGENTS.md:134-169`; `api-area-routing` via rotas | Alcança o agente quando ele toca `Routes/` ou usa vocabulário explícito de área. Não chega automaticamente ao planejamento de uma task de domínio. |
| SYSTEM/DEFAULT versus TENANT_CUSTOM, leitura Admin e escrita MZRT/Admin | `MANUAL_SEARCH_REQUIRED` | `catalog.md:47-56,115-138`; ADR-002; `tasks.md:72-75` | O repositório usa canonicamente `System`/`Custom`, não `DEFAULT`/`TENANT_CUSTOM`. O split de área é claro; a nomenclatura pedida pelo produto não é um alias documentado. |
| Categorias: não colidir com SYSTEM, repetir entre tenants, não repetir no mesmo tenant | `MANUAL_SEARCH_REQUIRED` | `catalog.md:58-70`; ADR-002:19-27,57-75 | Regra existe, mas só aparece ao abrir a subspec/ADR. |
| Hierarquia, parent de mesmo escopo, materialized path, ciclo e soft delete protegido | `MANUAL_SEARCH_REQUIRED` | `catalog.md:72-88`; `20-catalog-learning/tasks.md:99-105` | Parte do alvo ADR-002 ainda está em `Pending`; um agente precisa distinguir regra canônica de delta não implementado. |
| Curso → módulo → aula; módulo obrigatório; `is_free` é preview; `price_cents`/`access_days` são acesso | `MANUAL_SEARCH_REQUIRED` | `courses-modules-lessons.md:53-62,76-89` | Não está em `AGENTS.md` nem no router. Sem busca, é fácil criar aula diretamente no curso ou inventar `course.is_free`. |
| Curso pode existir sem Instructor; Admin não vira owner pedagógico; `instructor_id` não é payload arbitrário | `MANUAL_SEARCH_REQUIRED` | `courses-modules-lessons.md:16,71-74,113-136`; `ADMIN-CLOSURE-SLICE-2-2026-09-06.md:18-24,54-62` | Regra canônica recente, mas o detalhe aparece em spec/report. Alto risco de atribuir o Admin como Instructor. |
| `draft/published/archived`, preview, publish/unpublish exclusivamente na superfície Admin | `MANUAL_SEARCH_REQUIRED` | `courses-modules-lessons.md:60-68,91-103,148-156`; `20-catalog-learning/tasks.md:29-34` | O guard é roteável; o lifecycle e os campos proibidos não. |
| Tenant derivado de contexto; não aceitar `tenant_id`, owner, parent ou status para redefinir escopo | `ROUTED_REACHABLE` | `AGENTS.md:150-157`; `courses-modules-lessons.md:113-118`; Admin FormRequests/reports | A regra global chega automaticamente; o conjunto de campos proibidos por recurso depende de spec e testes. |
| LessonMedia múltipla, provider separado, pre-signed/direct/player URL e sem proxy binário | `MANUAL_SEARCH_REQUIRED` | `media-ratings.md:13-22,47-69,95-108`; `20-catalog-learning/tasks.md:65-67,116-117` | Não existe skill de mídia. A skill genérica de slice tende a produzir um único campo URL/upload. |
| `CourseMaterial` é registro/material de curso; `LessonMedia` é mídia da aula; não misturar download com consumo | `MANUAL_SEARCH_REQUIRED` | `media-ratings.md:31-34,76-84,100-116` | Explícito na subspec, invisível no contexto global. |
| Quiz core: questionário lesson/course/standalone, questões básicas, tenant scope e ownership | `AMBIGUOUS` | `30-assessment/spec.md:37-58`; `questionnaires-questions.md:45-75`; `rbac.md:171-189` | O modelo básico existe, mas Admin e Instructor recebem a mesma matriz e não há contrato area-first/ownership equivalente ao Learning. |
| Snapshot e scoring server-side; cliente não envia gabarito; questão usada não é editável | `MANUAL_SEARCH_REQUIRED` | `attempts-scoring.md:35-58`; `30-assessment/tasks.md:20-23` | Regra crítica de integridade, mas só é alcançada ao abrir a subspec de tentativas. |
| “CORE SIMPLE + PLUGIN ADVANCED” e fronteira exata de quiz avançado | `NOT_REACHABLE` para a regra completa; `AMBIGUOUS` no parcial | ADR-005 cita `quiz.advanced` e capability no core (`ADR-005:32-47`), mas Assessment não define o que é “simple core” nem a lista/contrato do avançado. | Um agente encontra o exemplo `quiz.advanced`, mas não uma regra única suficiente para decidir o escopo de uma nova feature. Exige decisão humana. |
| Plugins first-party, capability no core, sem código carregado dinamicamente | `AUTO_REACHABLE` | `AGENTS.md:118-125`; ADR-005:19-37; `Plugin.php:9-16` | Esta parte está bem ancorada no contrato e no código. |
| Activation/entitlement, config de instância e catálogo são stores/conceitos distintos | `MANUAL_SEARCH_REQUIRED` | `50-ecosystem-plugins/spec.md:24-60`; `PluginActivation.php`; `TenantPluginConfig.php`; `TenantGatewayProvider.php` | A separação existe em models/contracts, mas não há uma regra resumida/injetada dizendo explicitamente “não colapsar em `config.enabled`”. |
| Admin configura o que foi liberado; MZRT cria/disponibiliza catálogo/entitlement | `MANUAL_SEARCH_REQUIRED` | `areas-surfaces.md:64-83`; `50-ecosystem-plugins/spec.md:42-53,62-83` | A regra é canônica, porém distribuída entre área, Ecosystem e ADR. |
| Gateway do tenant ≠ gateway da plataforma; tenant config ≠ `PlatformPaymentGateway` | `ROUTED_REACHABLE` | `financial-money-flows` quando `gateway/pagamento` é detectado; ADR-005:38-53; Financial spec:48-69 | O skill dá o alerta monetário; a separação concreta ainda exige leitura manual de ADR/spec. |
| Billing Mzrt→tenant ≠ venda tenant→student; dois ledgers; cents, claim, idempotência e outbox | `ROUTED_REACHABLE` | `financial-money-flows`; `areas-surfaces.md:215-232`; `orders-payments.md:41-93` | O domínio money é roteado, mas o elo com Learning/Ecosystem e exceções de matrícula manual não é injetado. |
| Relatórios recentes, snapshots de estado e claims de “Done/verde” | `HISTORICAL_ONLY` | `docs/reports/**`; `STATE.md`; reconciliação | Servem para contexto/evidência datada. Não devem substituir spec, código ou decisão atual. |
| Manifesto que entregue automaticamente regra de domínio por task | `NOT_REACHABLE` | Não encontrado em `.codex/hooks.json`, `routing.json` ou `scripts/ai/` | O Codex tem routing de skill, não routing de regras/specs. |

## 4. Scenario Simulation

| Cenário | Docs/rules automaticamente alcançadas | Busca manual necessária | Regra potencialmente invisível | Risco / routing recomendado |
|---|---|---|---|---|
| 1. Adicionar campo/regra em categoria Admin | `AGENTS.md` (API/área/payload); `vertical-slice` ao tocar Action/HTTP; `api-area-routing` ao tocar rota; RBAC se tocar Policy/permission. | `catalog.md`, ADR-002, Learning tasks, `CategoryPolicy`, testes de categoria. | Admin só CRUD de custom; SYSTEM é MZRT/developer; sem `is_system`; cross-tenant e cross-scope proibidos; árvore não é CRUD plano. | **HIGH.** Context bundle `Learning:catalog + ADR-002 + Admin/Mzrt + category tests` antes de editar. |
| 2. Criar endpoint de curso Admin | Global API/tenant/área; `vertical-slice`; `api-area-routing` se o arquivo final for `Routes/admin.php`. | `courses-modules-lessons.md`, `areas-surfaces.md`, Learning tasks e `ADMIN-CLOSURE-SLICE-2`. | Curso Admin nasce sem `instructor_id`; Admin não vira Instructor; módulo antecede aula; operações Admin não são consumo Student. | **HIGH.** Rota `Learning/Routes/admin.php`; bundle de ownership e negative tests. |
| 3. Adicionar tipo de mídia | Arquitetura informa que `MediaProvider` é uma costura futura; `vertical-slice` cobre boilerplate. | `media-ratings.md`, backend patterns, Learning tasks e provider/URL contracts. | LessonMedia é múltipla; `media_type`, `provider`, `provider_ref`, `metadata` e progress strategy são ortogonais; não fazer proxy/upload genérico. | **HIGH.** Routing por `media|lesson_media|provider` para bundle Media + security/storage tests. |
| 4. Criar quiz no Admin | Módulo Assessment no layout global; `vertical-slice`; path `Assessment/Routes` chama `api-area-routing`, embora o grupo atual seja legacy. | Assessment spec, questionnaire/attempt subspecs, RBAC e ownership/area decision. | Admin/Instructor não são automaticamente o mesmo owner; morph `standalone`/tenant scope; associação de questões ainda pending; scoring não é do cliente. | **CRITICAL.** Não iniciar sem contexto Assessment + decisão de ownership + negative/tenant/snapshot tests. |
| 5. Adicionar feature avançada de quiz | ADR-005 é alcançável só se o agente procurar plugin/capability; nenhum skill de plugin é acionado pela palavra `plugin` isoladamente. | ADR-005, Ecosystem spec/tasks, Assessment spec e decisão de produto para core/advanced. | Feature avançada é capability do core gated por activation/entitlement/config apropriados; não é `enabled` puro nem módulo carregado dinamicamente. | **CRITICAL.** Roteamento explícito `quiz|assessment|advanced|capability` para Assessment + Ecosystem; gate humano obrigatório. |
| 6. Configurar gateway do tenant | `financial-money-flows` por `gateway`; `vertical-slice` em Action/HTTP; `api-area-routing` em rota; RBAC em permission. | ADR-005, Financial/Ecosystem specs, `TenantGatewayProvider`, config revision e Admin gateway tests. | Gateway tenant usa `TenantPluginConfig` + activation; platform gateway é outro âmbito; secrets write-only/encrypted; no máximo um ativo; resolver precisa validar config. | **CRITICAL.** Contexto composto Financial + Ecosystem + Admin; nunca apenas `Financial`. |
| 7. Criar capability/plugin | `AGENTS.md` aponta Ecosystem e first-party; `vertical-slice` para arquivos; RBAC se permissions aparecerem. | ADR-005, Ecosystem spec/tasks, marketplace/subscriptions e areas; conferir conflito histórico de `app/Plugins`. | Developer/MZRT cria catálogo; Admin configura/consome o range; activation, entitlement e config não são uma flag; billing é platform ledger futuro. | **CRITICAL.** Nova skill/contexto `ecosystem-capability` ou manifest roteado por `plugin|activation|entitlement`. |
| 8. Adicionar funcionalidade Instructor sobre cursos | Áreas/RBAC global e `vertical-slice`; `api-area-routing` só se a rota/persona for explicitada. | `areas-surfaces.md`, `rbac.md`, `courses-modules-lessons.md`, `InstructorOwnershipTest`, Learning tasks. | Não existe ainda superfície `v1/instructor` canônica; authoring Instructor permanece em `/v1/learning`; `own` é funcional, não apenas permission técnica. | **HIGH.** Bundle Persona/Ownership + legado/compatibilidade; não “corrigir” migrando tudo para Instructor sem decisão. |
| 9. Alterar publicação de curso | `AGENTS.md` diz que área decide escopo; route path aciona área; RBAC pode acionar skill. | Lifecycle de courses, Publish/Unpublish tasks, Admin slice e preview/access rules. | POST/PATCH de curso não publica; publish/unpublish é transição dedicada Admin; draft não é Student-visible; archived deve bloquear. | **HIGH.** Contexto `Learning lifecycle + Admin area + legacy compatibility` e teste discriminante de status. |
| 10. Alterar matrícula/pagamento | `financial-money-flows` por pagamento/checkout; `vertical-slice`; `pest-api-tests` se tocar testes; área se rota. | Enrollment spec/tasks, Financial orders/payments, Ecosystem gateway/ADR-005, eventos/outbox e Admin targeting. | Student→tenant não é Mzrt→tenant; matrícula manual zero-consideration tem espelho específico; externa permanece pendente; idempotência/outbox/charge state são obrigatórios. | **CRITICAL.** Bundle triplo Learning + Financial + Ecosystem e validação E2E/side effects quando autorizado. |

## 5. Hidden Business Rules

### A. Regra existe e é canônica, mas o Codex não a recebe antes da decisão

1. **Admin não é owner pedagógico por default.** A regra está na subspec e no slice recente; sem o
   bundle de Learning, um CRUD genérico tende a preencher `instructor_id` com o usuário autenticado.
   Categoria: **B — routing/context**. Severidade: **HIGH**.
2. **Admin management não é Student consumption.** Materiais/mídia Admin administram metadata/path;
   não resolvem consumo, progresso, download ou tracking. Categoria **B**. Severidade **HIGH**.
3. **Mídia não é um `file_url` singular.** Múltiplas mídias, provider, metadata, URL temporária e
   ausência de proxy binário formam um contrato composto. Categoria **B**. Severidade **HIGH**.
4. **Gateway é costura Ecosystem/Financial, não um CRUD financeiro isolado.** A disponibilidade
   efetiva combina activation/entitlement e config, enquanto o adaptador é resolvido pelo Financial.
   Categoria **B**. Severidade **CRITICAL**.
5. **Pagamento tem dois âmbitos/ledgers e eventos.** Um agente roteado apenas por `checkout` pode
   ainda perder o mirror específico de matrícula manual ou misturar PlatformOrder com Order.
   Categoria **B**. Severidade **CRITICAL**.

### B. Regra existe, mas o mecanismo de alcance é inadequado

1. O router dispara `vertical-slice` para praticamente qualquer arquivo de produto. Isso carrega o
   formato da fatia, não o que torna este LMS diferente.
2. `api-area-routing` é disparado pelo arquivo de rota, frequentemente tarde demais: a decisão de
   ownership/área já pode ter sido tomada ao desenhar o endpoint. Em Assessment, o path legacy aciona
   a skill de área, mas a própria regra canônica permite `v1/assessment` legado sem guard; isso pode
   confundir “skill acionada” com “rota deve ser area-first”.
3. Nenhum hook Codex injeta `docs/specs/<domínio>/spec.md`, `subspecs`, ADRs ou testes discriminantes no
   início de uma task. O Codex depende de obediência manual ao índice.
4. Reports recentes, inclusive os de Admin/MZRT, não estão ligados a um índice de contexto automático;
   além disso, são evidência datada, não fonte de regra.

### C. Regra ainda não está decidida de modo suficiente

1. **Assessment Admin/Instructor:** a matriz de RBAC concede questionários/questões a Admin e
   Instructor, e os models têm `instructor_id`, mas não há contrato equivalente ao Learning para
   dizer quando Admin cria sem owner, quando Instructor é `own`, ou como Admin enxerga conteúdo de
   outro Instructor.
2. **Core simple versus quiz advanced:** ADR-005 fornece o exemplo `quiz.advanced`, mas não define o
   limite funcional do quiz core, o catálogo de capabilities, nem o que exige entitlement/config.
3. **Nomenclatura de categoria:** o contrato implementado usa System/Custom; `DEFAULT`/
   `TENANT_CUSTOM` aparece no pedido, mas não como alias canônico documentado.
4. **Mídia real:** `MediaProvider`, upload real e MediaEmbedService continuam alvo/pending; adicionar
   um tipo agora pode exigir decisão de provider, storage e segurança que o contrato ainda deixa para
   outro slice.
5. **Matrícula externa:** o mirror depois da aprovação de `billing_type=external` continua pendente
   (`20-catalog-learning/tasks.md:117-119`; Financial tasks). Não inferir o comportamento a partir do
   fluxo cash.

## 6. Routing / Skill Coverage

| Área de trabalho | Skill/roteamento atual | Cobertura | Gap para reachability |
|---|---|---|---|
| Categoria/Learning | `vertical-slice`; `api-area-routing` somente em rota | Mecânica/API surface | Falta context skill de categoria, árvore, System/Custom e Learning ownership. |
| Curso/módulo/aula | `vertical-slice`; área ao tocar `Routes` | Mecânica e guard | Falta injeção de árvore, lifecycle, preview, publish e Admin/Instructor boundary. |
| Mídia | `vertical-slice` apenas | Boilerplate | Falta `MediaProvider`/storage/pre-signed/metadata context. |
| Assessment | `vertical-slice`; `pest-api-tests` em testes; área em rota legacy | Stack parcial | Falta skill/contexto de snapshot, scoring, morph/tenant, ownership e core/advanced. |
| Plugin/Ecosystem | `vertical-slice`; RBAC quando permission aparece | CRUD parcial | A palavra `plugin` não aciona skill de capability; activation/config/entitlement/billing não são entregues. |
| Gateway/checkout | `financial-money-flows`; vertical/area/RBAC conforme path | Melhor cobertura atual | Ainda depende de busca manual para Ecosystem owner, tenant/platform e exceções de matrícula. |
| Admin/Instructor | Área se explicitada ou rota tocada | Superfície | Não há skill de ownership funcional; `Admin` isoladamente não é regex de contexto. |
| `tasks.md`/roadmap | `spec-task-planning` somente ao tocar esses paths ou usar palavras de planejamento | Status | Uma task de código não recebe automaticamente a task/spec relevante. |
| Reports | Nenhum routing | Contexto histórico | Correto não tratá-los como regra, mas falta um índice que aponte o report pertinente sem promovê-lo. |
| Lifecycle Codex | Apenas `PreToolUse` | Proteção local | Não há pre-task bundle, Stop de invariantes ou receipt automático Codex equivalente ao Claude. |

`validate-harness.py` está estruturalmente verde com um warning de OpenCode opcional. Isso prova a
validade do harness, não que o Codex receba as regras de domínio. O warning OpenCode não é finding
relevante para este baseline Codex, conforme `AGENTS.md:31-42`.

## 7. Generic LMS Drift Risks

### Risco geral: `HIGH`

- **Curso genérico:** criar `Course` com `instructor_id = auth()->id()`, aceitar `tenant_id`/
  `status` no payload, permitir aula sem módulo ou publicar no `PATCH`.
- **Categorias genéricas:** usar uma tabela plana, aceitar `is_system` do cliente, permitir custom sob
  SYSTEM, usar morph pivot ou aplicar unicidade global indevida.
- **Mídia genérica:** um único `video_url`, upload binário pelo backend, sem provider/ref/metadata,
  ou confundir `CourseMaterial` com `LessonMedia`.
- **Quiz genérico:** aceitar snapshot/gabarito do cliente, recalcular score contra a questão atual,
  editar questão já usada ou tratar Admin e Instructor como o mesmo owner.
- **Plugin genérico:** interpretar plugin como `config.enabled`, carregar código dinamicamente de
  `app/Plugins`, permitir Admin criar capability/entitlement ou expor segredo.
- **Gateway genérico:** um único gateway global, um único ledger ou uma config sem activation/
  entitlement/revision; isso mistura Mozart→tenant com tenant→student.
- **Admin genérico:** mover todas as rotas `/v1/learning` para `/v1/admin` e absorver authoring
  Instructor, consumo Student ou ownership sem preservar compatibilidade e boundaries.

O risco não vem de ausência total de documentação. Vem de o agente encontrar primeiro um código CRUD
plausível e só depois — ou nunca — abrir a regra discriminante.

## 8. Product Decisions Still Unresolved

Estas são decisões C, não problemas que devem ser “resolvidos” por inferência do agente:

1. Definir formalmente o boundary `quiz core simple` versus `quiz.advanced` plugin/future: tipos,
   capacidades, entitlement, config, permissions, lifecycle e impacto em Student/Admin/Instructor.
2. Definir ownership de Questionnaire/QuizQuestion para Admin e Instructor: criação Admin sem autor,
   `own` do Instructor, visibilidade Admin tenant-wide, associação a Course/Module/Lesson e regra para
   `standalone`.
3. Confirmar se `DEFAULT` é apenas sinônimo de `SYSTEM`; manter no contrato um vocabulário único
   (`System`/`Custom` ou outro) para evitar duas taxonomias.
4. Decidir o contrato de `MediaProvider`, upload real, MediaEmbedService, storage e tipos de mídia que
   pertencem ao core versus plugin.
5. Decidir o mirror financeiro da matrícula externa após aprovação; não derivar da regra de
   zero-consideration de matrícula manual sem `billing_type`.
6. Decidir o lifecycle futuro `PluginInstallation`/`PluginActivation`/`PluginSubscription`/
   `PluginGrant` e qual superfície Admin apenas configura versus qual superfície MZRT cria entitlement.
7. Definir a futura operação explícita de atribuição de Instructor para cursos criados por Admin.

Não são decisões pendentes: tenant gateway é de Ecosystem, o gateway da plataforma é dedicado; a
separação de ledgers está aceita; e o modelo de curso `price_cents + access_days`, `content_type`
ortogonal e matrícula `open` no MVP já foram resolvidos (`courses-modules-lessons.md:76-89`;
ADR-005; ADR-003).

## 9. Recommended Context Architecture

Não copiar todas as regras para `AGENTS.md`. Manter nele apenas as invariantes verdadeiramente globais
e adicionar um caminho de contexto resolvido antes da primeira edição:

1. **Manifest de regras por capability/persona.** Um índice pequeno, ligado por links, deve registrar
   para cada capability: spec canônica, ADRs, ownership/área, campos proibidos, decisões abertas,
   testes discriminantes e skills obrigatórias. Reports entram como `historical evidence`, nunca como
   source of truth.
2. **Prompt routing de domínio.** Palavras e paths devem resolver bundles, não apenas skills:

   - `category|catalog` → Learning catalog + ADR-002 + CategoryPolicy + Admin/Mzrt;
   - `course|module|lesson|publish` → Learning content + areas/RBAC + Admin/Instructor boundary;
   - `media|provider|material` → media spec + storage/provider + security;
   - `quiz|assessment|questionnaire|scoring` → Assessment specs + snapshot + ownership decision +
     ADR-005;
   - `plugin|activation|entitlement|capability` → Ecosystem spec + ADR-005 + marketplace/billing
     status;
   - `gateway|checkout|payment|enrollment` → Financial money flow + Learning enrollment + Ecosystem
     gateway + two-ledger rules;
   - `Admin|Instructor|Student|MZRT` → areas/RBAC + current legacy/area-first map.

3. **Path routing antecipado.** `app/Modules/Learning/**` e `app/Modules/Assessment/**` não devem
   receber somente `vertical-slice`; `Routes/**` não deve ser o primeiro momento em que área é
   considerada. O bundle deve ser emitido ao detectar a intenção ou o primeiro path do domínio.
4. **Prova discriminante associada ao contexto.** Cada bundle deve apontar para testes/guards que
   distinguem este produto: no-owner Admin, cross-tenant 404, forbidden scope fields, category
   system/custom, snapshot server-side, entitlement sem config, tenant gateway versus platform
   gateway e ledger correto.
5. **Human gate para C.** Quando o resolver encontrar uma decisão marcada `OPEN/AMBIGUOUS`, o agente
   deve parar a implementação daquele ramo e reportar a decisão necessária; não escolher o padrão de
   um LMS genérico.
6. **Lifecycle Codex mínimo.** Sem abrir WS2 genérico, o Codex precisa ao menos de um hook/pre-task
   equivalente que mostre branch/STATE, bundle de regras e skills antes de uma edição. Stop/receipts
   podem continuar sendo hardening separado, mas não devem ser confundidos com reachability de regra.

## 10. Minimal Fixes Before Continuing Admin

### Bloqueiam continuação segura de determinados ramos Admin

| Gap | Categoria | Severidade | Bloqueia |
|---|---|---:|---|
| Não existe bundle obrigatório de Admin/Instructor ownership antes de nova task Learning | B — routing/context | HIGH | Qualquer mudança que crie/atribua conteúdo, especialmente curso Admin e Instructor. Não bloqueia um slice de conteúdo já delimitado se o bundle for anexado manualmente. |
| Assessment não tem decisão suficiente sobre ownership Admin/Instructor e core/advanced | C — produto | CRITICAL | Criação de quiz Admin e qualquer feature avançada. |
| Matrícula/pagamento exige contexto triplo Learning + Financial + Ecosystem, mas só Financial é roteado por vocabulário comum | B — routing/context | CRITICAL | Alterações em matrícula, cash/manual, checkout, gateway ou efeitos `OrderPaid`. |
| Categoria tem delta ADR-002 aberto sem marcador de “regra canônica versus implementação pending” no contexto da task | A/B | HIGH | Mudança de árvore, unicidade, restore/delete ou parent; não bloqueia CRUD Admin simples já coberto, desde que o delta não seja inventado. |

### Não bloqueiam toda continuação Admin

- ADM-02 de conteúdo tem contexto muito melhor após o Slice 2: spec Learning atualizada, superfície
  `/api/v1/admin`, negativos de ownership e payloads proibidos (`ADMIN-CLOSURE-SLICE-2-2026-09-06.md:43-78`).
- Gateway tenant já tem owner técnico e contrato estrutural claros; o risco é perder o bundle composto,
  não ausência de regra.
- `ADM-03` está intencionalmente fechado até uma task autorizada em `STATE.md:13-17`; isso é disciplina
  de escopo, não falha de reachability.
- `ADM-04`/receipt e a ausência de Stop Codex afetam evidência/closure, mas não justificam abrir um
  WS2 genérico nesta auditoria.

### Correções mínimas recomendadas

1. Introduzir o manifest/bundle de contexto por capability/persona e fazê-lo ser emitido por prompt/path
   routing antes da skill genérica de slice.
2. Adicionar os quatro bundles de maior retorno: `Learning ownership`, `Assessment integrity`,
   `Ecosystem capability`, `Financial cross-ledger`.
3. Marcar explicitamente, nos bundles, `CANONICAL`, `PENDING`, `HISTORICAL` e `OPEN DECISION`, com
   links para o arquivo e linha de cada regra.
4. Fechar primeiro as duas decisões humanas de maior risco: Assessment ownership e core quiz versus
   advanced plugin.
5. Para continuar Admin agora, entregar o bundle `Admin content` manualmente em cada task: áreas/RBAC,
   courses-modules-lessons, media quando aplicável, category ADR quando aplicável, Admin Slice 2 como
   evidência histórica e os testes negativos relevantes.

## 11. Verdict

**`AGENT_READY_WITH_GAPS`**

O Codex está pronto para continuar o projeto em fatias controladas, principalmente Admin content,
categorias, publicação e gateway, desde que o contexto de domínio seja anexado deliberadamente. Não
está pronto para operar “frio” em Assessment, plugins, ownership Instructor ou matrícula/pagamento
sem risco material de implementar um LMS genérico ou misturar os âmbitos do produto.

Resumo final:

- regras globais e superfícies: **AUTO_REACHABLE**;
- área/guard, RBAC, money e formato de fatia: **ROUTED_REACHABLE**;
- regras de Learning, mídia, Assessment integrity e lifecycle de plugin: **MANUAL_SEARCH_REQUIRED**;
- reports/STATE para regra durável: **HISTORICAL_ONLY / pointer**, nunca autoridade;
- core simple versus advanced quiz completo e manifesto automático de regras: **NOT_REACHABLE**;
- ownership Assessment, nomenclatura System/Custom versus DEFAULT e lifecycle futuro de plugins:
  **AMBIGUOUS / decisão humana**.

Sem os bundles mínimos e as decisões C acima, o bloqueio é seletivo, mas material: **não continuar
Admin quiz nem matrícula/pagamento como task normal fria**. Admin content estreito pode continuar com
contexto manual explícito.
