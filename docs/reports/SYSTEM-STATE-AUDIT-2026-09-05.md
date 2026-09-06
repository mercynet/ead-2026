# Auditoria global do estado do sistema — 2026-09-05

## 1. Executive Summary

Esta é uma auditoria READ-ONLY do estado observado em 2026-09-05. A única alteração feita durante a auditoria foi a criação deste relatório. O `docs/STATE.md` já estava modificado antes da auditoria e foi preservado sem edição.

O repositório não é um produto vazio: há um monólito Laravel modular com cinco módulos, autenticação, tenants, RBAC, catálogo/conteúdo de aprendizagem, matrículas, progresso, avaliações/certificados e uma fatia financeira. A implementação é, contudo, desigual: o núcleo e boa parte de Learning existem, enquanto áreas de instructor, home pública, marketplace/plugins, webhooks e várias capacidades de Assessment/Financial ainda estão explicitamente pendentes.

O harness também é real, especialmente no caminho Claude Code: há skills versionadas, roteamento automático, guards de ferramentas, hooks de sessão/compactação/encerramento, verificador de invariantes, hooks Git e validação estrutural. Ele não é plenamente operacional como sistema único: a configuração Codex cobre apenas parte dos hooks Claude, a validação não é uma suíte comportamental, `pre-compact` é apenas advisory, e a auditoria de dependências é não-bloqueante na CI.

### Veredictos

| Dimensão | Veredicto | Base resumida |
|---|---|---|
| Produto | `PRODUCT_PARTIALLY_IMPLEMENTED` | Cinco módulos e muitos fluxos implementados, mas roadmap ainda marca várias superfícies como parciais ou não iniciadas. Runtime não pôde ser validado por indisponibilidade do Docker. |
| Harness | `HARNESS_OPERATIONAL_WITH_GAPS` | Fluxos executáveis e probes positivos/negativos funcionam; há diferenças entre ferramentas e ausência de testes discriminantes suficientes. |
| Organização | `PARTIALLY_DISORGANIZED` | Estrutura canônica existe, mas há documentação histórica/aspiracional concorrente, dívida de fronteiras e contratos de resposta/documentação divergentes do código. |

### Contagem de findings

`BLOCKER: 0` · `HIGH: 0` · `MEDIUM: 9` · `LOW: 3`.

Não foi encontrada vulnerabilidade crítica confirmada. A confiança sobre funcionamento em runtime é limitada: o daemon Docker não estava acessível neste ambiente, portanto os resultados de código estático não equivalem a uma aprovação funcional.

## 2. Repository State

### Identidade e working tree

- Branch atual: `main`.
- HEAD: `ffe966ca6cccb6c1f4255146ae51f8841c23682a` — `chore(harness): route skills automatically and enforce invariants at turn end`.
- Relação com remoto observada: `main...origin/main`, sem commits à frente ou atrás.
- Working tree: `docs/STATE.md` já estava modificado antes da auditoria; não foi tocado. O relatório é o único arquivo criado pela auditoria.
- Não houve checkout, reset, commit, push, delete, move, migration, alteração de configuração ou fix.
- `core.hooksPath`: `.githooks`.

### Estrutura e volume observado

- 714 arquivos rastreados; 155 commits.
- 5 módulos em `app/Modules`: Core, Learning, Assessment, Financial e Ecosystem.
- Aproximadamente 455 arquivos PHP de aplicação, banco, rotas, bootstrap e configuração.
- 47 documentos Markdown rastreados.
- 100 arquivos de teste rastreados.
- 14 especificações declarativas em `tests/e2e-http/`, além de fixtures.
- Contagem estática de declarações de rota HTTP: 78. A contagem efetiva de `route:list` não foi confirmada.

### Stack e entrypoints

- PHP 8.4 e Laravel 12; Sanctum 4; Spatie Permission, Multitenancy, Activitylog, Medialibrary e Query Builder; Pest 4; Pint; Larastan; PHP Insights; Scribe.
- Entry point web: `public/index.php`, bootstrap em `bootstrap/app.php`.
- Rotas web: `routes/web.php`, que apenas entrega a welcome view.
- Rotas de produto: carregadas pelos providers dos módulos a partir de `app/Modules/*/Routes/*.php`; não existe `routes/api.php` global.
- Providers registrados em `bootstrap/providers.php`.
- Console: `routes/console.php`, incluindo o agendamento de `financial:drain-order-paid-outbox` a cada minuto.
- Dados: migrations e modelos são distribuídos por módulo; migrations base incluem cache, jobs, tokens, permissões, activity e media.
- Docker/Compose: `compose.yaml` define `laravel.test`, MySQL 8.4, Redis e Mailpit; o container da aplicação é o caminho canônico do projeto. Não há Dockerfile próprio.
- CI: `.github/workflows/qa-gate.yml`, com PHP 8.4 e serviço MySQL 8.4.
- Frontend: apenas scaffolding Laravel/Vite/Tailwind e `resources/views/welcome.blade.php`; não há frontend de produto, em linha com o contrato API-first.

### Configuração e comandos relevantes

`composer.json` expõe `test`, `analyse`, `insights`, `docs`, `qa:deps`, `qa:gate`, `git:hooks` e comandos de desenvolvimento. O contrato do projeto define Sail como forma canônica para PHP, Artisan, Pint, PHPStan e QA. `phpunit.xml` fixa a conexão de teste; `phpstan.neon` usa nível 5 e baseline; `phpinsights.php` define thresholds; `config/scribe.php` gera documentação de `api/v1/*` em `public/docs`.

## 3. Product State

### Módulos e fundamentos existentes

