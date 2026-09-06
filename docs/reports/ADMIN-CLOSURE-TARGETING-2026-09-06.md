# ADMIN Closure Targeting — revisão crítica — 2026-09-06

## Escopo e evidência

Esta revisão transforma `docs/reports/ADMIN-GAP-ANALYSIS-2026-09-06.md` em um plano mínimo de fechamento. Ela é **READ-ONLY** sobre o produto: nenhum código, teste, spec, roadmap, `STATE`, hook, configuração, script ou CI foi alterado.

Classificação de evidência desta revisão: **STATIC_EVIDENCE_ONLY**. Não houve execução do app, Feature test, Architecture test, Scribe, E2E HTTP ou consulta ao banco durante esta revisão. Portanto, referências a testes existentes significam que o teste está presente no repositório; não significam `TEST_VERIFIED` nesta sessão.

Fontes principais: contrato em `AGENTS.md`, `docs/ROADMAP.md`, `docs/specs/00-architecture/areas-surfaces.md`, specs/tasks de Core, Learning e Financial, rotas/controllers/policies atuais, testes existentes, specs em `tests/e2e-http/`, `.codex/hooks.json`, `scripts/ai/verify-changes.sh` e os relatórios de estado/harness de 2026-09-05.

## 1. Revised ADMIN_COMPLETE

`ADMIN_COMPLETE` deve significar que um operador Admin consegue operar o mínimo controle de um tenant pela superfície canônica da API, com autorização e evidência verificável:

1. **Identidade e usuários do tenant:** listar/consultar usuários relevantes, convidar, atualizar e remover instrutores/estudantes conforme as regras existentes; aceitar convite continua sendo uma operação neutra/pública, não uma operação Admin.
2. **Controle de conteúdo:** criar, consultar, atualizar, remover e ordenar o conteúdo administrativo necessário — curso, módulos, aulas, metadados/materiais/mídia — além de categorias e publicação/despublicação. Isso cobre gerenciamento, não consumo do aluno.
3. **Operação de matrícula:** listar/consultar, criar, atualizar/cancelar a matrícula administrativa e completar o fluxo de cobrança `cash/manual`, incluindo transição idempotente e efeitos de domínio já previstos.
4. **Superfície e segurança:** endpoints de produto novos nas áreas corretas, stack de middleware/guard exata, isolamento de tenant, ceiling de RBAC, proibição de spoofing de escopo, envelope de erro, ausência de IDOR e contrato Scribe coerente.
5. **Prova de fechamento:** Feature e Architecture tests focados verdes, pelo menos um fluxo E2E HTTP atual contra app/banco reais com side effects verificados, e geração Scribe atual bem-sucedida.

Ficam fora do boundary Admin: conteúdo próprio do Instructor, consumo/progresso/avaliação do Student, checkout automático do Student, papéis customizados e configuração white-label do tenant, reporting, upload/provider de mídia real, assessment completo, certificados e plataforma MZRT. Esses itens podem ser importantes no roadmap, mas não são pré-requisito para o mínimo controle administrativo.

Essa fronteira é consistente com a separação de áreas de `docs/specs/00-architecture/areas-surfaces.md` (Admin opera todo o tenant; Instructor opera seu conteúdo; Student consome) e com a regra de que leitura e escrita podem estar em áreas distintas, desde que o escopo não venha do payload. Não se deve resolver o gap Admin migrando rotas de consumo, progresso ou ownership do Instructor para Admin.

Os cinco MUST originais continuam representando a maior parte do risco real, mas não têm a mesma natureza: ADM-01/02/03 são lacunas de superfície/capacidade; ADM-04 é um gate de evidência; ADM-05 não é uma capacidade independente e deve ser absorvido pelos critérios de cada slice e pelo gate final.

## 2. MUST Reviews

### ADM-01 — identidade e usuários Admin

**Verdict: `CONFIRMED_MUST` — HIGH.**

