# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-10 — Construção do harness. Planejamento travado e pushado. Fundação executável:
  `config/permissions.php` + `PermissionDriftTest`, hooks (pint/footguns), skill `context-checkpoint`,
  GitHub MCP read-only. **Invariantes `tests/Architecture` adicionados** (commit `59e4e7d`):
  MoneyNeverFloat/TenantIsolation/ErrorEnvelope(422,404) verdes; ControllerLeanness + ScribeAuth
  como `skip('debt')` com asserção presente.

## Próximos passos (1-3)

1. Invariantes pendentes do spec (`RouteSecuritySurfaceTest`, `PiiAuditTest` escrevíveis já;
   `ModuleBoundaryTest` depende da migração modular).
2. Migração modular `app/` → `app/Modules/*` (item B) → slices TDD (C). RFCs por último.
3. Decidir/corrigir drift do error-envelope (ver Decisões abertas).

## Decisões abertas

- **Drift do error-envelope (descoberto ao escrever `ErrorEnvelopeShapeTest`):** só as 4 exceptions
  custom (bootstrap/app.php) emitem `{data,errors}`. Sanctum 401, Gate `AuthorizationException` 403 e
  `findOrFail` 404 ainda vazam JSON default do Laravel. Os 2 `->todo()` no teste marcam isso. Decidir:
  registrar render handlers para `AuthenticationException`/`AuthorizationException`/`ModelNotFoundException`
  e padronizar `findOrFail` → `ResourceNotFoundException`.
- Sem outras bloqueantes. (Identidade tenant-scoped, acesso Learning e matriz de permissions resolvidos.)

## Último commit

- `70c9582` — `feat(harness): add spec-task-planning, vertical-slice and pest-api-tests skills` —
  branch `harness/specs-foundation`. Architecture: 5 pass + 2 todos + 2 skipped (debt).