| Módulo | Evidência observada | Estado estrutural |
|---|---|---|
| Core | 6 modelos, 12 migrations, autenticação, usuários, convites, password reset, tenants, RBAC e middleware de tenant/área | Implementação substancial |
| Learning | 15 modelos, 27 migrations, 168 arquivos no módulo; cursos, categorias, módulos, aulas, matrícula, progresso, materiais, mídia e ratings | Implementação substancial, ainda incompleta/migrando superfícies |
| Assessment | 6 modelos, 11 migrations; questionários, perguntas, tentativas, respostas e certificados | Implementação parcial |
| Financial | 5 modelos, 8 migrations; orders, order items, payments, cash gateway, checkout/idempotência e outbox | Implementação parcial |
| Ecosystem | 4 modelos, 5 migrations; plugins, activation, configuração por tenant/revisões, gateways e entitlements | Estrutura funcional parcial |

### Fluxo real observado

1. O bootstrap registra rotas, middleware e exception rendering.
2. O provider de cada módulo carrega migrations, Gates, listeners e arquivos de rota.
3. A request entra em uma rota API `v1`; quando aplicável resolve tenant por headers/domínio/host, cria `ApiContext`, autentica com Sanctum e verifica área/acesso ao tenant.
4. O controller injeta `ApiContext`, autoriza via Gate e delega a uma Action.
5. A Action valida estado e persiste diretamente via Eloquent, normalmente em transação, podendo disparar eventos.
6. O resultado normalmente passa por API Resource; alguns endpoints retornam `JsonResponse` diretamente.
7. Eventos e listeners permanecem síncronos no código observado. Não foram encontrados Jobs de produto em `app/`.

### O que é funcional versus o que é plano

O roadmap classifica `FOUNDATION-0` e `MZRT-SKELETON` como utilizáveis; `ADMIN-OPS`, `STUDENT-PAID` e `INSTRUCTOR-OWN` como parciais; `MZRT-PLATFORM` e `HOME-PUBLIC` como não iniciados. Essa leitura é compatível com o código: existem rotas e Actions reais, mas não há evidência de uma jornada completa e validada em runtime para todas as personas.

Não foi penalizada a ausência de uma funcionalidade apenas porque ela é comum em um LMS. Quando a própria spec/tasks marca a capacidade como pendente, ela foi tratada como escopo ainda não iniciado ou implementação parcial, não como bug.

## 4. Product Capability Matrix

| Capacidade | Evidência concreta | Classificação | Operação confirmada? |
|---|---|---|---|
| Usuários, login, logout, perfil e password reset | `Core/Routes/api.php`, `AuthController`, `LoginAction`, `UserController`, migrations e testes Feature | IMPLEMENTADO E OPERACIONAL em código/testes in-process | Não em runtime nesta auditoria |
| Convites | `Invitation`, Actions, rotas públicas protegidas por throttles nomeados e testes | IMPLEMENTADO E OPERACIONAL em código/testes | Não em runtime |
| Tenants e provisionamento | rotas MZRT, `TenantProvisionService`, models/migrations e E2E declarativo | IMPLEMENTADO PARCIALMENTE | Runner/app indisponível |
| RBAC e permissões | `config/permissions.php`, seeder, Gates/Policies, testes Architecture | IMPLEMENTADO E OPERACIONAL em código/testes | Não em runtime |
| Catálogo de cursos e categorias | Models, migrations, Actions, Resources, rotas Learning e admin | IMPLEMENTADO PARCIALMENTE | Boa cobertura estática; runtime não confirmado |
| Módulos, aulas e conteúdos | Models, Actions e rotas de módulos/aulas | IMPLEMENTADO PARCIALMENTE | Não em runtime |
| Matrículas e acesso | `Enrollment`, Actions, rotas e testes de isolamento/progresso | IMPLEMENTADO PARCIALMENTE | Fluxo pago completo ainda pendente |
| Progresso/conclusão | model, Actions, eventos e endpoints de progresso | IMPLEMENTADO PARCIALMENTE | Não em runtime |
| Mídia e materiais | `LessonMedia`, `CourseMaterial`, URLs/download, validação de paths | IMPLEMENTADO PARCIALMENTE | Upload/abstração MediaProvider ainda ausentes |
| Ratings/ranking | modelos, migrations, Actions e endpoints | IMPLEMENTADO PARCIALMENTE | Há inconsistência nas tasks sobre o delta restante |
| Avaliações/questionários | CRUD, snapshots, tentativas, respostas, scoring | IMPLEMENTADO PARCIALMENTE | Delete/associação e E2E ainda pendentes |
| Certificados | emissão, listagem, show e verificação pública | IMPLEMENTADO PARCIALMENTE | PDF, revoke e eventos complementares pendentes |
| Pagamento/checkout | orders, payments, cash gateway, idempotência, claim/replay e outbox | IMPLEMENTADO PARCIALMENTE | Webhook/Job e gateways externos pendentes |
| Planos/marketplace/plugins | plugin activation/config/revision e entitlements existem | IMPLEMENTADO PARCIALMENTE | Marketplace, billing e adapters first-party pendentes |
| Notificações | `PasswordResetNotification` | IMPLEMENTADO PARCIALMENTE | Não há subsistema geral de notificações |
| Relatórios/analytics | referências documentais e fundamentos de stats, sem superfície de produto completa | ESTRUTURA/PLACEHOLDER | Não confirmado |
| Filas/jobs | migration `jobs`, configuração database e drainer de outbox agendado; nenhum Job de produto encontrado | ESTRUTURA/PLACEHOLDER | Não há processamento assíncrono real demonstrado |
| UI de produto | welcome view e scaffolding Vite/Tailwind apenas | NÃO ENCONTRADO | O contrato declara backend-only |
| Área instructor | tarefas e superfície area-first ainda incompletas | DOCUMENTADO MAS NÃO IMPLEMENTADO / IMPLEMENTADO PARCIALMENTE | Não confirmado |
| Home pública | roadmap marca `HOME-PUBLIC` como não iniciado | DOCUMENTADO MAS NÃO IMPLEMENTADO | Não confirmado |