| Item | Revisão |
|---|---|
| Capability/jornada | Admin identifica e opera usuários do próprio tenant: listagem/consulta, convite e ciclo administrativo de update/delete de instrutor/estudante. |
| Evidência | `app/Modules/Core/Routes/admin.php:11-25` já tem update/delete com stack Admin; `app/Modules/Core/Routes/api.php:43-74` ainda expõe list/show/invite em `/api/v1/core` sem guard de área. Há Feature tests em `tests/Feature/Api/Core/Users/UserAdminApiTest.php` e `AdminUserManagementApiTest.php`; o E2E `tests/e2e-http/core/admin-users.php` cobre apenas o subconjunto update/delete. |
| Faltante | Uma superfície canônica e coerente para as operações Admin ainda legadas, com guard Admin exato e contrato Scribe correspondente. Convite aceitar continua fora dessa migração, por ser neutral/public. |
| Por que bloqueia | Sem isso, a jornada administrativa de usuários depende de uma superfície domínio-first que o contrato diz ser legado e não comprova a área Admin. O operador não tem um ciclo Admin canônico completo. |
| Dependências | Rotas/controllers/requests/resources Core, políticas e permissions já existentes, tenant context, área Admin e Scribe. Não depende de Instructor nem de Financial. |
| Escopo mínimo | Convergir list/show/invite create para Admin; preservar compatibilidade legada enquanto o inventário/decisão de remoção não existir; manter accept público/neutro; não permitir `tenant_id`, `user_type` ou equivalentes no payload. |
| Testes | Feature de happy path, 401/403, ceiling Admin, peer Admin, self, cross-tenant/404 defensivo, payload proibido e envelope; Architecture de área/superfície/Scribe/tenant/PII. |
| E2E/runtime | Necessário para fechamento: Admin login/contexto → list/show → convite → update/delete, com tenant negativo e resposta real. |
| Risco/esforço | Risco médio-alto de contrato, PII e IDOR; esforço médio; modelo `LUNA_HIGH`. |

### ADM-02 — controle de curso e conteúdo

**Verdict: `CONFIRMED_MUST` — `BLOCKER` mantido, porém estreitado.**

| Item | Revisão |
|---|---|
| Capability/jornada | Admin controla o conteúdo do próprio tenant: curso e seu ciclo, módulos, aulas e metadados/materiais/mídia administrativos, categorias e publicação. |
| Evidência | `app/Modules/Learning/Routes/api.php:37-97` concentra CRUD legacy de módulos, aulas, materiais, mídia e parte de cursos/matrículas sem `area.guard`; `app/Modules/Learning/Routes/admin.php:12-20` já tem show/publish/unpublish/categorias, mas não o CRUD administrativo completo. Policies e Feature tests existentes mostram checagens de tenant, permission e ownership; os E2E Admin de cursos/categorias/publicação são evidência de superfície parcial, não de cobertura completa nem de runtime atual. |
| Faltante | A superfície Admin canônica para o controle de conteúdo ainda não cobre a jornada inteira. A existência de policy e de happy paths legacy não equivale a uma API Admin fechada. |
| Por que bloqueia | O Admin não consegue operar o controle mínimo de catálogo/conteúdo pela superfície que o contrato considera correta. Sem curso/conteúdo, matrícula administrativa também não possui um objeto operacional completo. O bloqueio é principalmente de produto/API boundary; não há evidência nesta revisão de um bypass de autorização já demonstrado. |
| Dependências | Rotas Learning, requests/resources/controllers/actions, policies/permissions, tenant scoping, área/guard e Scribe. A parte de ownership do Instructor deve permanecer como regra negativa/independente. |
| Escopo mínimo | Cobrir somente operações de gerenciamento Admin: curso list/create/show/update/delete, módulos, aulas, material/mídia administrativa, ordenação, categorias e publish/unpublish. Excluir downloads/consumo, progresso, ratings, lesson access do Student e operações de conteúdo próprio do Instructor. |
| Testes | Feature para cada operação e tenant isolation; 401, área incorreta, persona sem permission, ceiling de RBAC, 404 cross-tenant, payload sem redefinição de escopo, envelope, controller leanness, module boundary e Scribe/middleware. Incluir negativos que provem que a convergência Admin não toma ownership do Instructor. |
| E2E/runtime | Necessário e amplo: criar curso → módulos → aulas → material/metadados → ordenar/publicar, com negativas de Student/Instructor conforme a ownership rule e cross-tenant. O teste deve comprovar side effects no banco. |
| Risco/esforço | Risco alto de boundary, regressão de consumers e contrato legado; esforço alto; modelo `LUNA_XHIGH_REVIEW`. |

