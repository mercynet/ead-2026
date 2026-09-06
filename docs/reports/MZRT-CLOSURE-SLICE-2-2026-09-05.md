# MZRT — Closure Slice 2 — 2026-09-05

> Revalidação final: 2026-09-06T00:12:03+01:00.

## 1. Target MUST

Target: `MZRT-MUST-01` — jornada skeleton em runtime.

Critério aplicado, sem reabrir o gap: provar por HTTP real, contra aplicação viva e banco
descartável, a jornada `developer login → create tenant → cash/entitlements → admin login →
suspend/deny → reactivate/allow`, incluindo persistência, redaction, auditoria e cleanup.

O contrato canônico de autenticação usado nesta execução foi `/api/v1/auth/*`. A compatibilidade
`/api/v1/core/auth/*` não substituiu a prova canônica.

## 2. Environment Safety

- Branch: `main`.
- HEAD: `ffe966ca6cccb6c1f4255146ae51f8841c23682a`.
- Working tree: já estava suja antes deste slice, com alterações pré-existentes; nenhum commit ou
  push foi feito.
- App local existente (`APP_ENV=local`, `APP_DEBUG=on`, `localhost:8099`) não foi usado.
- Stack isolada: projeto Compose `ead2026-e2e`, volume MySQL `ead2026-e2e_sail-mysql` e rede
  própria.
- App de prova: container `ead2026-e2e-laravel`, `APP_ENV=e2e`, `APP_DEBUG=false`.
- Banco: `ead2026_e2e`, recriado com `--fresh`; sem acesso a produção ou ao banco de desenvolvimento.
- Base URL efetiva da revalidação: `http://localhost:8087`, dentro do container isolado; router
  Laravel oficial iniciado a partir de `public/`.
- APP key: efêmera, injetada apenas em runtime; não foi gravada nem incluída neste receipt.
- Fixtures: tenants/users/tokens sintéticos do runner, prefixados `e2e-*`.

## 3. Runtime/E2E Execution

Spec executada: `tests/e2e-http/mzrt/tenant-lifecycle.php`.

A spec foi ajustada somente como evidência: metadata passou a apontar para o POST real, auth passou
a usar `/api/v1/auth/*`, e foram explicitadas asserções de senha ausente no response e auditoria do
status. O número de casos permaneceu 9.

Resultados HTTP relevantes:

| Caso | Resultado |
|---|---:|
| create tenant + primeiro admin + `cash` | 201 |
| ler entitlements redacted | 200 |
| login do admin criado | 200 |
| suspender tenant | 200 |
| login com tenant suspenso | 401 |
| token com tenant suspenso sem contexto | 422 `tenant_not_resolved` |
| reativar tenant | 200 |
| login após reativação | 200 |
| token original após reativação | 200 |

Todos os 9 casos passaram. O runner também confirmou, via banco, tenant/admin/role, ativação
`cash` ativa, configuração enabled, ausência de senha no response, status persistido e auditoria.
O entitlement retornou apenas capability/status; config, credentials e actor não apareceram.

## 4. Receipt

- Timestamp do receipt inicial: `2026-09-05T23:30:04+01:00` (timezone `Europe/Lisbon`).
- Revalidação final: `2026-09-06T00:12:03+01:00` (timezone `Europe/Lisbon`; `2026-09-05
  23:12:03 UTC`).
- HEAD: `ffe966ca6cccb6c1f4255146ae51f8841c23682a`.
- Ambiente: `APP_ENV=e2e`, debug OFF.
- Base URL da revalidação: `http://localhost:8087` dentro de `ead2026-e2e-laravel`.
- Banco/isolamento: `ead2026_e2e`, volume e rede Compose dedicados, `migrate:fresh` executado.
- Spec: `mzrt/tenant-lifecycle`.
- Casos: 9 executados, 9 PASS, 0 FAIL; `runner_exit=0`.
- Cleanup pós-run da revalidação: `e2e_tenants=0`, `e2e_users=0`, `e2e_tokens=0`,
  `e2e_activations=0`, `e2e_configs=0`, `e2e_activity_logs=0`, `cash_plugins=0`.
