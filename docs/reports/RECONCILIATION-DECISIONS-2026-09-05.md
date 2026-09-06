# Decisões de Reconciliação — 2026-09-05

## Escopo e método

Este relatório resolve, por decisão humana explícita, os sete itens classificados como
`NEEDS_RECONCILIATION` no relatório [CANONICAL-STATE-RECONCILIATION-2026-09-05.md](CANONICAL-STATE-RECONCILIATION-2026-09-05.md).
Os sete itens foram agrupados quando representam a mesma decisão de governança.

Esta é uma análise `READ-ONLY`. Não foram alterados código, testes, configurações, hooks, scripts,
CI, tasks, roadmap ou `STATE`. A evidência de runtime não foi inferida: nesta sessão não houve
execução do app, Artisan, Pest, Scribe ou E2E HTTP. Resultados documentados de rodadas anteriores
foram tratados como evidência histórica, não como validação do HEAD atual.

A hierarquia usada foi: `AGENTS.md` → specs/ADRs → código, bootstrap, providers, rotas e testes.
O grafo existente foi consultado para localizar as relações entre documentação, módulos, harness e
rotas; a confirmação decisória abaixo foi feita nas fontes locais.

## Inventário e agrupamento

| Itens originais | Causa decisória | Decisão resultante | Prioridade |
|---|---|---|---|
| Core `tasks.md` com `[x]` em `Pending`; Learning com itens repetidos em `Done`/`Pending`; Learning com “delta restante” após `[x]` | Semântica de status e histórico das tasks | D1 — status de tasks | `MUST_RESOLVE_BEFORE_API_CONTRACT` |
| Layout antigo em `backend-patterns.md` contra o runtime modular | Qual layout orienta trabalho novo | D2 — arquitetura executável | `CAN_WAIT` |
| URL de login do Scribe contra a rota implementada | Qual URL é o contrato público | D3 — URL de autenticação | `MUST_RESOLVE_BEFORE_API_CONTRACT` |
| Claims de E2E/testes no estado documental sem reprodução no HEAD | O que conta como evidência atual | D4 — política de evidência runtime | `MUST_RESOLVE_BEFORE_HARNESS_WORK` |
| `.opencode/opencode.json` ausente, com `opencode.json` na raiz | Qual configuração OpenCode é canônica e qual suporte é prometido | D5 — suporte/configuração OpenCode | `MUST_RESOLVE_BEFORE_HARNESS_WORK` |

Assim, a contagem é: **7 itens originais → 5 decisões reais**.

## D1 — Semântica de status e duplicação nas tasks

### Prioridade

`MUST_RESOLVE_BEFORE_API_CONTRACT` — a implementação do contrato pode continuar tecnicamente, mas a
seleção de fatias e a leitura do backlog ficam ambíguas sem uma convenção única.

### 1. Questão

Um item `[x]` significa “a fatia descrita foi entregue”, mesmo quando aparece em `Pending`, ou a
presença em `Pending` significa que ainda há trabalho? Quando uma capacidade foi entregue em um
slice inicial e depois ganhou um delta, o `[x]` representa o slice original ou a capacidade completa?

### 2. Fontes envolvidas

- `docs/specs/10-core-identity/tasks.md:10-70,76-97`: tarefas concluídas em `Done` e, ao mesmo
  tempo, vários `[x]` dentro de `Pending`, incluindo rate limiting, isolamento E2E e administração
  de usuários.
- `docs/specs/20-catalog-learning/tasks.md:10-65`: módulos, lessons, media, materiais e ratings
  aparecem como concluídos no bloco `Done`.
- `docs/specs/20-catalog-learning/tasks.md:71-117,131-142`: o mesmo domínio mantém blocos
  posteriores de `Pending` e chama ratings/materials de “delta restante”, embora existam itens `[x]`
  anteriores para essas capacidades.
- `docs/specs/README.md:10-15,20-33,68-75`: `tasks.md` é o estado mutável; `spec.md` é contrato;
  endpoint é unidade de execução e jornada é unidade de sucesso.
- `AGENTS.md:15-24,161-174`: status de task não substitui código, teste ou validação externa.

### 3. Estado observado

