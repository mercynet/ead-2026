> **Snapshot histórico — superseded por `RECONCILIATION-CLOSURE-2026-09-05.md`.** As classificações
> `NEEDS_RECONCILIATION` deste relatório registram o estado anterior às decisões humanas. O
> fechamento posterior aplica D1–D5; não usar a lista abaixo como backlog atual sem consultar o
> relatório de closure e as fontes canônicas.

# Reconciliação do Estado Canônico — 2026-09-05

## Resultado executivo

Esta Fase 0 estabelece o estado documental atual sem implementar feature, corrigir finding técnico,
refatorar produto ou alterar o harness executável. O código/rotas/testes atuais foram usados para
arbitrar conflitos; documentação histórica não foi promovida a contrato.

Conclusão canônica:

- **Produto:** `PRODUCT_PARTIALLY_IMPLEMENTED`. É um backend REST JSON Laravel 12, API-first,
  monólito modular com Core, Learning, Assessment, Financial e Ecosystem. Auth/tenants/RBAC,
  catálogo/conteúdo, matrícula/progresso, assessment/certificados e checkout/gateway `cash` existem
  em graus diferentes. Instructor e Home não fecham jornadas; orders/webhooks, marketplace/billing
  de plataforma, PDF/revoke e adapters externos continuam backlog.
- **Arquitetura válida:** runtime em `app/Modules/*` carregado por providers; `app/Shared` é shared
  kernel; fluxo pretendido é Route → Controller fino → Action → Model → Resource. Cross-module deve
  usar Events/Contracts, mas a dívida Eloquent da allowlist de `ModuleBoundaryTest` ainda existe.
  Tenant é resolvido pelo stack de multitenancy/contexto; as queries atuais usam filtro explícito em
  Actions/Services conforme ADR-004 e `TenantScopingTest`.
- **Runtime:** nenhum item desta reconciliação é `RUNTIME_VERIFIED`. A auditoria base registrou
  Docker indisponível; E2E/testes mencionados abaixo são evidência de testes ou execução documentada
  anterior, não uma nova aprovação funcional do HEAD.
- **Harness:** há enforcement real, mas por ferramenta. Claude cobre guards, router, pós-edição,
  SessionStart, PreCompact e Stop; Codex atualmente recebe apenas PreToolUse de Graphify e router.
  O router informa skills e não bloqueia. CI executa QA, mas a auditoria de dependências é advisory.

## Regra de validação usada

`RUNTIME_VERIFIED` = execução HTTP/console contra app e banco reais na rodada atual. `TEST_VERIFIED` =
há teste e/ou resultado de teste documentado para a capacidade, mas isso não substitui runtime atual.
`STATIC_EVIDENCE_ONLY` = código, rota, model, migration ou teste presente sem resultado confiável
observado nesta reconciliação. `DOCUMENTATION_ONLY` = intenção em spec/tasks/roadmap sem implementação
arbitrada. `UNVERIFIED` = evidência conflitante ou insuficiente.

## 1. Inventário documental

