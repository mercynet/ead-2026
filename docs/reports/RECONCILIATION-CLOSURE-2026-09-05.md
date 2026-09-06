# Fechamento da reconciliação — 2026-09-05

## Resultado

Reconciliação documental aplicada conforme as decisões humanas D1–D5. Esta tarefa não implementa
nenhum workstream técnico e não promove evidência histórica para runtime atual.

## Decisões aplicadas

### D1 — Status de tasks

Aplicada. Os slices anteriormente marcados como entregues em `Pending` foram preservados em
`Done`, com histórico útil. `Pending` agora contém somente deltas abertos e não há `[x]`
operacionalmente ambíguo nessa seção. Nenhum item foi promovido a concluído por inferência de
runtime; a semântica de `[x]` permanece declarativa e separada de `RUNTIME_VERIFIED`.
Onde a entrega não pôde ser determinada, como o possível delta adicional de Ratings em Learning,
o item ficou explicitamente `[ ] UNKNOWN` em `Pending`.

### D2 — Arquitetura

Aplicada. A arquitetura atual/canônica para trabalho novo é o monólito modular de cinco módulos:
`Core`, `Learning`, `Assessment`, `Financial` e `Ecosystem`, com `app/Shared`. `app/Plugins` e
`app/Support/Ports` foram explicitamente classificados como históricos/superseded e não são paths
válidos para implementação nova. A dívida Eloquent cross-module existente continua dívida e não foi
refatorada nem maquiada.

### D3 — Contrato público de autenticação

Registrada, sem implementação. `/api/v1/auth/*` é `TARGET_CANONICAL`; `/api/v1/core/auth/*` é
`CURRENT_IMPLEMENTED` + `LEGACY_COMPATIBILITY` durante a v1. A migração pertence ao WS1 — API
Contract Convergence. Não há data artificial de remoção; qualquer remoção futura exige inventário de
consumidores e decisão explícita. Rotas, Scribe, testes e PHP não foram alterados nesta tarefa.

### D4 — Evidência runtime

Permanece aberta como `EVIDENCE_PENDING`, não como decisão humana pendente. Não houve execução atual
contra aplicação e banco adequados; portanto nenhuma capability foi promovida a `RUNTIME_VERIFIED`.
Resultados anteriores permanecem históricos. A classificação canônica é:

- `RUNTIME_VERIFIED` — execução atual contra app/banco adequados;
- `TEST_VERIFIED` — teste ou resultado de teste disponível, sem substituir runtime atual;
- `STATIC_EVIDENCE_ONLY` — código/rota/model/migration/teste presente sem resultado atual confiável;
- `DOCUMENTATION_ONLY` — intenção documentada sem implementação arbitrada;
- `UNVERIFIED` — evidência insuficiente ou conflitante.

### D5 — Harness e ferramentas

Aplicada. Codex é a ferramenta first-class. O próximo workstream é **Codex Harness Hardening**:
guards, provas, lifecycle e enforcement necessários ao projeto, com distinção explícita entre
advisory e enforced. Não há objetivo de parity mecânica com Claude ou OpenCode.

OpenCode é opcional/best-effort; `opencode.json` na raiz é sua configuração canônica,
`.opencode/opencode.json` não é requerido e sua ausência não é finding relevante do harness
principal.

## Arquivos alterados

- `AGENTS.md`
- `docs/STATE.md`
- `docs/ROADMAP.md`
- `docs/specs/README.md`
- `docs/specs/00-architecture/overview.md`
- `docs/specs/00-architecture/backend-patterns.md`
- `docs/specs/00-architecture/areas-surfaces.md`
- `docs/specs/10-core-identity/spec.md`
- `docs/specs/10-core-identity/subspecs/auth.md`
- `docs/specs/10-core-identity/tasks.md`
- `docs/specs/20-catalog-learning/tasks.md`
- `docs/reports/CANONICAL-STATE-RECONCILIATION-2026-09-05.md` (header/status histórico)
- `docs/reports/RECONCILIATION-CLOSURE-2026-09-05.md`

## Conflitos eliminados

- `[x]` dentro de `Pending` em Core e Learning;
- duplicação operacional de slices entregues versus deltas abertos em Learning;
- prescrição dos paths antigos como layout alternativo;
- ausência de distinção documental entre autenticação target, implementada e legacy;
- STATE tratando decisões já tomadas como abertas;
- promessa implícita de parity do harness como baseline do Codex;
- ausência do status separado `EVIDENCE_PENDING` para runtime não reproduzido.

## Inconsistências deliberadamente mantidas

- `/api/v1/auth/*` continua divergente da superfície executável `/api/v1/core/auth/*` até o WS1;
- Scribe e rotas executáveis não foram alterados nesta tarefa;
- reports/auditorias históricas podem conter recomendações, contagens ou matrizes anteriores; foram
  mantidos como histórico, com o relatório canônico anterior marcado como superseded;
- a dívida Eloquent de fronteiras e outras superfícies legacy não foram refatoradas;
- a ausência de `.opencode/opencode.json` permanece, pois esse arquivo não é requerido.

## Estado final

- `NEEDS_RECONCILIATION`: **nenhuma** decisão humana/documental restante deste fechamento.
- `EVIDENCE_PENDING`: **validação runtime atual** das capabilities e jornadas, aguardando execução
  contra ambiente adequado. Isso não bloqueia a decisão documental e não é contado como
  `NEEDS_RECONCILIATION`.

## Próximos três workstreams

1. **WS1 — API Contract Convergence:** D3, M-01, M-02, M-03 e documentação/Scribe relacionada.
2. **WS2 — Codex Harness Hardening:** M-05, M-06, enforcement necessário ao Codex,
   behavioral probes/tests e distinção advisory/enforced; M-04 será avaliado separadamente.
3. **WS3 — Legacy Surface & Module Boundary Debt:** M-07, M-09 e limpeza documental restante da
   arquitetura, sem refactor Eloquent por inferência.

Nenhum workstream acima foi implementado nesta tarefa.