Itens explicitamente documentados e não encontrados em código incluem `MediaProvider`, `SystemSetting`, `PlatformOrder`, `ProcessPaymentWebhookJob`, `RoleController` e `SuspendOverduePluginSubscriptionsAction`. Isso não é, por si só, defeito: as tasks os registram como próximos trabalhos.

## 5. Harness State

O harness possui uma base operacional, mas a palavra “enforcement” precisa ser delimitada por ferramenta e por fluxo.

### Componentes observados

- 16 diretórios de skills sob `.agents/skills/`, cada um com `SKILL.md`.
- `routing.json` com regras automáticas e skills manuais; `scripts/ai/skill-router.sh` informa skills, mas explicitamente não bloqueia.
- Symlinks de skills para Claude, Codex e OpenCode.
- `validate-harness.py` verifica frontmatter, links, regex, configs, executabilidade e MCP read-only.
- Claude tem hooks de prompt, ferramenta, pós-edição, sessão, compactação e Stop.
- Codex tem hooks de ferramenta para Graphify e roteamento, mas não replica todos os hooks Claude.
- Hooks Git validam harness quando aplicável e auditam dependências em mudanças sensíveis.
- `verify-changes.sh` seleciona testes Architecture conforme arquivos sujos; em mudanças PHP dispara atualização Graphify assíncrona quando o ambiente permite.
- Skills de segurança, routing de área, RBAC, money flows, Pest, validação de trabalho e checkpoint fornecem instruções de domínio.

### Existence / Integration / Enforcement / Tests / Empirical Evidence

| Componente | Existência | Integração | Enforcement | Testes discriminantes | Evidência empírica |
|---|---|---|---|---|---|
| Skill router | Sim | Claude UserPrompt/PreToolUse/PostTool e Codex PreToolUse | Parcial: informa skills, não bloqueia | Não foram encontrados testes dedicados | Probes de prompt/tool retornaram skills corretas |
| Harness validator | Sim | pre-commit quando harness/config muda | Sim, falha em estrutura inválida | Não há suíte dedicada; o próprio comando é a evidência | 36 PASS, 1 WARN, exit 0 |
| Guard de comandos/paths | Sim | Claude PreToolUse | Sim no caminho Claude | Sem testes automatizados encontrados | Probes `rm -rf`, `.env`, vendor, legado e host PHP foram bloqueados; `git status` passou |
| Pint pós-edição | Sim | Claude PostToolUse | Best effort; falha é ignorada | Não encontrado | Não executado com Docker inacessível |
| Invariante no Stop | Sim | Claude Stop | Parcial; depende do hook e ambiente | Não encontrado | `verify-changes.sh` passou com mudança docs-only, sem selecionar testes |
| Contexto/PreCompact | Sim | Claude PreCompact | Não: apenas imprime instruções | Não encontrado | Script saiu 0 e não escreveu STATE |
| Hooks Git | Sim | `.githooks` via `core.hooksPath` | Sim quando commit/push ocorre | Não há testes dedicados | `pre-commit` sem staged changes saiu 0; sintaxe shell passou |
| E2E HTTP runner | Sim | Artisan command/specs | Gate de ambiente/DB no código | Há testes do command, não execução externa nesta auditoria | Não executado por Docker indisponível |
| Security audit de dependências | Sim | hooks/CI/composer script | Localmente pretendido; CI não-bloqueante | Há teste do command, não auditoria externa nesta auditoria | Não executado |
| Área/tenant/module architecture tests | Sim | PHPUnit/Pest Architecture | Sim apenas quando executados | Existem testes, mas parte da análise é textual/allowlist | Não executados por Docker indisponível |

## 6. Harness Component Matrix

| Componente/área | Classificação | Evidência e limite |
|---|---|---|
| Rules canônicas | OPERACIONAL | `AGENTS.md` é contrato amplo; `routing.json` e skills estão presentes. Há documentos históricos que continuam acessíveis. |
| Skills | OPERACIONAL | Skills linkadas entre ferramentas; validator confirmou estrutura. |
| Prompts/contexto | PARCIALMENTE OPERACIONAL | `docs/STATE.md` e skill checkpoint existem; PreCompact não persiste automaticamente. |
| Agentes/subagentes | SOMENTE ESTRUTURAL | Há instruções de economia/revisão, mas não foi encontrada execução verificável de subagentes no repositório. |
| Hooks Claude | OPERACIONAL COM GAPS | Cobre lifecycle amplo, mas pós-edição é best effort e Stop depende de Docker/testes. |
| Hooks Codex | PARCIALMENTE OPERACIONAL | Só Graphify + router PreToolUse; faltam guard destrutivo, post-edit, Stop, SessionStart e PreCompact equivalentes. |
| Lifecycle de task | PARCIALMENTE OPERACIONAL | Specs/tasks/ROADMAP/STATE e checkpoint existem; persistência/enforcement não é uniforme. |
| Task adoption/creation | DOCUMENTAÇÃO/INTENÇÃO | Tasks em specs existem, mas não foi encontrado um mecanismo executável que adote/feche uma task com prova completa. |
| Evidence/receipts | SOMENTE ESTRUTURAL | Há linguagem e arquivos de estado, porém não há store/receipt central executável identificado. |
| Gates/risk engine | PARCIALMENTE OPERACIONAL | Há guards, routing e testes Architecture; não foi encontrado motor separado de risco ou ledger de decisão. |
| Scope detection | PARCIALMENTE OPERACIONAL | `verify-changes.sh` mapeia caminhos para testes; cobertura é baseada em padrões e escopos conhecidos. |
| Coverage/test selection | PARCIALMENTE OPERACIONAL | Seleção de invariantes existe; cobertura percentual não é medida na CI atual. |
| Destructive guards | OPERACIONAL NO CAMINHO CLAUDE | Probe real bloqueou comandos perigosos; cobertura depende da ferramenta e do payload. |
| Security gates | PARCIALMENTE OPERACIONAL | Hooks/comandos existem, mas `qa:deps` tem `continue-on-error` na CI. |
| HEAD/commit validation | PARCIALMENTE OPERACIONAL | Hooks e status reportam git; não há evidência de um receipt de HEAD anexado ao fechamento. |
| Independent review | DOCUMENTAÇÃO/INTENÇÃO | `validate-ai-work` e contrato descrevem revisão; não há pipeline independente obrigatório identificado. |
| Closure | PARCIALMENTE OPERACIONAL | Stop e `verify-changes.sh` existem em Claude; não há equivalência Codex. |
| AI automation/CI | PARCIALMENTE OPERACIONAL | CI roda QA gate; não roda o conjunto completo de enforcement do harness. |