| Documento/conjunto | Classificação | Uso canônico e limite |
|---|---|---|
| `AGENTS.md` | `CANONICAL` | Contrato principal do projeto; agora explicita o tenant pattern real. Código/runtime vence em conflito. |
| `docs/specs/README.md` | `CANONICAL` | Governança das specs, tasks, roadmap e state. |
| `docs/specs/00-architecture/*.md` | `DOMAIN_SPEC` | Contratos cross-cutting; decisões/ADRs são decisões arquiteturais aceitas. `backend-patterns.md` está parcialmente superseded. |
| `docs/specs/00-architecture/decisions/*.md` | `DOMAIN_SPEC` | Registro de decisões; não é inventário de implementação. ADR-004 é a referência atual para tenant queries. |
| `docs/specs/*/spec.md` | `DOMAIN_SPEC` | Contrato durável de cada domínio; `maturity` é maturidade do contrato, não implementação. |
| `docs/specs/*/subspecs/*.md` | `DOMAIN_SPEC` | Detalhes duráveis por recurso; sem status de entrega. |
| `docs/specs/*/tasks.md` | `OPERATIONAL` | Backlog/status local. É a fonte de progresso, mas contém drift/duplicação identificada abaixo. |
| `docs/ROADMAP.md` | `ROADMAP` | Jornadas cross-domain e gates. Status `usable/partial` é status de jornada/contrato, não prova runtime atual. |
| `docs/STATE.md` | `SESSION_STATE` | Handoff efêmero desta sessão; não é fonte permanente nem substitui tasks. |
| `docs/reports/SYSTEM-STATE-AUDIT-2026-09-05.md` | `HISTORICAL` | Snapshot READ-ONLY da auditoria-base; evidência datada, não regra futura. |
| `CONTEXT.md` | `SUPERSEDED` | Descrevia layout flat antigo; recebeu header e não deve guiar trabalho novo. |
| `PROMPT-CONTINUAR.md` | `SUPERSEDED` | Prompt de retomada antigo; pode conter regras de negócio/path desatualizados. |
| `CHECKLIST-VERIFICACAO.md` | `HISTORICAL` | Checklist de auditorias anteriores; não substitui testes/specs atuais. |
| `docs/auditoria-correcoes-2026-07-11-pending.md` | `HISTORICAL` | Snapshot de branch/commit antigo; itens corrigidos precisam ser confrontados com código/tasks. |
| `docs/auditoria-e2e-http-2026-07-16.md` | `HISTORICAL` | Evidência de uma rodada antiga e deliberadamente NO-GO; não valida HEAD atual. |
| `CLAUDE.md` | `OPERATIONAL` | Extensão específica do Claude; deve obedecer `AGENTS.md`. |
| `GEMINI.md` | `OPERATIONAL` | Orientações Boost/stack; não governa estado do produto. |
| `.agents/skills/*/SKILL.md` | `OPERATIONAL` | Instruções de trabalho roteadas; o conteúdo não prova enforcement. |
| `.github/skills/**`, `.junie/skills/**` | `UNCLEAR` | Cópias/integrações de ferramenta fora do home canônico `.agents/skills`; não foram usadas como fonte de verdade. |
| `.scribe/*.md` | `GENERATED` | Saída gerada de documentação API; só vale após geração contra rotas atuais. |
| `.slim/deepwork/*.md` | `HISTORICAL` | Notas de trabalhos/auditorias anteriores; sem autoridade de contrato. |
| `README.md` | `UNCLEAR` | Ainda é quase o README padrão Laravel; contém o comando QA, mas não descreve o produto atual. |

## 2. Capability matrix canônica