Os arquivos de task realmente contêm as inconsistências textuais acima. Há referências a models,
rotas, testes e E2E correspondentes, e o código Learning contém as entidades de módulos, lessons,
media, materiais e ratings; isso é evidência estática. Não há, nesta sessão, execução que prove que
cada `[x]` continua verde no runtime atual.

Não houve movimentação, fechamento ou reclassificação de task durante esta reconciliação.

### 4. Alternativas plausíveis

- **A —** interpretar todo `[x]` como fatia entregue, independentemente do bloco, e aceitar as
  duplicações como histórico sem semântica operacional.
- **B —** definir `Done` como fatia entregue; manter em `Pending` somente o delta ainda aberto;
  preservar histórico em texto explícito, sem deixar `[x]` operacional dentro de `Pending`.
- **C —** congelar os arquivos atuais e não usar seu status para planejar até uma reconciliação
  específica por domínio.

### 5. Consequências

| Alternativa | Produto | Harness | Tasks/roadmap | Dívida futura | Custo aproximado |
|---|---|---|---|---|---|
| A | Não muda comportamento, mas pode sugerir que uma capacidade completa está pronta | Seleção de testes e roteamento de skills podem tratar delta como encerrado | Mantém leitura contraditória e dificulta ordenar jornadas | Alta: novas duplicações ficam plausíveis | Baixo agora; alto custo recorrente de interpretação |
| B | Não muda comportamento nem contrato de produto | Permite escolher testes pelo delta real e separar slice entregue de prova atual | Alinha `tasks.md` à governança existente; não exige mudar status do roadmap por inferência | Baixa, se cada novo delta tiver escopo explícito | Baixo: organização documental pontual |
| C | Não muda comportamento | Reduz risco de confiar em status, mas também remove um sinal útil do planejamento | Adia planejamento e pode bloquear trabalho sem necessidade | Média/alta: backlog congelado envelhece | Médio/alto, porque exige reconciliações repetidas |

### 6. Recomendação

**B — definir `Done` como slice entregue e `Pending` como delta aberto.** O texto “delta restante”
já aponta para essa interpretação e a governança de `docs/specs/README.md` separa claramente contrato,
status de execução e jornada. A decisão não promove `[x]` a prova de runtime e não fecha gaps por
inferência.

Classificação: **LOW_RISK_DECISION**.

### 7. Pode ser resolvido agora?

**`SAFE_TO_RECONCILE`**. É uma decisão de organização/status; não requer mudança de produto nem
refactor. A aplicação posterior deve preservar o histórico e não alterar o roadmap sem evidência.

## D2 — Layout arquitetural canônico para trabalho novo

### Prioridade

`CAN_WAIT` — o contrato operacional já orienta agentes para o layout atual; a correção integral da
spec pode acompanhar o workstream de migração legacy/fronteiras.

### 1. Questão

O layout antigo descrito em `backend-patterns.md` (`app/Plugins`, `app/Support/Ports` e três
módulos) ainda é uma alternativa aceita, ou o layout modular atual, com costuras dentro dos módulos,
é o único layout canônico para trabalho novo?

### 2. Fontes envolvidas

- `docs/specs/00-architecture/backend-patterns.md:7-12,25-38,81-92,130-138`: o próprio documento
  avisa que conserva o desenho antigo, mas ainda apresenta esse desenho e declara a migração como
  concluída em outra seção.
- `AGENTS.md:75-102`: contrato operacional afirma cinco módulos, shared kernel e costuras dentro
  de `Financial`/`Ecosystem`; declara ausentes `app/Plugins` e `app/Support/Ports`.
- `bootstrap/providers.php:3-9`: registra `Core`, `Financial`, `Learning`, `Assessment` e
  `Ecosystem`.
- `app/Modules/Financial/Gateways/` e `app/Modules/Ecosystem/Contracts/`: costuras observadas no
  runtime repository.
- Ausências observadas: `app/Plugins/` e `app/Support/Ports/` não existem.

### 3. Estado observado

O código e os providers estão organizados em cinco módulos. `PaymentGateway` está dentro de
`Financial/Gateways`; contratos de plugin/gateway tenant estão em `Ecosystem`. `MediaProvider` ainda
é alvo não implementado. O layout antigo sobrevive como prosa na spec, não como diretório usado pelo
código observado.