## 7. Harness ↔ Product Alignment

### Alinhamentos confirmados

- O harness conhece os cinco módulos e há testes de fronteira, controller leanness, tenant scoping, permissões, PII, envelopes, superfície de segurança, área e Scribe.
- O router possui regras específicas para Financial, security, RBAC, routing de área, Pest, vertical slice e outras costuras reais.
- O `verify-changes.sh` relaciona alterações em rotas, RBAC, controllers, models/schema e módulos a testes Architecture.
- O contrato de Docker, as skills e os scripts reconhecem que o código PHP roda no container.
- O E2E runner possui guards contra ambiente/banco inadequados, e existem specs declarativas para partes reais de Core/Learning.

### Gaps concretos

1. A configuração Codex não recebe todo o enforcement que Claude recebe. Uma sessão Codex pode não passar pelo guard destrutivo, Pint pós-edição, checkpoint de sessão e verificação Stop.
2. A maior parte da proteção Architecture é textual ou baseada em allowlists. `ModuleBoundaryTest`, `TenantScopingTest` e `ControllerLeannessTest` não provam completamente semântica de runtime.
3. `AreaRouteGuardTest` deliberadamente não cobre os prefixos legados `v1/core`, `v1/learning` e `v1/assessment`. Isso corresponde ao plano de migração, mas deixa a superfície legacy fora do guard de área.
4. `RouteSecuritySurfaceTest` cobre a allowlist atual de rotas públicas e throttles específicos, mas não constitui uma regra geral para toda nova rota anônima mutante.
5. O sistema tem capabilities reais que o harness só conhece como tasks/documentação: webhook financeiro, MediaProvider, marketplace, PDF/revoke de certificados e superfície instructor/home.
6. O estado de “verified/closed” não é um artefato central com receipt verificável; o fechamento depende de scripts/hooks e da disciplina do agente.
7. Coverage não mede execução real na CI. O harness pode afirmar que selecionou invariantes sem quantificar cobertura funcional do produto.
8. O runtime não foi observado nesta auditoria porque Docker não pôde ser acessado; portanto nenhum gate de aplicação foi empiricamente confirmado nesta máquina.

## 8. Architecture Observed

### Camadas

`bootstrap/app.php` configura routing, aliases de middleware e renderização de exceções. Cada Module Service Provider registra migrations, Gates, listeners e rotas. As rotas compõem middleware de tenant, contexto API, Sanctum, guard de área e acesso ao tenant conforme a superfície.

Os controllers são relativamente finos e delegam para Actions. As Actions concentram validação de estado, transações, Eloquent e emissão de eventos. Models e migrations vivem dentro dos módulos. Resources são usados em boa parte da saída, mas existem respostas JSON diretas. Contracts/Events são a via pretendida de comunicação entre módulos.

### Fronteiras e acoplamentos

As fronteiras modulares existem, mas a própria `ModuleBoundaryTest` mantém dívida conhecida de imports Eloquent entre Core/Learning/Assessment. Exemplos incluem `Core/Models/User` importando `Assessment` e `Learning/Models/Course` importando `Assessment`; Assessment também referencia modelos de Learning. A allowlist evita que isso seja tratado como regressão, mas a dependência continua arquiteturalmente presente.

O código atual diverge de parte da documentação de patterns: a implementação mantém costuras em módulos (`Financial/Gateways`, Ecosystem e futuras MediaProvider), enquanto a documentação antiga ainda menciona `app/Plugins` e `app/Support/Ports`. O contrato `AGENTS.md` registra essa dívida e determina que o código vence.

### Request-to-data flow

`HTTP request → module route → middleware/ApiContext/tenant/Sanctum/area → thin controller → Gate → Action → Eloquent model/transaction → event/listener ou Resource → JSON envelope`.

Há uma trilha financeira adicional `OrderPaid → outbox → scheduled drainer`; não há `ShouldQueue`/Job de produto encontrado. Os eventos/listeners observados são síncronos.

### Legado e scaffolding

- Rotas `v1/core`, `v1/learning` e `v1/assessment` são domain-first e ficam fora da regra de guard de área, conforme contrato e roadmap.
- Diretórios vazios como `app/Http`, `app/Financial` e scaffolding de Vite podem induzir novos agentes a usar uma arquitetura que não existe mais.
- `routes/web.php` e a welcome view são infraestrutura Laravel, não UI de produto.

## 9. Test & Quality State

### Inventário estático

- Unit: 8 arquivos, aproximadamente 20 casos.
- Feature: 58 arquivos, aproximadamente 547 casos.
- Architecture: 12 arquivos, aproximadamente 20 casos top-level.
- E2E PHPUnit/Pest: 2 arquivos, aproximadamente 2 casos.
- Total aproximado por contagem estática de `it()`/`test()`: 589 casos.
- E2E HTTP externo: 14 specs declarativas, com fixtures; não é substituto do Feature in-process.
- Não há Jobs de produto e não há evidência de cobertura percentual atualizada.