| Módulo | Capability atual | Estado | Evidência principal | Validação |
|---|---|---|---|---|
| Core | login, logout, me, password reset | `IMPLEMENTED` | `Core/Routes/api.php`, controllers/actions, Feature tests | `TEST_VERIFIED` |
| Core | convites invite-only | `IMPLEMENTED` | `InvitationController`, migrations, `InvitationApiTest` | `TEST_VERIFIED` |
| Core | tenants: create/status, login bloqueado/reativado, preset `cash` | `IMPLEMENTED` | Mzrt routes, `ProvisionTenantAction`, task MZRT-SKELETON; evidência E2E documentada, não atual | `TEST_VERIFIED` |
| Core | RBAC, UserType, permission ceiling | `IMPLEMENTED` | `config/permissions.php`, providers/policies, Architecture tests | `TEST_VERIFIED` |
| Core | administração de usuários pelo Admin | `IMPLEMENTED` | `v1/admin/users/{id}`, controller/action/tests | `STATIC_EVIDENCE_ONLY` |
| Learning | catálogo e categorias system/tenant | `PARTIAL` | Learning routes/actions; escrita split Admin/Mzrt; redesign de categoria pendente; sem runtime atual | `TEST_VERIFIED` |
| Learning | cursos: CRUD, publish/unpublish, preço/histórico, categorias | `PARTIAL` | `CourseController`, admin routes, migrations, Feature/E2E specs | `TEST_VERIFIED` |
| Learning | módulos, aulas, preview e reorder | `IMPLEMENTED` | controllers/actions/routes e tasks marcadas | `STATIC_EVIDENCE_ONLY` |
| Learning | matrícula manual, acesso, progresso e conclusão | `PARTIAL` | Enrollment/Lesson actions, events, Feature/E2E specs | `TEST_VERIFIED` |
| Learning | media/materials/download URL/stats | `PARTIAL` | models/actions/routes; `MediaProvider`/upload real ainda ausente | `STATIC_EVIDENCE_ONLY` |
| Learning | ratings/ranking | `UNKNOWN` | código existe, mas tasks repetem “done” e “delta restante” | `UNVERIFIED` |
| Assessment | questionnaires/questions CRUD | `PARTIAL` | Assessment routes/controllers; delete/associação de questões pendentes | `TEST_VERIFIED` |
| Assessment | attempts, snapshot e score | `IMPLEMENTED` | actions, models, `AttemptApiTest`; aborts 422 ainda são finding M-02 | `TEST_VERIFIED` |
| Assessment | certificates, emissão e verify público | `PARTIAL` | listeners/actions/routes; PDF, revoke e trigger de quiz pendentes | `TEST_VERIFIED` |
| Financial | ledger Order/Item/Payment e cents | `IMPLEMENTED` | models/migrations, `MoneyNeverFloatTest` | `TEST_VERIFIED` |
| Financial | checkout student, idempotência, `cash`, outbox e matrícula paga | `PARTIAL` | `StoreCheckoutAction`, gateway manager, events/listener; não runtime atual | `TEST_VERIFIED` |
| Financial | orders list/show, webhooks/jobs e adapters externos | `PLANNED` | `40-financial/tasks.md`, ausência das rotas/jobs | `DOCUMENTATION_ONLY` |
| Ecosystem | plugin catalog/activation/config/revisions/entitlements | `PARTIAL` | models/migrations/services, Feature tests | `TEST_VERIFIED` |
| Ecosystem/Financial | gateway Admin tenant (`GET`/`PUT`) e resolver | `IMPLEMENTED` | Ecosystem routes, `TenantGatewayProvider`, registry | `TEST_VERIFIED` |
| Ecosystem | marketplace, subscriptions e platform ledger | `PLANNED` | `50-ecosystem-plugins/tasks.md`, ausência de PlatformOrder | `DOCUMENTATION_ONLY` |
| Cross-cutting | API-first, areas, tenant middleware, Resources/envelopes | `PARTIAL` | `bootstrap/app.php`, routes, Architecture tests; M-01..M-03 | `STATIC_EVIDENCE_ONLY` |
| Personas | instructor end-to-end e Home público | `PLANNED` | `ROADMAP.md`: `INSTRUCTOR-OWN` partial, `HOME-PUBLIC` not-started | `DOCUMENTATION_ONLY` |
| Platform | filas/jobs assíncronos gerais | `SCAFFOLD` | jobs migration/outbox drainer; nenhum Job de produto geral observado | `STATIC_EVIDENCE_ONLY` |
| Product UI | frontend de produto | `SCAFFOLD` | welcome view/Vite/Tailwind apenas; contrato API-only | `STATIC_EVIDENCE_ONLY` |

Não há `RUNTIME_VERIFIED` nesta tabela. “Operational/usable” em auditorias ou roadmap foi preservado
como status de implementação/jornada e não convertido em validação runtime.

## 3. Harness matrix canônica

Legenda: `ENFORCED` bloqueia ou falha o fluxo quando acionado; `ADVISORY` informa; `AVAILABLE` existe
para uso manual/indireto; `PARTIAL` cobre só parte da capacidade; `NOT_AVAILABLE` não foi encontrado;
`UNKNOWN` não foi demonstrado. A coluna “Git hooks” descreve o próprio mecanismo Git, não Claude/Codex.

| Capacidade | Claude | Codex | CI | Git hooks | Evidência/limite |
|---|---|---|---|---|---|
| skill routing | `ENFORCED` como injeção de instrução | `PARTIAL` (`PreToolUse` só) | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `skill-router.sh` informa e sai 0; não bloqueia. |
| destructive guards | `ENFORCED` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `pre-tool-use.sh` apenas no `.claude/settings.json`. |
| tool/path guards | `ENFORCED` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | guard de ferramenta Claude; probes históricos. |
| lifecycle/session hooks | `ENFORCED` | `NOT_AVAILABLE` | `AVAILABLE` apenas no job CI | `AVAILABLE` em commit/push | Claude tem SessionStart/Stop; CI não tem sessão de agente. |
| pre-compact | `ADVISORY` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `pre-compact.sh` imprime orientação, não persiste STATE. |
| post-edit | `PARTIAL` | `NOT_AVAILABLE` | `AVAILABLE` via QA | `NOT_AVAILABLE` | PostToolUse Claude; falha best-effort conforme auditoria. |
| stop/closure | `PARTIAL` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | `verify-changes.sh` seleciona Architecture e pode exit 2; depende de Sail. |
| Git hooks | `AVAILABLE` | `AVAILABLE` | `NOT_AVAILABLE` | `ENFORCED` | `core.hooksPath=.githooks`; pre-commit/pre-push. |
| test selection | `ENFORCED` no Stop Claude | `NOT_AVAILABLE` | `AVAILABLE` (QA fixo) | `NOT_AVAILABLE` | `verify-changes.sh` mapeia diff para Architecture. |
| architecture gates | `PARTIAL` | `NOT_AVAILABLE` | `ENFORCED` via `qa:gate` | `NOT_AVAILABLE` | Execução depende de ambiente; não foi executada nesta sessão. |
| security/dependency gate | `AVAILABLE` | `AVAILABLE` | `ADVISORY` | `ENFORCED` | CI usa `continue-on-error`; hooks bloqueiam pre-push. |
| E2E | `AVAILABLE` | `AVAILABLE` | `NOT_AVAILABLE` | `NOT_AVAILABLE` | runner/specs existem; nenhum HTTP novo foi executado. |
| receipts/evidence | `PARTIAL` | `PARTIAL` | `AVAILABLE` em logs | `AVAILABLE` em saída do hook | Não existe receipt central obrigatório. |
| independent review | `AVAILABLE` via `validate-ai-work` | `AVAILABLE` manual | `NOT_AVAILABLE` | `NOT_AVAILABLE` | Skill/instrução não equivale a revisão automática. |