Isso prova a organização presente, não que a arquitetura esteja livre da dívida Eloquent de
fronteiras já registrada na allowlist.

### 4. Alternativas plausíveis

- **A —** manter o layout antigo como canônico e migrar o código atual para `app/Plugins` e
  `app/Support/Ports`.
- **B —** adotar o layout de cinco módulos e costuras intramódulo como canônico; tratar o desenho
  antigo como histórico/superseded e atualizar a spec quando o workstream arquitetural chegar.
- **C —** aceitar os dois layouts para novas implementações até uma migração global.

### 5. Consequências

| Alternativa | Produto | Harness | Tasks/roadmap | Dívida futura | Custo aproximado |
|---|---|---|---|---|---|
| A | Nenhum ganho de produto imediato; alto risco de regressão estrutural | Exige revisar paths, allowlists, router e invariantes | Reabre uma migração tratada como concluída e desloca o roadmap | Alta: refactor amplo e novo legado | Alto/muito alto |
| B | Preserva o comportamento e as costuras já usadas | Mantém providers, fronteiras e paths observados como referência | Permite focar migração real sem reabrir layout | Baixa para o layout; dívida Eloquent permanece explicitamente delimitada | Baixo: apenas alinhamento documental futuro |
| C | Nenhum ganho observável | Agentes e checks precisam suportar dois caminhos | Torna cada task dependente de uma escolha ad hoc | Alta: dualidade e novos arquivos fora da fronteira | Médio contínuo |

### 6. Recomendação

**B.** A evidência executável favorece claramente o layout modular de cinco módulos, e `AGENTS.md` já
o estabelece como contrato operacional. Não há justificativa para refatorar o código apenas para
reaproximá-lo da descrição histórica.

Classificação: **OBVIOUS**.

### 7. Pode ser resolvido agora?

**`SAFE_TO_RECONCILE`** como decisão de orientação. A dívida documental pode ser corrigida depois,
no workstream legacy, sem bloquear o contrato API atual.

## D3 — URL pública canônica de autenticação

### Prioridade

`MUST_RESOLVE_BEFORE_API_CONTRACT` — consumidores podem copiar a URL errada; a decisão também define
compatibilidade e eventual depreciação.

### 1. Questão

A autenticação canônica é `/api/v1/core/auth/*`, que o código e os testes usam hoje, ou
`/api/v1/auth/*`, que o texto introdutório do Scribe e o alvo de migração do roadmap indicam?

### 2. Fontes envolvidas

- `app/Modules/Core/Routes/api.php:10-27`: o grupo atual expõe `v1/core/auth/login`, logout, me,
  forgot e reset.
- `config/scribe.php:19-29,31-40,109-134`: introdução e `extra_info` orientam o consumidor para
  `/api/v1/auth/login`.
- `docs/specs/10-core-identity/spec.md:111-113` e `subspecs/auth.md:40-44`: contrato de domínio
  documenta `/api/v1/core/auth/*`.
- `docs/specs/10-core-identity/tasks.md:34-39`, `tests/Feature/Api/Core/Auth/AuthApiTest.php` e
  specs E2E: usam `/api/v1/core/auth/*`.
- `docs/ROADMAP.md:67-77`: classifica `/core/auth/*` como legacy técnico e aponta `/auth/*` como
  target neutral.
- `AGENTS.md:104-126,154-159`: exige contrato versionado, documentação Scribe consistente e
  distingue prefixos legacy de novas superfícies.
- `public/docs` e `.scribe` são artefatos não canônicos: alguns textos introdutórios usam a URL nova,
  enquanto os endpoints gerados exibem a URL atual.

### 3. Estado observado

Hoje, a rota implementada e os testes observados são `/api/v1/core/auth/*`. A configuração textual
do Scribe orienta `/api/v1/auth/login`. O roadmap já prevê uma migração para uma superfície neutral,
mas não há evidência nesta sessão de que `/api/v1/auth/*` exista como rota executável ou de que
clientes externos tenham sido migrados.

Portanto:

- “o sistema faz hoje”: expõe `/api/v1/core/auth/*`;
- “deveria continuar fazendo”: ainda requer decisão de contrato/compatibilidade.