### Checks executados

| Check | Resultado | Interpretação |
|---|---|---|
| `python3 scripts/ai/validate-harness.py` | Exit 0; 36 PASS, 1 WARN | Harness estrutural válido; warning sobre `.opencode/opencode.json` ausente |
| `bash scripts/ai/skill-router.sh list` | Exit 0 | Tabela de roteamento legível |
| Probes do router prompt/tool | Exit 0 | Financial/security e vertical-slice foram roteados corretamente |
| Probes do `pre-tool-use.sh` | Bloqueios esperados; comandos seguros passaram | Guard Claude empiricamente respondeu aos casos testados |
| `bash -n scripts/ai/*.sh .githooks/*` | PASS | Sintaxe shell válida |
| `git diff --check` | PASS | Sem whitespace error no diff pré-existente |
| `bash .githooks/pre-commit` sem staged changes | Exit 0 | Não exercitou os ramos condicionais de staged files |
| `bash scripts/ai/verify-changes.sh` | Exit 0, sem testes selecionados | Só havia mudança docs-only pré-existente; não prova invariantes de produto |
| `bash scripts/ai/session-start.sh` | Exit 0 | Sessão reportou branch/HEAD/STATE |
| `bash scripts/ai/pre-compact.sh` | Exit 0 | Apenas imprimiu instruções; não persistiu contexto |
| `docker compose config --quiet` | Exit 0, warnings de `WWWUSER/WWWGROUP` não definidos | Compose é sintaticamente válido |
| Docker exec / Artisan / Pest / Pint / PHPStan | Não executado: daemon Docker inacessível | Não classificar como falha funcional |

### Checks não executados deliberadamente

`qa:gate`, `qa:deps`, `composer audit`, `artisan test`, `route:list`, `scribe:generate` e E2E HTTP não foram executados porque dependem do container/serviços ou escrevem artefatos. Rodar Scribe também alteraria artefatos ignorados de documentação; não seria apropriado neste escopo READ-ONLY.

## 10. Documentation / Sources of Truth

### Fontes que parecem canônicas

1. `AGENTS.md`: contrato operacional principal, com ressalva de que este relatório foi produzido sob instrução READ-ONLY do usuário.
2. `docs/specs/README.md` e `docs/specs/`: regras por domínio, `spec.md` sem status e `tasks.md` com status.
3. Código, bootstrap, providers e rotas: estado executável observado.
4. `docs/ROADMAP.md`: fases cross-domain, snapshot declarado em 2026-07-29.
5. `docs/STATE.md`: handoff de sessão, atualmente modificado antes da auditoria.

### Documentos históricos ou conflitantes

- `docs/specs/00-architecture/backend-patterns.md` descreve paths antigos (`app/Plugins`, `app/Support/Ports`, etc.) e também declara a migração como concluída em outra seção.
- `CONTEXT.md`, `PROMPT-CONTINUAR.md` e `CHECKLIST-VERIFICACAO.md` são explicitamente históricos pelo contrato, mas continuam presentes e facilmente encontrados.
- `PROMPT-CONTINUAR.md` traz contagens antigas de testes e uma alegação de 87,5% de cobertura que não é reproduzida pela configuração/CI atual.
- `docs/auditoria-correcoes-2026-07-11-pending.md` mantém `Status: PENDING` e mistura itens históricos corrigidos com itens de contexto.
- `docs/auditoria-e2e-http-2026-07-16.md` descreve um estado antigo/NO-GO e não deve ser tratado como evidência de runtime atual.
- `docs/specs/20-catalog-learning/tasks.md` tem itens anteriormente marcados como concluídos para ratings/materials/media e uma seção posterior que ainda descreve delta restante; requer interpretação humana.
- `config/scribe.php` aponta login para `/api/v1/auth/login`, enquanto a rota observada é `/api/v1/core/auth/login`. A mesma referência incorreta aparece no output ignorado do Scribe.
- `public/docs` e `.scribe` são artefatos ignorados, não contrato versionado; não há documentação gerada rastreada no Git.

Há, portanto, uma arquitetura executável razoavelmente identificável, mas não uma única fonte documental perfeitamente alinhada. O código e o `AGENTS.md` resolvem alguns conflitos; os documentos históricos ainda aumentam o custo de onboarding.

## 11. Findings

### M-01 — Middleware de tenant monta resposta de erro fora do renderer central

- **Evidência:** `app/Modules/Core/Http/Middleware/EnsureTenantAccess.php:29-38` lê usuário/tenant e retorna diretamente um array HTTP com código `forbidden`.
- **Caminho source → sink:** identidade Sanctum e tenant resolvido → verificação `belongsToTenant` → `response([...], 403)` direto.
- **Impacto:** quebra o vocabulário canônico (`access_denied`) e permite envelopes divergentes por caminho de segurança; os testes smoke atuais verificam status, não o contrato completo.
- **Severidade:** MEDIUM.
- **Confiança:** Alta, por inspeção direta.
- **Recomendação inicial:** centralizar a exceção/renderer e adicionar teste discriminante de código/envelope para isolamento cross-tenant.

### M-02 — `abort(422)` em Actions não está coberto pelo contrato central de validação

- **Evidência:** `Assessment/Actions/StartAttemptAction.php`, `SubmitAnswerAction.php`, `FinishAttemptAction.php`, `UpdateQuestionAction.php`, `DeleteQuestionnaireAction.php`, `StoreQuestionnaireAction.php` e `Learning/Actions/GenerateCourseMaterialDownloadUrlAction.php` usam `abort(422, ...)`.
- **Impacto:** esses caminhos podem produzir HttpException genérica em vez de `validation_error` no envelope padrão; não foi encontrado teste que exercite cada abort.
- **Severidade:** MEDIUM.
- **Confiança:** Alta para a existência do caminho; média para a forma final sem runtime.
- **Recomendação inicial:** representar erros de domínio/validação pelos tipos tratados no bootstrap e criar asserts de envelope para cada classe de regra.