### ADM-03 — matrícula Admin e cash/manual

**Verdict: `CONFIRMED_MUST` — HIGH, não BLOCKER independente.**

| Item | Revisão |
|---|---|
| Capability/jornada | Admin cria/consulta/atualiza/cancela matrículas e consegue concluir o caminho cash/manual do tenant de forma idempotente. |
| Evidência | `app/Modules/Learning/Routes/api.php:67-75` deixa CRUD de enrollment na superfície legacy; `app/Modules/Financial/Routes/api.php:10-14` já expõe confirmação manual em Admin. `tests/Feature/Api/Learning/EnrollmentApiTest.php` possui happy paths/isolamento de Admin, e `tests/Feature/Api/Financial/ConfirmManualPaymentApiTest.php` cobre transição, outbox, idempotência, recuperação e negativas. Isso é evidência estática forte, mas o relatório anterior corretamente aponta ausência de prova E2E atual integrada. |
| Faltante | Convergência da operação de matrícula para Admin e prova HTTP atual do caminho content → enrollment → manual payment → efeitos de domínio. |
| Por que bloqueia | Sem matrícula, o controle administrativo não fecha a operação do curso. Cash/manual já tem a lógica necessária, mas a jornada ainda mistura superfície legacy e não tem receipt runtime atual. |
| Dependências | ADM-02 para curso/content operável; Financial cash/manual e outbox já existentes. Não depende de webhook, adapter externo ou Instructor launch. |
| Escopo mínimo | Mover/expor as operações administrativas de enrollment na área Admin com exatamente o guard/tenant/RBAC corretos; manter Student own enrollment/progress fora; preservar confirmação manual e sua idempotência. |
| Testes | Feature de create/list/show/update/cancel, tenant isolation, permission ceiling, estados inválidos, replay idempotente, outbox/enrollment uma vez, envelopes e cross-tenant. Architecture de money/tenant/area/security. |
| E2E/runtime | Necessário: curso elegível → Admin cria matrícula ou confirma cash/manual → verificar order/payment/enrollment/outbox e replay sem duplicação, inclusive negativas de auth/persona/tenant. |
| Risco/esforço | Risco alto por tenant e efeitos financeiros; esforço médio-alto; modelo `LUNA_XHIGH_REVIEW` dentro do slice operacional. |

### ADM-04 — evidência atual de runtime/E2E

**Verdict: `CONFIRMED_MUST` como gate de fechamento, não como feature de produto.**

| Item | Revisão |
|---|---|
| Capability/jornada | Capacidade de afirmar que a jornada Admin realmente funciona no app/banco atuais, além de existir no código. |
| Evidência | `AGENTS.md` estabelece que, sem execução atual adequada, a capability não é `RUNTIME_VERIFIED`; o relatório anterior registra `EVIDENCE_PENDING`. Há specs em `tests/e2e-http/`, mas elas são artefatos de validação, não uma execução atual. |
| Faltante | Receipt atual de Feature/Architecture e E2E HTTP contra app e banco adequados, com side effects; atualidade/proveniência da execução; não apenas presença de arquivos. |
| Por que bloqueia | Bloqueia o nome `ADMIN_COMPLETE`, não o início do desenvolvimento. Sem ele, não é possível distinguir contrato entregue de integração quebrada, nem selar isolamento, autorização e cash/manual em runtime. |
| Dependências | Slices de produto, ambiente E2E válido, banco isolado e execução Scribe. Resolver o receipt Admin não exige abrir todo o WS2. |
| Escopo mínimo | Executar o conjunto focado após ADM-01/02/03, registrar resultado/proveniência e corrigir apenas falhas encontradas no escopo Admin. |
| Testes | Feature/Architecture relevantes e E2E HTTP com side effects, não uma cópia integral do gate MZRT. |
| E2E/runtime | É o próprio requisito; sem runtime atual fica `EVIDENCE_PENDING`. |
| Risco/esforço | Risco de falsa confiança e de ambiente; esforço médio; modelo `PREMIUM_REVIEW_ONLY` no closure slice. |

### ADM-05 — seleção de testes para novas superfícies