### 4. Alternativas plausíveis

- **A —** manter `/api/v1/core/auth/*` como contrato canônico, corrigindo Scribe, roadmap e demais
  referências para refletir isso.
- **B —** adotar `/api/v1/auth/*` como contrato futuro canônico, criar/migrar essa superfície e
  manter `/api/v1/core/auth/*` temporariamente como compatibilidade/depreciação.
- **C —** adiar a escolha e manter a divergência enquanto o inventário de consumidores não for feito.

### 5. Consequências

| Alternativa | Produto | Harness | Tasks/roadmap | Dívida futura | Custo aproximado |
|---|---|---|---|---|---|
| A | Estável para consumidores atuais; preserva URL legacy como contrato | Menor custo imediato em testes e E2E | Contraria o target neutral já registrado e pode exigir rever migração | Média: domínio-first permanece na superfície pública | Baixo |
| B | Melhor separação técnica cross-area e alinhamento ao target; exige compatibilidade para não quebrar clientes | Atualiza testes, Scribe, E2E, allowlists e provas de segurança | Alinha o roadmap; cria tasks de migração/depreciação explícitas | Baixa após transição, se prazo de compatibilidade for definido | Médio |
| C | Evita quebra imediata, mas mantém consumidores expostos a duas mensagens | Harness continua tendo que testar/explicar duas URLs | Contrato API fica impedido de fechar | Alta: divergência pode virar contrato acidental | Baixo agora, alto depois |

### 6. Recomendação

**B**, com período de compatibilidade/depreciação explicitamente decidido. O roadmap já classifica
`/core/auth/*` como legacy e aponta `/auth/*` como superfície neutral; isso é uma direção de produto
API mais coerente. A recomendação não autoriza implementar a migração nesta fase: falta decidir
prazo, comportamento da URL antiga e se existe consumidor externo que exija preservação longa.

Classificação: **PRODUCT_DECISION**.

### 7. Pode ser resolvido agora?

**`REQUIRES_PRODUCT_DECISION`**. O repositório oferece uma direção forte, mas a escolha pública da
URL e a política de compatibilidade não devem ser inferidas somente do código atual.

## D4 — Evidência de runtime versus evidência documental/testual

### Prioridade

`MUST_RESOLVE_BEFORE_HARNESS_WORK` — o harness precisa distinguir presença de código, teste existente,
execução em processo e HTTP real; isso não bloqueia a escrita do contrato API baseada em evidência
estática, mas bloqueia uma alegação honesta de prova runtime.

### 1. Questão

Claims como “E2E 9/9”, `[x]` em tasks e status `usable` do roadmap podem ser tratados como prova do
HEAD atual, ou devem permanecer como evidência histórica/testual até uma nova rodada contra app e
banco reais?

### 2. Fontes envolvidas

- `docs/reports/CANONICAL-STATE-RECONCILIATION-2026-09-05.md:20-33,66-117`: define
  `RUNTIME_VERIFIED`, `TEST_VERIFIED`, `STATIC_EVIDENCE_ONLY` e `DOCUMENTATION_ONLY`, e declara que
  nenhum item desta reconciliação foi validado em runtime.
- `docs/reports/SYSTEM-STATE-AUDIT-2026-09-05.md:134-147,231-250`: registra testes/estrutura
  presentes, mas Docker/Artisan/Pest/Scribe/E2E não executados na auditoria-base.
- `docs/specs/10-core-identity/tasks.md:66-70`: documenta E2E `9/9` no DB `ead2026_e2e`.
- `docs/ROADMAP.md:9-17,23-43`: usa status de jornada e DoD com E2E, sem transformar o snapshot em
  certificação global.
- `AGENTS.md:65-73,165-174`: define E2E HTTP como validação externa e exige não confundir isso com
  teste in-process.
- `app/Console/Commands/E2eRunCommand.php` e `tests/e2e-http/`: runner e specs existem, mas sua
  presença não é execução.

### 3. Estado observado

Há código, testes e specs E2E declarativas. Não houve execução do app ou banco real nesta sessão. A
auditoria anterior documenta indisponibilidade do Docker para os comandos de runtime. Assim, o
estado atual pode ser descrito como evidência estática/testual e histórica; não como
`RUNTIME_VERIFIED`.