### M-03 — Saídas de sucesso ainda escapam de Resource/envelope uniforme

- **Evidência:** `Core/Http/Controllers/AuthController.php:38-45,56-67,78-89,107-115`, `Core/Http/Controllers/UserController.php:78-92`, `Learning/Http/Controllers/EnrollmentController.php:81-92,118-126` e `Learning/Http/Controllers/CertificateController.php:59-64` retornam `JsonResponse`/mensagens diretamente em vez de um contrato uniformemente arbitrado por Resources.
- **Impacto:** consumidores podem receber shapes diferentes; `EnrollmentController` possui retorno de mensagem sem `data`, e não há teste Architecture que prove que todo sucesso é Resource/envelope.
- **Severidade:** MEDIUM.
- **Confiança:** Alta para a divergência estrutural.
- **Recomendação inicial:** inventariar conscientemente exceções legítimas (auth/action responses) e cobrir o restante com Resources e teste de contrato.

### M-04 — Auditoria de dependências é advisory na CI

- **Evidência:** `.github/workflows/qa-gate.yml:62-64` executa `composer qa:deps` com `continue-on-error: true`; `qa:gate` é chamado separadamente.
- **Impacto:** uma falha de supply chain pode deixar o job verde, apesar de o contrato dizer que hooks/pre-push bloqueiam auditoria falha.
- **Severidade:** MEDIUM.
- **Confiança:** Alta, por leitura direta do workflow.
- **Recomendação inicial:** decidir e documentar a política de exceção; se o gate deve ser obrigatório, remover a tolerância ou materializar baseline aprovado como condição explícita.

### M-05 — Enforcement do Codex é menor que o enforcement Claude

- **Evidência:** `.codex/hooks.json:2-22` cobre Graphify e router em `PreToolUse`; `.claude/settings.json` também liga guard de ferramenta, PostToolUse, Stop, SessionStart e PreCompact.
- **Impacto:** trajetórias Codex não têm a mesma proteção contra operações destrutivas, formatação, invariantes de encerramento e persistência de contexto. O contrato do projeto apresenta essas proteções como gerais.
- **Severidade:** MEDIUM.
- **Confiança:** Alta para a diferença de configuração; o impacto é de enforcement potencial.
- **Recomendação inicial:** alinhar hooks por ferramenta ou declarar uma matriz de capacidades suportadas e testá-la com probes equivalentes.

### M-06 — Harness tem pouca prova comportamental discriminante

- **Evidência:** não foram encontrados testes dedicados para `skill-router.sh`, `pre-tool-use.sh`, `verify-changes.sh`, `pre-compact.sh` ou `validate-harness.py`. `ModuleBoundaryTest`, `TenantScopingTest` e `ControllerLeannessTest` usam regex/allowlist e heurística, conforme seus próprios arquivos.
- **Impacto:** o harness pode passar por presença/forma textual enquanto não prova o bloqueio ou o comportamento semântico esperado. Os probes manuais desta auditoria cobrem somente uma amostra.
- **Severidade:** MEDIUM.
- **Confiança:** Alta quanto à ausência de testes localizados; média quanto à cobertura total do repositório.
- **Recomendação inicial:** criar uma matriz de testes negativos para hooks/scripts e separar explicitamente “scanner heurístico” de “prova runtime”.

### M-07 — Superfícies legacy ficam fora do guard de área

- **Evidência:** `AGENTS.md:118-126` documenta a exceção; `app/Modules/Core/Routes/api.php`, `Learning/Routes/api.php` e `Assessment/Routes/api.php` usam prefixos `v1/core`, `v1/learning` e `v1/assessment` sem `area.guard`.
- **Impacto:** um endpoint novo colocado na superfície legacy pode escapar do invariante de persona/área. É uma dívida de migração explícita, não um bug confirmado.
- **Severidade:** MEDIUM.
- **Confiança:** Alta.
- **Recomendação inicial:** manter inventário de migração e adicionar uma proteção que impeça novas rotas de produto de nascerem nos prefixos legacy, sem reescrever os endpoints atuais de forma indiscriminada.

### M-08 — Documentação e contrato Scribe divergem do código

- **Evidência:** `config/scribe.php:24-25,133` usa `/api/v1/auth/login`, mas Core expõe `/api/v1/core/auth/login`; `backend-patterns.md` mistura layout antigo e novo; `tasks.md` contém estados contraditórios de Learning; documentos históricos mantêm contagens/cobertura antigas.
- **Impacto:** onboarding, geração de exemplos e consumidores podem seguir uma URL ou arquitetura inexistente. A documentação pública gerada também não é versionada.
- **Severidade:** MEDIUM.
- **Confiança:** Alta para a URL errada e os conflitos documentais citados.
- **Recomendação inicial:** declarar uma política de documentos históricos, comparar specs com rotas/modelos em um check, e tratar a documentação Scribe versionada/gerada como contrato deliberado.

### M-09 — Dívida de acoplamento Eloquent entre bounded contexts está congelada em allowlist

- **Evidência:** `tests/Architecture/ModuleBoundaryTest.php` mantém allowlist de imports Core/Learning/Assessment; exemplos concretos incluem `Core/Models/User`, `Learning/Models/Course`, `Assessment/Models/Certificate`, `Assessment/Models/QuizQuestion` e Actions de Assessment que importam Learning.
- **Impacto:** ciclos e dependências internas atravessam módulos, dificultando evolução independente e tornando o teste verde dependente de exceções conhecidas.
- **Severidade:** MEDIUM.
- **Confiança:** Alta; a dívida é explicitamente visível e documentada.
- **Recomendação inicial:** não converter por reflexo; priorizar os acoplamentos que atravessam mudança de ciclo de vida e migrá-los gradualmente para Events/Contracts, atualizando spec e allowlist a cada fatia.