**Verdict: `INVALID_GAP` como MUST independente.**

O relatório anterior transforma a necessidade de selecionar testes em um gap separado, mas o repositório já contém testes Feature/Architecture e specs E2E para partes relevantes. O problema real é cobertura/execução atual dos slices e não a ausência de uma capability chamada “seleção de testes”.

ADM-05 deve ser absorvido por:

- critérios de aceite e testes de ADM-01, ADM-02 e ADM-03;
- o gate de evidência de ADM-04;
- a matriz final de rotas, permissions, tenant, envelopes, Scribe e E2E.

Isso preserva a exigência de testar sem criar um MUST artificial, duplicado e potencialmente contável como fechado apenas por adicionar arquivos de teste. Se alguma cobertura concreta faltar, ela reaparece como critério do slice correspondente, não como quinto produto independente.

## 3. ADM-02 Blocker Analysis

O blocker exato não é “Instructor ainda não está pronto” nem “há uma policy inexistente”. É:

> **A jornada de controle de conteúdo necessária ao Admin ainda não tem uma superfície Admin canônica completa; as operações legacy são compartilhadas/mistas e não estão submetidas ao guard de área Admin exigido para fechamento.**

### Natureza do blocker

- **Produto:** o Admin ainda não fecha a operação de criar e manter o conteúdo que administra.
- **API boundary:** operações de gerenciamento vivem em `v1/learning` legacy ou estão apenas parcialmente em `v1/admin`.
- **Autorização:** policies e permissions existentes são evidência positiva, mas não substituem o guard exato da área nem provam que a superfície pública está correta.
- **Ownership:** Instructor continua responsável pelo próprio conteúdo; isso é uma restrição que a solução deve conservar, não uma dependência para iniciar o Instructor.

### Por que impede `ADMIN_COMPLETE`

O boundary definido para Admin inclui catálogo/conteúdo e publicação. Um Admin que só consegue publicar, sincronizar categorias ou acessar operações legacy não tem uma jornada canônica completa para administrar o curso. Fechar Admin nessas condições confundiria “a regra Eloquent existe” com “a superfície do produto está fechada”.

### Menor slice sem abrir Instructor

1. Definir as operações de **management** Admin, mantendo consumo Student e ownership Instructor fora.
2. Colocar essas operações em rotas Admin com `resolve.tenant.optional`, `api.context`, `auth:sanctum`, `area.guard:admin`, `tenant.required.unless.developer` e `tenant.access`, conforme a stack canônica.
3. Reusar Actions/Models/Policies/Resources quando possível, sem permitir importação indevida entre módulos e sem aceitar campos de escopo no payload.
4. Adicionar testes negativos que demonstrem: Instructor só opera seu conteúdo; Student não administra; cross-tenant resulta em 404 defensivo; Admin não recebe privilégio MZRT.
5. Provar o fluxo em E2E antes de declarar o blocker resolvido.

Não é necessário, neste slice, implementar a área Instructor, migrar rotas de consumo, completar Assessment ou resolver webhook/payment adapters. Portanto, ADM-02 permanece `BLOCKER` para fechamento Admin, mas não é um blocker para começar o trabalho nem uma justificativa para abrir um programa Instructor.

## 4. ADM-12 Classification

**Classificação: `DOWNGRADE_TO_LATER` para o fechamento Admin; prioridade HIGH no roadmap Financial/Student.**

ADM-12 é a parte de webhook/job/adapters externos e pagamento automático. É HIGH porque o produto ainda precisa do caminho automático para a jornada Student-paid e porque integrações externas aumentam risco operacional. Porém, não bloqueia `ADMIN_COMPLETE`:

- o Financial já tem preset cash e confirmação manual Admin;
- a confirmação manual tem transição idempotente e outbox conforme `docs/specs/40-financial/tasks.md` e `subspecs/orders-payments.md`/`webhooks-events.md`;
- webhook é propriedade do caminho automático, não do cash offline;
- Admin não precisa receber a jornada Student de checkout automático para administrar o tenant.

Classificá-lo como “HIGH e não MUST Admin” só é consistente se a etiqueta HIGH for entendida como prioridade de produto fora do boundary Admin. Para este fechamento, ele é `LATER`, pertencente ao trabalho Student-paid/Financial.

