# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — Construção do harness. Planejamento (specs/contrato/arquitetura/decisões) travado e
  pushado. Fundação executável em andamento: `config/permissions.php` canônico + `PermissionDriftTest`,
  hooks (pint/footguns), skill `context-checkpoint`, GitHub MCP read-only nos 3 agents.

## Próximos passos (1-3)

1. Invariantes restantes em `tests/Architecture` (TenantIsolation/ErrorEnvelope/MoneyNeverFloat
   verdes; ControllerLeanness/ScribeAuth como `todo` expondo dívida).
2. Skills `spec-task-planning` + `vertical-slice` + `pest-api-tests` (escrita via subagente barato).
3. Migração modular `app/` → `app/Modules/*` (item B) → slices TDD (C). RFCs por último.

## Decisões abertas

- Nenhuma bloqueante. (Identidade tenant-scoped, modelo de acesso Learning e matriz de permissions
  já resolvidos — ver os `tasks.md` / subspecs.)

## Último commit

- `72c7cca` — `fix(harness): scaffold E2E suite + clarify spec maturity` — branch
  `harness/specs-foundation` (pushed). Suíte: 97 passed; Architecture 1; E2E vazia.