### L-01 — Configuração OpenCode opcional ausente

- **Evidência:** `validate-harness.py` reportou 1 warning: `.opencode/opencode.json is missing`; a configuração raiz `opencode.json` existe.
- **Impacto:** pode ser apenas uma escolha de instalação local, mas deixa ambíguo qual arquivo é a configuração operacional.
- **Severidade:** LOW.
- **Confiança:** Alta.
- **Recomendação inicial:** documentar se o arquivo é opcional ou garantir que o fluxo suportado não dependa dele.

### L-02 — PreCompact é lembrete, não persistência automática

- **Evidência:** `scripts/ai/pre-compact.sh:10-19` apenas imprime instruções e verifica dirty count; não escreve `docs/STATE.md`.
- **Impacto:** a perda de contexto depende da disciplina do agente; o lifecycle não garante handoff.
- **Severidade:** LOW.
- **Confiança:** Alta.
- **Recomendação inicial:** manter advisory se a restrição de escrita for intencional, mas não descrevê-lo como checkpoint executável.

### L-03 — Scaffolding e paths legados aumentam risco de onboarding

- **Evidência:** diretórios vazios `app/Http`/`app/Financial`, scaffolding Vite/Tailwind e documentos com paths antigos coexistem com a arquitetura modular; o guard de paths foi testado com caminhos absolutos, que bloqueou os legados.
- **Impacto:** novos agentes podem criar código na superfície errada; não há dano funcional confirmado.
- **Severidade:** LOW.
- **Confiança:** Alta quanto à existência; baixa quanto a uso real futuro.
- **Recomendação inicial:** reduzir ambiguidade documental e tornar os caminhos suportados mais visíveis antes de remover scaffolding.

## 12. Global Verdicts

### Produto — `PRODUCT_PARTIALLY_IMPLEMENTED`

Há implementação significativa e test harness de produto: Core, Learning e partes de Assessment/Financial/Ecosystem atravessam migrations, models, Actions, controllers, Resources e rotas. Ao mesmo tempo, o roadmap marca fluxos fundamentais como parciais ou não iniciados; faltam webhook/Jobs financeiros, marketplace/plugins completos, MediaProvider, várias operações de Assessment e as superfícies Home/Instructor. Como o runtime não foi executado, “operacional” aqui significa evidência estática e testes existentes, não aprovação end-to-end atual.

### Harness — `HARNESS_OPERATIONAL_WITH_GAPS`

O validator, router, guards, hooks Claude, hooks Git e seleção de invariantes são executáveis e produziram evidência manual positiva. Não é `HARNESS_OPERATIONAL` sem ressalvas porque Codex não tem a mesma integração, o pre-compact não persiste estado, a CI tolera falha de dependências, não há suíte comportamental dedicada para os scripts e o ambiente runtime não permitiu executar os gates de aplicação.

### Organização — `PARTIALLY_DISORGANIZED`

Existe uma organização intencional: contrato principal, specs por domínio, roadmap/state, módulos e suites Architecture. A desorganização não é ausência de estrutura; é a coexistência de fontes históricas, paths aspiracionais, divergência Scribe, status de tasks ambíguo, allowlists de fronteira e capacidades parcialmente implementadas sem uma matriz canônica versionada. O projeto é recuperável sem recomeço, mas precisa primeiro reduzir ambiguidade.

## 13. Recommended Organization Roadmap

### FASE 0 — Establish canonical state

- **Objetivo:** transformar o estado observado em referência única e verificável.
- **Atacar:** separar docs canônicos de históricos; reconciliar `AGENTS.md`, specs, tasks, roadmap e código; registrar a limitação de runtime e a contagem real de testes sem alegações antigas.
- **Resultado esperado:** uma tabela de capabilities e uma matriz “código/spec/rota/teste/runtime” que diga explicitamente implementado, parcial, planejado ou desconhecido.

### FASE 1 — Organizar o produto sem refatorar prematuramente

- **Objetivo:** consolidar o inventário por jornada/persona e módulo.
- **Atacar:** marcar Core/Learning/Assessment/Financial/Ecosystem e as áreas legacy/area-first; distinguir scaffolding, capability entregue e task futura.
- **Resultado esperado:** nenhuma task ativa apontando para símbolo inexistente sem status explícito; prioridades baseadas em jornadas quebradas, não em limpeza cosmética.

### FASE 2 — Organizar o harness

- **Objetivo:** definir o conjunto de enforcement suportado por Claude, Codex e demais ferramentas.
- **Atacar:** matriz de hooks; tests negativos para router/guards/Stop/selection; política para PreCompact, receipts e independent review; decisão sobre `.opencode/opencode.json`.
- **Resultado esperado:** saber, por ferramenta, o que bloqueia, o que apenas informa e o que é responsabilidade humana.

### FASE 3 — Alinhar harness ↔ produto

- **Objetivo:** fazer os gates protegerem as superfícies reais sem prometer cobertura que não existe.
- **Atacar:** migração/lock das rotas legacy; checks de Resource/envelope e erros `abort(422)`; cobertura dos novos módulos/capabilities; revisão das allowlists de fronteira e tenant; tornar dependências CI realmente decisivas conforme a política escolhida.
- **Resultado esperado:** cada risco relevante do produto possui teste discriminante e cada exceção arquitetural tem owner/critério de saída.

### FASE 4 — Próxima implementação funcional

- **Objetivo:** só então aumentar a capacidade de produto.
- **Ordem racional sugerida:** fechar contratos API/erros/documentação; validar runtime do caminho atual; completar uma jornada de alto ROI (provavelmente estudante pago, incluindo webhook/async, ou outra prioridade de negócio confirmada); depois instructor/home e marketplace conforme roadmap.
- **Resultado esperado:** cada fatia futura atravessa spec/task, rota correta, autorização, Action, Resource, Feature test, documentação Scribe e E2E externo quando aplicável.