## 5. Harness Closure Gap

### Causalidade concreta

O `.codex/hooks.json:1-22` configura apenas PreToolUse para Graphify e roteamento de skills. Ele não invoca automaticamente `scripts/ai/verify-changes.sh` ao encerrar, não cria um receipt de E2E/Scribe e não força um lifecycle de validação atual. `scripts/ai/verify-changes.sh` mapeia arquivos sujos para Architecture tests, mas só produz valor quando executado; para docs-only ou ambiente Sail indisponível pode sair sem provar runtime. O relatório de estado do sistema de 2026-09-05 também registra M-05/M-06 como hardening Codex ainda não implementado.

A propriedade Admin que fica não comprovável é:

> **“As superfícies Admin convergidas continuam passando, com o guard correto, tenant isolation, RBAC e side effects cash/manual em um app/banco atuais, e essa prova é reprodutível/proveniente.”**

Isso não prova que o produto esteja quebrado. Prova que a declaração de fechamento não é automaticamente protegida contra uma mudança não testada ou uma falsa conclusão de “arquivo de teste presente”.

### Classificação dos gaps do harness

| Gap | Classificação | Justificativa |
|---|---|---|
| Ausência de guard/área nas rotas legacy Admin | `CAN_FIX_INSIDE_ADMIN_SLICE` | É boundary de produto/API e deve estar resolvido antes do closure; não é reparo genérico de harness. |
| Ausência de execução E2E/runtime atual | `MUST_FIX_BEFORE_ADMIN_CLOSURE` | É a prova que falta para promover a capacidade a runtime verified; pode ser produzida no closure slice. |
| Scribe não gerado/verificado no estado atual | `CAN_FIX_INSIDE_ADMIN_SLICE` | Deve ser resolvido no closure slice; contrato Admin precisa refletir as rotas finais e isso não exige WS2. |
| Falta de Stop/PostToolUse automático para `verify-changes.sh` | `CAN_WAIT_FOR_WS2` | Reduz enforcement contínuo, mas não impede desenvolver nem fechar Admin com receipt explícito. |
| Falta de probes comportamentais dedicados para router/hooks/validator/lifecycle | `CAN_WAIT_FOR_WS2` | É hardening transversal do Codex, não uma dependência da jornada Admin. |
| Falta de capacidade para começar trabalho Admin | **Nenhum gap encontrado** | Rotas, policies, testes e slices existentes permitem começar com escopo controlado. |

Conclusão: o harness **bloqueia a declaração de closure por falta de evidência automatizada/receitada**, não bloqueia o desenvolvimento e não exige abrir WS2 por reflexo. Um receipt específico de Admin — Feature/Architecture + E2E HTTP + Scribe, com proveniência — é suficiente para este fechamento se os responsáveis aceitarem execução manual explícita. O hardening genérico M-05/M-06 pode esperar WS2.

## 6. Missing MUST Search

Foi feita uma busca adversarial contra os candidatos indicados:

| Candidato | Resultado |
|---|---|
| Tenant isolation | Existe middleware/policy/teste de isolamento; a prova final é critério de closure, não novo MUST. |
| Admin RBAC | Permissions, policies, Gates e testes já existem em partes; lacunas de superfície ficam em ADM-01/02/03. |
| Area guard | A ausência nas superfícies legacy está capturada por ADM-01/02/03 e pelo gate de rotas; não é um MUST adicional. |
| Gestão de usuários | ADM-01. |
| Curso/conteúdo necessário ao Admin | ADM-02. |
| Matrícula administrativa | ADM-03. |
| Cash/manual | ADM-03; a capacidade financeira base existe, faltando convergência/prova integrada. |
| Ownership | Regra do Instructor e negativos de ADM-02; não requer iniciar Instructor. |
| Security/API contract | Gate transversal: envelopes, Scribe, tenant, permissions e surface tests; não é jornada Admin adicional. |
| Assessment, certificados, custom roles, tenant config, reporting, upload real, plugins | SHOULD/LATER ou fora do boundary mínimo. |

**Resultado: `NO_ADDITIONAL_MUST_FOUND`.**

## 7. Revised Completion Estimate

**ADMIN completion: 60–70%, confidence medium.**

