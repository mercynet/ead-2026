# Capability Context Hardening — 2026-09-06

Mini-workstream restrito a tornar regras de negócio conhecidas alcançáveis pelo Codex antes de
alterar capabilities. Não implementa comportamento de produto e não é o WS2 completo.

## 1. Baseline

A auditoria [`RULE-REACHABILITY-AGENT-READINESS-2026-09-06.md`](RULE-REACHABILITY-AGENT-READINESS-2026-09-06.md)
classificou o estado como `AGENT_READY_WITH_GAPS`: invariantes globais eram alcançáveis, mas as
regras específicas de Learning, Assessment, Ecosystem/plugins e Financial dependiam de busca manual.
O risco de generic LMS drift foi classificado como `HIGH`, especialmente para quiz, plugins,
ownership, gateways e matrícula/pagamento.

Antes desta mudança, `routing.json` selecionava skills mecânicas, mas não havia manifesto que
selecionasse specs, invariantes, testes e decisões abertas por capability.

## 2. Manifest Design

Foi criado `.agents/capability-context.json`, versão 1, com cinco bundles declarativos. Cada bundle
possui:

- padrões `prompt` e `paths` para seleção automática;
- domínio, personas/áreas afetadas e classificação `core`/`plugin`;
- specs e subspecs obrigatórios;
- skills/rules relevantes;
- invariantes críticas;
- testes/gates relacionados;
- `decision_gates` com status obrigatório `HUMAN_DECISION_REQUIRED`.

O resolver `scripts/ai/capability-context.sh` é intencionalmente advisory: sugere o contexto, lista
as fontes e exibe os gates humanos, mas não finge possuir autoridade para decidir produto.

## 3. Capability Bundles

### `categories`

Alcança System/Custom (incluindo os aliases de trabalho SYSTEM/DEFAULT e TENANT_CUSTOM), ownership
Mzrt/Admin, tenant scope, colisões/normalização, parent de mesmo escopo, hierarquia, materialized
path, soft delete e pivô dedicado.

### `learning-course-content`

Alcança ownership operacional Admin versus ownership pedagógico Instructor, curso sem Instructor,
Course → Module → Lesson, requisito de Module para Lesson, distinção CourseMaterial/LessonMedia,
múltiplas mídias, lifecycle, payloads sem tenant/owner/parent arbitrários e a ponte de matrícula.

### `assessment`

Alcança quiz simples como core, quiz avançado como plugin/future, snapshot e scoring server-side,
questão usada não editável sem regra de histórico, morph lesson/course/standalone e os gaps de
tenant/ownership. Tasks ambíguas recebem gates explícitos.

### `ecosystem-plugins`

Alcança plugins first-party, activation versus entitlement versus config, ownership Mzrt/Admin,
tenant gateway versus platform gateway, disponibilidade efetiva além de `config.enabled` e a
regra `CORE SIMPLE + PLUGIN ADVANCED`.

### `financial`

Alcança Student → tenant ledger versus platform/Mzrt → tenant ledger, gateways por ownership,
cash/manual Admin versus pagamento automático, cents/idempotência/charge/outbox e a exigência de
carregar Financial + Learning em matrícula/pagamento.

## 4. Routing Changes

- `routing.json` agora declara a ponte `context_manifest` para o manifesto e o resolver.
- `skill-router.sh` emite bundles de capability em `prompt`, `tool` e `list`, preservando o
  roteamento de skills existente.
- `.codex/hooks.json` adiciona `UserPromptSubmit` ao router; o `PreToolUse` continua fornecendo
  contexto quando o harness só alcançar essa etapa.
- `AGENTS.md` recebeu somente uma regra global: carregar o bundle selecionado antes de alterar uma
  capability e tratar `HUMAN_DECISION_REQUIRED` como gate advisory.
- `validate-harness.py` valida o manifest, referências de specs/testes/skills, regexes, cinco
  bundles obrigatórios e o resolver executável.

Não há carregamento integral de domínio no `AGENTS.md`; o router entrega caminhos e regras mínimas
para o agente abrir as fontes canônicas.

## 5. Human Decision Gates

Permanecem explícitos no manifesto:

- nomenclatura System/Custom versus SYSTEM/DEFAULT/TENANT_CUSTOM;
- ownership Admin/Instructor em Assessment;
- fronteira exata entre quiz core simple e quiz advanced/plugin;
- MediaProvider;
- matrícula externa;
- lifecycle completo de plugins;
- publication/readiness completo de Learning.

O resolver emite `HUMAN_DECISION_REQUIRED` somente quando o padrão da task toca diretamente o gate.
O mecanismo não resolve nenhuma dessas decisões.

## 6. Probes / Tests

Probe criada: `scripts/ai/test-capability-context.sh`.

Resultado:

```text
PASS: capability context probes passed (10 prompt scenarios + 1 tool scenario; advanced quiz is discriminated)
```

Os cenários cobrem: categoria Admin, endpoint Admin Course, novo tipo de mídia, quiz Admin, quiz
avançado, gateway tenant, novo plugin, Instructor Course e matrícula/pagamento, além de um payload
de tool com rota Assessment. O caso negativo prova que `quiz avançado` seleciona
`assessment` **e** `ecosystem-plugins`, incluindo o gate de fronteira core/plugin.

Validações executadas:

- `scripts/ai/test-capability-context.sh` — verde;
- `python3 scripts/ai/validate-harness.py` — verde, com o warning preexistente de
  `.opencode/opencode.json` ausente;
- `git diff --check` — verde;
- `jq empty` em manifest, routing e hooks — verde;
- `scripts/ai/verify-changes.sh` — verde na primeira execução deste turno (10 invariantes de
  Architecture); a repetição após o checkpoint não iniciou porque Docker estava indisponível.

A indisponibilidade do Docker na repetição impede uma nova confirmação dos testes de Architecture,
mas não afeta os probes/validator específicos deste workstream e não foi causada por código de
produto alterado aqui.

## 7. Remaining Reachability Gaps

- O contexto continua advisory; o router não bloqueia edição nem substitui revisão humana.
- Matching é declarativo por regex, então uma task vaga pode selecionar mais de um bundle e ainda
  exige julgamento do agente.
- Specs novas ou decisões novas precisam ser adicionadas ao manifesto; não existe descoberta
  semântica automática de regras fora dos cinco bundles.
- Assessment, MediaProvider, matrícula externa, lifecycle de plugins e publication/readiness seguem
  decisões abertas e não foram implementados.
- Não houve runtime verification de produto, porque nenhum código de produto foi alterado.

O generic LMS drift risk cai de `HIGH` para `MEDIUM` para as capabilities conhecidas e roteadas,
mas permanece `HIGH/CRITICAL` se um agente ignorar um gate humano ou trabalhar em uma capability
fora dos bundles declarados.

## 8. Scope Guard

Confirmado fora do escopo:

- nenhuma implementação de Assessment, plugin, Learning ou Financial;
- nenhum endpoint, migration, model, Action, controller ou regra de produto alterado;
- nenhum Admin Slice seguinte, Instructor/Student ou parity Claude/OpenCode;
- nenhum hardening genérico de lifecycle/Stop/PreCompact do WS2.

Alterações pré-existentes no working tree foram preservadas e não fazem parte deste mini-workstream.

## 9. Verdict

**`CONTEXT_HARDENING_COMPLETE_WITH_GAPS`**

O mecanismo, os cinco bundles, o routing e as probes discriminantes estão entregues e validados.
As lacunas restantes são deliberadas: advisory-only, decisões humanas ainda abertas e ausência de
runtime de produto.