### 4. Alternativas plausíveis

- **A —** manter a classificação conservadora: sem rodada atual, não promover claims para
  `RUNTIME_VERIFIED`.
- **B —** executar uma rodada isolada com app, banco E2E e código fixado; promover somente os fluxos
  cujo HTTP e efeitos de banco forem observados e registrados.

Não é alternativa plausível promover automaticamente os resultados históricos: isso contradiz a
própria definição de evidência adotada e não demonstra o HEAD atual.

### 5. Consequências

| Alternativa | Produto | Harness | Tasks/roadmap | Dívida futura | Custo aproximado |
|---|---|---|---|---|---|
| A | Nenhum comportamento é alterado; comunicação fica cautelosa | Evita falso verde, mas não aumenta prova | Mantém claims históricos separados de status atual | Baixa em honestidade; média na falta de cobertura runtime | Nenhum agora |
| B | Aumenta confiança nos fluxos entregues sem alterar contrato | Fornece prova externa e receipts úteis para gates | Permite revisar status com evidência, sem marcar task por inferência | Baixa, se a rodada for repetível e isolada | Médio: ambiente E2E, execução e registro |

### 6. Recomendação

**A agora e B como próximo passo operacional.** A decisão de evidência é inequívoca: ausência de
execução não pode ser convertida em runtime verde. O item permanece aberto porque a rodada B não
foi possível dentro deste escopo READ-ONLY e não deve ser simulada.

Classificação: **OBVIOUS**.

### 7. Pode ser resolvido agora?

**`KEEP_UNRESOLVED`**. A política pode ser adotada imediatamente, mas a classificação positiva dos
fluxos não pode ser encerrada sem ambiente/runtime. Isto é uma lacuna de evidência, não um blocker de
produto nem autorização para refactor do harness.

## D5 — Configuração e nível de suporte ao OpenCode

### Prioridade

`MUST_RESOLVE_BEFORE_HARNESS_WORK` — parity e probes do harness dependem de saber se OpenCode é uma
ferramenta suportada, opcional ou fora do escopo.

### 1. Questão

O arquivo canônico de configuração OpenCode é `opencode.json` na raiz, tornando
`.opencode/opencode.json` desnecessário, ou o harness espera uma configuração dentro de `.opencode`?
Além da localização, o projeto promete suporte first-class ao OpenCode ou apenas disponibiliza uma
integração opcional?

### 2. Fontes envolvidas

- `opencode.json:1-35`: configuração JSON válida na raiz, com agentes e MCPs `laravel-boost` e
  `github` em modo read-only.
- `.opencode/skills`: symlink para `../.agents/skills`.
- `.opencode/package.json` e `.opencode/package-lock.json`: dependência local do plugin OpenCode.
- `boost.json:2-8,15-18`: lista OpenCode entre os agentes e skills compartilhadas.
- `scripts/ai/validate-harness.py:25-27,268-292,381-404`: considera `opencode.json` obrigatório e
  `.opencode/opencode.json` opcional; a ausência opcional gera warning.
- `.codex/hooks.json` e `.claude/settings.json`: mostram que cada ferramenta possui integrações de
  hooks diferentes; não há evidência de paridade completa entre elas.
- `docs/reports/SYSTEM-STATE-AUDIT-2026-09-05.md:124-132,153-170,184-191`: confirma integração
  estrutural e gaps de enforcement por ferramenta.

### 3. Estado observado

`opencode.json` existe na raiz e passa na validação estrutural. `.opencode/opencode.json` não existe,
mas o próprio validador o classifica como opcional e emite apenas um warning. Skills compartilhadas,
dependência/plugin local e inclusão no `boost.json` mostram intenção de suporte ao OpenCode.

Isso prova configuração estrutural; não prova que uma sessão OpenCode foi executada, que os MCPs
subiram ou que o enforcement seja equivalente ao Claude/Codex.

### 4. Alternativas plausíveis

- **A —** tratar `opencode.json` na raiz como configuração canônica e declarar OpenCode suportado no
  nível estrutural atual; `.opencode/opencode.json` fica opcional/obsoleto.
- **B —** exigir configuração em `.opencode/opencode.json` e manter a raiz apenas como configuração
  auxiliar, ajustando o harness para essa convenção.