A faixa sobe modestamente em relação a 55–65% porque há mais implementação e testes existentes do que uma leitura apenas da superfície legacy sugere: user update/delete, policies de tenant/ownership, categorias/publicação, enrollment Admin e cash/manual têm material estático relevante. Ela não sobe para “quase completo” porque os pesos maiores ainda estão abertos:

1. ADM-02 é uma jornada central e um blocker de boundary, não um detalhe de rota.
2. ADM-01 ainda deixa identidade Admin fragmentada entre superfície canônica e legacy.
3. ADM-03 não tem o fluxo administrativo completo na superfície correta nem receipt E2E atual.
4. ADM-04 deixa toda afirmação de runtime pendente.
5. Scribe/Architecture/harness podem transformar uma implementação aparentemente verde em contrato não comprovado.

A faixa não é uma medição de linhas ou contagem de endpoints. É uma estimativa de jornadas críticas restantes e sua evidência.

## 8. Minimal Closure Slices

O menor número coerente é **3 slices**. Quatro separaria artificialmente a evidência ou criaria um slice “misc”. ADM-05 é absorvido pelos critérios dos três.

### Slice 1 — Admin Identity & Onboarding Surface

- **MUSTs:** ADM-01.
- **Entrega:** list/show/invite Admin canônicos; update/delete já existentes alinhados ao mesmo contrato; accept permanece neutral/public; guard, tenant, RBAC, payload e Scribe coerentes.
- **Dependências:** Core routes/controllers/requests/resources/policies/permissions; nenhuma dependência Instructor/Student/Financial.
- **Testes:** Feature de jornada/negativas/tenant/PII; Architecture de surface, guard, Scribe, envelope e tenant.
- **E2E/runtime:** Admin login/contexto, list/show, invite, update/delete e negativas cross-tenant/persona.
- **Harness necessário:** validação manual explícita do receipt; generic Stop/PostToolUse pode esperar WS2.
- **Done:** endpoints Admin canônicos existem, testes focados definidos e passam quando executados, contrato Scribe bate com middleware e a jornada possui E2E atual.
- **Modelo:** `LUNA_HIGH`.

### Slice 2 — Admin Content & Enrollment Operations

- **MUSTs:** ADM-02 e ADM-03.
- **Entrega:** controle de curso/conteúdo Admin, categorias/publicação e matrícula Admin; cash/manual idempotente e seus efeitos permanecem; Student consumption e Instructor ownership não são absorvidos.
- **Dependências:** Slice 1 para actor/tenant contract; Learning routes/actions/policies/resources; Financial cash/outbox já existente. Dentro do slice, content precede enrollment.
- **Testes:** Feature/Architecture de CRUD, permissions, area guard, ownership negativa, tenant isolation, envelope, money/outbox e Scribe.
- **E2E/runtime:** content journey completa seguida de matrícula/cash/manual, side effects no banco, outbox/enrollment sem duplicação e negativas de area/persona/tenant.
- **Harness necessário:** receipt HTTP real e execução dos invariantes mapeados pelos arquivos sujos; não exige abrir WS2.
- **Done:** nenhum endpoint necessário ao Admin fica apenas em superfície legacy sem decisão compatível; área/guard/middleware/RBAC/tenant estão alinhados; fluxo content → enrollment → manual cash é verificável.
- **Modelo:** `LUNA_XHIGH_REVIEW`.

### Slice 3 — Closure Evidence & Contract Seal

- **MUSTs:** ADM-04; absorve ADM-05.
- **Entrega:** execução atual e registrada de Feature/Architecture/E2E, geração Scribe bem-sucedida, receipt/proveniência, limpeza de artefatos de execução quando aplicável e confirmação de que não resta `EVIDENCE_PENDING` no escopo Admin.
- **Dependências:** Slices 1 e 2; app/banco E2E disponíveis; resolução de ownership/permissão do cache Scribe se o ambiente exigir.
- **Testes:** somente o conjunto relevante ao boundary Admin, incluindo erros/tenant/RBAC/area/money/PII/module boundary e E2E com side effects.
- **E2E/runtime:** obrigatório; é o slice final de fechamento.
- **Harness necessário:** receipt Admin específico; hardening genérico de lifecycle/probes fica em WS2.
- **Done:** gate final abaixo satisfeito e verdict promovido somente com evidência corrente.
- **Modelo:** `PREMIUM_REVIEW_ONLY`.