- Após a coleta do receipt, o container/app temporários e a rede Compose `ead2026-e2e` foram
  desmontados; o volume dedicado não foi usado por nenhum outro projeto.
- Comandos canônicos/relevantes:
  - `docker compose --env-file .env.e2e -p ead2026-e2e up -d mysql redis mailpit`
    (com portas host isoladas);
  - `docker exec ead2026-e2e-laravel php artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost:8087 --fresh`;
  - `docker exec ead2026-e2e-laravel vendor/bin/pint tests/e2e-http/mzrt/tenant-lifecycle.php --format agent`;
  - `git diff --check -- tests/e2e-http/mzrt/tenant-lifecycle.php`.

## 5. Failures and Fixes

O primeiro run foi RED por falha de infraestrutura: `.env.e2e` não fornecia `APP_KEY`, e o
provisionamento de `cash` terminou em `MissingAppKeyException`/HTTP 500. A correção foi somente
operacional: chave efêmera injetada no ambiente descartável; nenhum código de produto foi alterado.

Também foram resolvidas colisões de portas e o repasse incompleto de ambiente pelo `supervisord` do
container Sail usando portas isoladas e listener HTTP temporário direto com `.env.e2e`. Após isso,
o run final foi GREEN 9/9. Nenhum bug funcional foi encontrado.

Na revalidação, o primeiro cleanup deixou dois admins provisionados como linhas soft-deleted
`e2e-*`; a spec foi corrigida para consultar `withTrashed()` e usar `forceDelete()` somente nos
fixtures do tenant criado. A corrida seguinte foi GREEN 9/9 e confirmou zero resíduos no banco.

## 6. Validation

- `Pint` da spec E2E: PASS.
- `git diff --check` da spec E2E: PASS.
- E2E HTTP real + asserts de banco: PASS, 9/9.
- Revalidação final: `runner_exit=0`, cleanup completo com todos os contadores em zero.
- Resíduos pós-cleanup: PASS, zero nas categorias verificadas.
- `MZRT-MUST-02`: continua satisfeito pelo receipt do Slice 1, sem regressão observada.
- Nenhuma bateria ampla foi executada: não houve alteração de produto que justificasse testes
  não relacionados.

## 7. MZRT Closure Gate

- `MZRT-MUST-01`: PASS — `RUNTIME_VERIFIED`.
- `MZRT-MUST-02`: PASS — receipt Scribe atual do Slice 1.
- MUST funcionais abertos: 0.
- Gates obrigatórios de evidência: satisfeitos.
- Jornada crítica: evidência HTTP/runtime atual, com side effects e limpeza.
- Regressões conhecidas introduzidas: nenhuma.

## 8. Deferred SHOULD/LATER

Os 4 SHOULD HAVE permanecem diferidos: visão/listagem global de tenants, `SystemSetting`,
observabilidade explícita ampliada e permissions de plugin/CRUD de roles em runtime.

Os 6 LATER permanecem diferidos: marketplace/catalogo, billing SaaS/ledger de plataforma,
subscriptions/grants, quotas/licenses/usage, adapters externos e impersonation auditada.

Nenhum deles foi implementado ou usado para inflar o closure.

## 9. Harness Status

`HARNESS_NOT_BLOCKING_MZRT_CLOSURE`.

WS2 — Codex Harness Hardening não foi executado. WS3 também não foi iniciado.

## 10. Final Verdict

`MZRT_COMPLETE`

`MZRT-MUST-01` está fechado com evidência runtime/E2E atual; `MZRT-MUST-02` permanece fechado.
Admin, Instructor e Student não fazem parte deste slice e não foram iniciados.