- **C —** classificar OpenCode como integração experimental/opcional e não prometer paridade de
  enforcement com Claude/Codex.

### 5. Consequências

| Alternativa | Produto | Harness | Tasks/roadmap | Dívida futura | Custo aproximado |
|---|---|---|---|---|---|
| A | Não muda produto; clarifica o suporte disponível | Remove ambiguidade de path e permite matriz explícita de gaps | Mantém a presença já registrada; exige não chamar parity de concluída | Baixa para path; gaps de enforcement continuam visíveis | Baixo |
| B | Não muda produto, mas cria convenção diferente da configuração observada | Exige mover/duplicar config e revisar validação, docs e bootstrap da ferramenta | Pode divergir da convenção de outros agentes | Média: duas configurações e drift | Médio |
| C | Comunica limite real e evita prometer proteção inexistente | Menor obrigação de parity; probes precisam marcar capacidade ausente | Pode exigir retirar OpenCode de matrizes de suporte futuro | Média: integração pode ficar negligenciada | Baixo agora, potencial médio depois |

### 6. Recomendação

**A, com a ressalva de que suporte estrutural não equivale a parity de enforcement.** A raiz é a
configuração obrigatória real; `.opencode/opencode.json` é opcional segundo o próprio validador. A
matriz de hooks deve continuar separando capacidades por ferramenta, sem transformar a existência do
arquivo em prova de execução.

Classificação: **ARCHITECTURAL_DECISION**.

### 7. Pode ser resolvido agora?

**`REQUIRES_ARCHITECTURAL_DECISION`**. A localização favorece claramente a raiz, mas o nível de
suporte prometido e a política de parity do harness são decisões de arquitetura operacional que
devem ser confirmadas antes de ampliar probes/gates.

## Síntese por prioridade

| Prioridade | Decisões | Leitura operacional |
|---|---|---|
| `MUST_RESOLVE_BEFORE_API_CONTRACT` | D1, D3 | D3 é o bloqueio estrito do contrato público; D1 é o pré-requisito de planejamento para não selecionar fatias com status ambíguo. |
| `MUST_RESOLVE_BEFORE_HARNESS_WORK` | D4, D5 | D4 aguarda runtime real; D5 aguarda política de suporte/parity do OpenCode. |
| `CAN_WAIT` | D2 | O contrato operacional já aponta para o layout executável; a limpeza da spec pode acompanhar a migração legacy. |
| `INDEPENDENT` | nenhuma | Não restou decisão independente dos quatro eixos acima. |

## Contagem e decisão humana

- **Itens originais `NEEDS_RECONCILIATION`:** 7.
- **Decisões reais após agrupamento:** 5.
- **`SAFE_TO_RECONCILE`:** 2 (D1 e D2).
- **`KEEP_UNRESOLVED`:** 1 (D4, por falta de runtime atual).
- **Decisões que exigem decisão humana real:** 2 (D3, produto/API; D5, arquitetura de suporte/parity).
- **Decisões que exigem nova evidência operacional, mas não uma escolha de produto:** 1 (D4).

O número “decisão humana real” conta apenas decisões cuja recomendação não deve ser promovida sem
escolha explícita de produto/arquitetura. D1 e D2 são reconciliações de baixo risco/óbvias; D4 é uma
limitação de evidência, não uma decisão de negócio.

## Bloqueio do workstream de contrato API

**D3 bloqueia estritamente** o fechamento do contrato API, porque define a URL pública e sua política
de compatibilidade/depreciação.

**D1 deve ser resolvida antes de iniciar o workstream** para que o backlog e os deltas não orientem
o contrato por status contraditório, mas não é um bloqueio técnico de endpoint.

D2, D4 e D5 não bloqueiam a decisão da URL API; D4 limita apenas a alegação de validação runtime, e
D5 pertence ao workstream do harness.

## Veredito da revisão

**A reconciliação anterior está parcialmente confirmada:** os sete itens e suas fontes foram
confirmados, e cinco agrupamentos decisórios são suficientes. A recomendação D3 ainda depende de
decisão de produto/API; D5 depende de decisão arquitetural do harness; D4 não pode ser encerrada
como evidência positiva sem runtime.