Essa ordem preserva implementação útil, não exige recomeço e evita adicionar features sobre contratos e gates ainda ambíguos.

## 14. Unknowns / Unverified Areas

- `artisan route:list`, `artisan test`, Feature/Architecture/E2E, Pint, PHPStan, Insights, Scribe e `qa:gate` não puderam ser executados: o daemon Docker não estava acessível.
- Não foi verificado o schema/estado real do MySQL, Redis, Mailpit, storage ou filas em execução.
- Não foi possível confirmar status HTTP, envelopes reais, side effects, idempotência no banco ou isolamento em um servidor vivo.
- A contagem de testes é aproximada por inspeção estática, não pelo PHPUnit.
- A contagem de rotas é de declarações fonte, não da tabela de rotas carregada.
- Não foi medida cobertura percentual atual; a alegação histórica de 87,5% não foi aceita como evidência atual.
- Não foi possível confirmar se `.opencode/opencode.json` é deliberadamente opcional.
- Não há evidência suficiente para afirmar uso real histórico de todos os hooks, skills, E2E HTTP e comandos de segurança além dos probes locais e do estado/documentação encontrados.
- Nenhum exploit de autenticação, IDOR, traversal, segredo em log ou bypass de tenant foi confirmado por esta auditoria estática. Isso não substitui uma execução autorizada dos testes de segurança e E2E.

## 15. Commands Executed

Comandos de inspeção/check executados, sem alteração de código ou configuração:

- `git status --short --branch`
- `git rev-parse HEAD`, `git log`, `git branch`, `git diff -- docs/STATE.md`
- `git ls-files`, `git status --ignored`, `git config core.hooksPath`, `git remote -v`
- `rg`, `find`, `wc`, `sed`, `nl` para inventário de arquivos, módulos, rotas, modelos, migrations, docs, scripts e testes
- `graphify query "global repository state product architecture harness tests documentation and automation" --budget 2200`
- `bash scripts/ai/skill-router.sh list`
- `python3 scripts/ai/validate-harness.py`
- probes controlados de `skill-router.sh` e `pre-tool-use.sh` para roteamento, comandos seguros e bloqueios; nenhum comando destrutivo foi executado
- `bash -n scripts/ai/*.sh .githooks/*`
- `git diff --check`
- `bash .githooks/pre-commit` sem arquivos staged
- `bash scripts/ai/verify-changes.sh`
- `bash scripts/ai/session-start.sh`
- `bash scripts/ai/pre-compact.sh`
- `docker compose config --quiet`
- tentativas de inspeção via `docker compose ps`/`docker exec` para runtime, que falharam por permissão de acesso ao daemon Docker

Não foram executados comandos host de PHP/Artisan/Pint/PHPStan/Pest, em respeito ao contrato do projeto.

## 16. Evidence References

### Contrato, arquitetura e roadmap

- `AGENTS.md`: API-first, stack, áreas, invariantes, testes, harness e dívida de spec.
- `docs/specs/README.md`: índice das fontes de domínio.
- `docs/ROADMAP.md`: fases e status cross-domain.
- `docs/specs/00-architecture/backend-patterns.md`: padrões e divergência de layout histórico.
- `docs/specs/00-architecture/areas-surfaces.md`: semântica das áreas.
- `docs/specs/00-architecture/testing-strategy.md`: pirâmide e papéis de teste.
- `docs/specs/*/spec.md` e `tasks.md`: contrato/status por Core, Learning, Assessment, Financial e Ecosystem.

### Aplicação

- `bootstrap/app.php`, `bootstrap/providers.php`, `routes/web.php`, `routes/console.php`.
- `app/Modules/*/Providers/*ServiceProvider.php` e `app/Modules/*/Routes/*.php`.
- `app/Modules/*/{Models,Actions,Http,Resources,Policies,Events,Listeners}`.
- `database/migrations/` e migrations dentro dos módulos.
- `config/permissions.php`, `config/lgpd.php`, `config/scribe.php`, `phpunit.xml`, `phpstan.neon`, `phpinsights.php`.

### Harness e automação

- `.agents/skills/*/SKILL.md`, `.agents/skills/routing.json`.
- `scripts/ai/{skill-router,pre-tool-use,post-tool-use,session-start,pre-compact,verify-changes}.sh`.
- `scripts/ai/validate-harness.py`.
- `.claude/settings.json`, `.claude/CLAUDE.md`, `.codex/hooks.json`, `.codex/config.toml`, `opencode.json`.
- `.githooks/pre-commit`, `.githooks/pre-push`.
- `.github/workflows/qa-gate.yml`.

### Testes que arbitram invariantes

- `tests/Architecture/ModuleBoundaryTest.php`.
- `tests/Architecture/ControllerLeannessTest.php`.
- `tests/Architecture/TenantScopingTest.php` e `TenantIsolationSmokeTest.php`.
- `tests/Architecture/RouteSecuritySurfaceTest.php` e `AreaRouteGuardTest.php`.
- `tests/Architecture/ErrorEnvelopeShapeTest.php`.
- `tests/Architecture/ScribeAuthAnnotationMatchesMiddlewareTest.php`.
- `tests/Architecture/PiiAuditTest.php`, `PermissionDriftTest.php`, `PermissionMetadataShapeTest.php`, `MoneyNeverFloatTest.php`.
- `tests/Feature/Console/*` e `tests/e2e-http/`.

### Working tree preservado

- `docs/STATE.md`: alteração pré-existente, mantida intacta.
- `docs/reports/SYSTEM-STATE-AUDIT-2026-09-05.md`: único artefato criado nesta auditoria.