O Codex realmente recebe: symlink das skills em `.codex/skills`, `graphify hook-check` para Bash e
`skill-router.sh tool` para `Edit|Write|MultiEdit|Bash`, além dos MCPs configurados em `.codex/config.toml`.
Não recebe, pela configuração atual, o guard destrutivo/path, Pint pós-edição, SessionStart,
PreCompact ou Stop de Claude.

## 4. Reconciliação STATE / ROADMAP / tasks

### Resolvido nesta Fase 0

1. `STATE.md` é somente handoff; não pode fechar task nem substituir `tasks.md`.
2. `ROADMAP.md` governa jornadas e gates, mas seus status são de jornada/contrato; não são prova de
   runtime. O snapshot datado de 2026-07-29 foi tratado como snapshot, não como estado executado hoje.
3. `CONTEXT.md`, `PROMPT-CONTINUAR.md`, checklist e auditorias de julho foram rebaixados para
   histórico/superseded por headers, sem apagar conteúdo.
4. O layout real é o modular de cinco módulos; o layout flat de `CONTEXT.md` e parte de
   `backend-patterns.md` não é instrução operacional.
5. Tenant: a regra executável atual é a combinação `ApiContext`/multitenancy + filtros explícitos nas
   Actions/Services, ADR-004 e `TenantScopingTest`; a frase contrária em `AGENTS.md` foi corrigida.

### Drift ou contradições ainda não fechados

| Item | Evidência | Estado |
|---|---|---|
| Tasks Core têm itens `[x]` dentro de `Pending` (E2E/rate limit/hardening/admin users) | `10-core-identity/tasks.md:84-97` | `NEEDS_RECONCILIATION`: não mover/fechar sem decisão de manutenção do histórico. |
| Learning repete módulos, lessons, media e ratings como `[x]` em `Done` e em blocos de `Pending` | `20-catalog-learning/tasks.md:38-65,94-117` | `NEEDS_RECONCILIATION`: separar slice entregue de delta remanescente. |
| Learning marca ranking/rating/material base como entregue e depois registra “delta restante” | `20-catalog-learning/tasks.md:59-65,114-142` | `NEEDS_RECONCILIATION`: escopo de “done” versus alvo ADR-001 não está distinguido. |
| `backend-patterns.md` descreve `app/Plugins`, `app/Support/Ports` e três módulos, contra providers/código atuais | `backend-patterns.md:18-31,74-83,123-131`; `bootstrap/providers.php:3-9` | `NEEDS_RECONCILIATION`: aviso foi adicionado; revisão completa da spec ainda exige decisão. |
| Scribe orienta `/api/v1/auth/login`, rota atual é `/api/v1/core/auth/login` | `config/scribe.php:24,133`; `Core/Routes/api.php:10-18` | `NEEDS_RECONCILIATION`: não alterar config nesta Fase 0. |
| Evidência E2E/testes em STATE/tasks não foi reproduzida no HEAD por Docker indisponível | auditoria base §2/§3 e `STATE.md` anterior | `NEEDS_RECONCILIATION`: precisa de rodada runtime isolada; não é autorização para marcar operacional. |
| `.opencode/opencode.json` ausente, embora `opencode.json` exista | auditoria base L-01 e `validate-harness.py` | `NEEDS_RECONCILIATION`: decidir suporte/configuração OpenCode. |