## 9. Dependency Order

1. **Primeiro: Slice 1 — Identity & Onboarding.** É o menor fundamento de actor, tenant e RBAC; pode começar com o estado atual e não exige Instructor, Student ou pagamento automático.
2. **Segundo: Slice 2 — Content antes de Enrollment.** ADM-02 é o blocker real; matrícula Admin depende de curso/conteúdo operacional. O blocker para a segunda etapa é a decisão e implementação da superfície Admin de management, não o lançamento da área Instructor.
3. **Final: Slice 3 — Closure Evidence.** Só depois das rotas finais existirem faz sentido executar E2E, Scribe e os invariantes contra o estado final.

O repair específico de área/route guard deve ser carregado pelos Slices 1/2, onde a superfície é alterada. O receipt E2E/Scribe e a comprovação atual devem ser carregados pelo Slice 3. O repair genérico de Codex hooks/probes fica em `WS2` e não é pré-requisito desta ordem.

## 10. Financial Boundary

**Dependência financeira bloqueante para `ADMIN_COMPLETE`: `NO`.**

Admin pode ser considerado completo com cash/manual e sem webhook, job ou adapter externo, desde que o E2E do closure slice comprove:

- confirmação manual autorizada na área Admin;
- transição válida e idempotente;
- criação/atualização da matrícula esperada;
- outbox/evento e audit side effects esperados;
- replay sem duplicação;
- isolamento de tenant e negativas de estado/persona.

O caminho automático (webhook/job/adapters) pertence ao Student-paid/Financial e é ADM-12 `DOWNGRADE_TO_LATER` para este boundary. Não se deve puxar a jornada de checkout automático do Student para dentro do fechamento Admin.

## 11. Final Closure Gate

`ADMIN_COMPLETE` só pode ser declarado quando todos os itens abaixo forem verdadeiros:

- **MUST = 0:** ADM-01, ADM-02 e ADM-03 entregues; ADM-04 comprovado; ADM-05 não existe como gap independente.
- **Feature tests:** identidade, conteúdo, matrícula/cash/manual, RBAC ceiling, 401/403, área, 404 cross-tenant, tenant spoofing, envelopes, idempotência/outbox e ownership negativa.
- **Architecture tests:** pelo menos `AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest`, `ErrorEnvelopeTest`, `TenantScopingTest`/smoke, permission drift/metadata, controller leanness, PII/LGPD, module boundary e money-never-float quando o diff tocar esses invariantes.
- **E2E HTTP real:** app e banco E2E atuais; jornada Admin de usuário → conteúdo → matrícula/cash/manual; asserts de status, persona/area, cross-tenant e side effects DB/outbox/enrollment; replay idempotente quando aplicável.
- **Scribe:** geração atual bem-sucedida, rotas Admin documentadas e `@unauthenticated` consistente com middleware real; eventual problema de ownership de cache deve estar resolvido ou explicitamente evidenciado como ambiente, não mascarado como produto.
- **Harness/evidence:** receipt atual com comando, ambiente, commit/estado de trabalho e resultado; nenhuma capability Admin permanece apenas `EVIDENCE_PENDING`; distinção preservada entre `STATIC_EVIDENCE_ONLY`, `TEST_VERIFIED` e `RUNTIME_VERIFIED`.
- **Cleanup/provenance quando aplicável:** artefatos temporários e resíduos de execução não podem invalidar o resultado; não é necessário copiar o gate MZRT nem abrir WS2 para declarar o Admin se o receipt específico for suficiente.

## 12. Verdict

**`ADMIN_PARTIAL` — fechamento ainda não autorizado.**

O produto tem uma base substancial e testada estaticamente, mas ADM-01, ADM-02 e ADM-03 ainda precisam convergir para a superfície Admin correta; ADM-02 permanece blocker do boundary; e ADM-04 impede a promoção para `ADMIN_COMPLETE` enquanto não houver execução/runtime atual. O plano mínimo é de **3 slices**, com cash/manual suficiente e sem dependência financeira bloqueante. O próximo passo recomendado é o **Slice 1 — Admin Identity & Onboarding Surface**.