Contagem usada neste relatório: **5 conflitos/document classifications resolvidos** e **7 itens
`NEEDS_RECONCILIATION`**. A contagem é por cluster decisório, não por ocorrência textual.

## 5. Hierarquia canônica e resolução de conflito

1. **Código executável e runtime observado**: rotas, providers, middleware, models, testes executados e
   banco/app reais vencem descrição histórica. Runtime só vence quando foi realmente observado.
2. **`AGENTS.md`**: contrato operacional do projeto, atualizado para refletir o código; não pode
   contradizer uma decisão executável sem registrar a reconciliação.
3. **`docs/specs/README.md` + specs de arquitetura/domínio + ADRs aceitos**: contrato durável/intenção
   e decisões; não afirmam entrega por si só.
4. **`docs/specs/*/tasks.md`**: status mutável local; `[x]` é evidência declarativa e exige teste/código
   correspondente, não prova runtime. Duplicata ou status em seção errada vira `NEEDS_RECONCILIATION`.
5. **`docs/ROADMAP.md`**: ordem e outcome de jornadas; status de jornada não substitui validação.
6. **`docs/STATE.md`**: ponteiro efêmero da sessão; nunca fonte permanente.
7. **Relatórios, prompts, checklists e notas históricas**: contexto datado; nunca promovidos por serem
   mais recentes ou mais detalhados.

Regra prática: primeiro identificar se a divergência é de **contrato**, **status** ou **evidência**;
depois seguir a fonte de maior precedência. Se o código contradiz spec, registrar o delta e corrigir a
spec/contrato documental na próxima tarefa; não reescrever comportamento automaticamente nesta Fase 0.
Se não houver execução/teste suficiente para arbitrar, usar `NEEDS_RECONCILIATION`, não “operacional”.

## 6. Workstreams seguintes

| Ordem | Workstream | Objetivo | Findings | Risco se adiado | Dependências | Modelo |
|---|---|---|---|---|---|---|
| 1 | Contrato API e superfície pública | Fechar envelope/error paths, exceções 422, exceções legítimas de `JsonResponse`, URL Scribe e inventário legacy; criar prova de contrato | M-01, M-02, M-03, M-08 | consumidores e agentes podem seguir shapes/URLs inválidos e falhas de segurança com envelope divergente | decisões de contrato API e isolamento de runtime para validação | `LUNA_HIGH` |
| 2 | Paridade e prova do harness | Decidir parity Codex/Claude, transformar advisory em política explícita, adicionar probes/testes discriminantes e fechar CI dependency policy | M-04, M-05, M-06, L-01, L-02 | mudanças podem passar por presença textual sem enforcement equivalente; supply-chain pode ficar verde por tolerância | workstream 1 para paths/gates canônicos; decisão humana sobre OpenCode | `LUNA_HIGH` (rascunho mecânico pode ser CHEAP/MECHANICAL) |
| 3 | Migração legacy e fronteiras de módulo | Impedir nova superfície em legacy, priorizar allowlist Eloquent por ciclo de vida e atualizar spec/layout sem refactor amplo | M-07, M-09, L-03 | dívida aumenta e novos agentes podem criar APIs/código em áreas erradas | decisões de contrato de áreas e revisão arquitetural independente | `PREMIUM_REVIEW_ONLY` após proposta da Luna |

Fora desses workstreams, podem esperar sem bloquear a Fase 0: marketplace completo, gateways externos,
PDF, analytics/RabbitMQ, i18n/SEO, comissões e catálogo amplo de plugins, desde que não sejam puxados
como pré-requisito de uma jornada do roadmap.

## 7. Arquivos alterados nesta Fase 0

- `AGENTS.md` — regra de tenant alinhada ao ADR-004/código atual.
- `docs/STATE.md` — handoff efêmero atualizado pelo checkpoint.
- `docs/specs/00-architecture/backend-patterns.md` — aviso de supersession parcial.
- `CONTEXT.md`, `PROMPT-CONTINUAR.md`, `CHECKLIST-VERIFICACAO.md` — headers de histórico/supersession.
- `docs/auditoria-correcoes-2026-07-11-pending.md`, `docs/auditoria-e2e-http-2026-07-16.md` — headers
  de snapshot histórico.
- `docs/reports/CANONICAL-STATE-RECONCILIATION-2026-09-05.md` — este relatório.

Nenhum PHP, migration, teste, script, hook, config de aplicação/CI ou código de produto/harness foi
alterado. Nenhuma task foi movida, fechada ou reclassificada por inferência.
