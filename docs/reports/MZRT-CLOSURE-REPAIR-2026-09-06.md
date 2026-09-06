# MZRT — Closure Repair — 2026-09-06

Receipt de reparo exclusivo de evidência para o closure MZRT. O escopo permaneceu
`MZRT-SKELETON`; não foram abertas novas capabilities, áreas ou workstreams.

## 1. Independent Review Findings

A revisão independente classificou o estado anterior como `MZRT_CLOSURE_REJECTED` por quatro
gaps de evidência/closure:

- **F1 — HIGH:** ausência de receipt atual dos Feature tests focados e invariantes associados.
- **F2 — MEDIUM:** login HTTP canônico do developer não provado; o runner usava token de fixture.
- **F3 — MEDIUM:** receipt não vinculado de forma suficiente ao working tree sujo executado.
- **F4 — MEDIUM:** prova pós-cleanup não discriminava todas as dependências tocadas pela jornada.

A revisão não encontrou assert removido/relaxado, bug de produto confirmado ou SHOULD/LATER
promovido.

## 2. F1 Resolution

Os três Feature files exigidos foram executados integralmente via Sail, no banco de testes
`testing`:

```text
./vendor/bin/sail artisan test --compact tests/Feature/Api/Core/Mzrt/TenantCreateApiTest.php tests/Feature/Api/Core/Mzrt/TenantStatusApiTest.php tests/Feature/Api/Ecosystem/Mzrt/TenantEntitlementApiTest.php
```

Resultado: **26 testes passaram, 234 assertions, exit 0**.

Correspondências discriminantes do targeting anterior:

- conflito de domínio e rollback: `tests/Feature/Api/Core/Mzrt/TenantCreateApiTest.php:120-260`;
- idempotência de status: `tests/Feature/Api/Core/Mzrt/TenantStatusApiTest.php:36-46`;
- preservação do tenant vizinho e bloqueio do tenant suspenso:
  `tests/Feature/Api/Core/Mzrt/TenantStatusApiTest.php:92-120`;
- isolamento, cursor pagination e redaction de entitlement:
  `tests/Feature/Api/Ecosystem/Mzrt/TenantEntitlementApiTest.php:26-67`.

Não houve nome/path equivalente oculto: os três nomes do targeting continuam nos paths canônicos
acima.

## 3. F2 Resolution

A spec `tests/e2e-http/mzrt/tenant-lifecycle.php:29-44` recebeu um caso explícito de:

```text
POST /api/v1/auth/login
```

O caso usa o email/password do developer fixture, espera `200`, valida o ID retornado e captura
`data.token` sem imprimi-lo. O token retornado é então enviado pelo header `Authorization` nas
etapas relevantes de create tenant, entitlement, suspend e reactivate
(`tests/e2e-http/mzrt/tenant-lifecycle.php:45-64,95-112,128-142,170-184`).

O token de fixture criado pelo runner permanece apenas como suporte ao canário/fixtures padrão;
ele não substitui a prova do login canônico do developer.

## 4. F3 Resolution

O receipt foi selado contra o snapshot efetivamente executado antes da criação deste relatório:

```text
timestamp de sealing: 2026-09-06T00:39:53+01:00
HEAD: ffe966ca6cccb6c1f4255146ae51f8841c23682a
E2E_SPEC_DIFF_SHA256: a6a2dba55edb6ec32fe0f9188b9d4becf46b92312fb50ce168ed3aea010bb754
SNAPSHOT_FILE_COUNT: 114
SNAPSHOT_MANIFEST_SHA256: 9d992a6e36681e21d56dec5140f91bac10836a56b846f70b8eabe3757af0fee5
STATUS_PORCELAIN_LINES: 51
STATUS_PORCELAIN_SHA256: 80cfe955adaeb6d71ec5b96d3782c963e52e7bc4a8194f905d746879cb47ec5f
```

O manifesto determinístico foi calculado ordenando os paths rastreados de:
`app/Modules/Core`, `app/Modules/Ecosystem`, `app/Shared`, `bootstrap/app.php`,
`config/permissions.php`, `config/scribe.php`, `app/Console/Commands/E2eRunCommand.php`, os
três Feature files MZRT e `tests/e2e-http/mzrt/tenant-lifecycle.php`; para cada path, foi
hasheado o conteúdo atual e, em seguida, o manifesto foi hasheado novamente.

O `git status --porcelain=v1` foi capturado no mesmo sealing. O working tree já estava sujo; as
alterações relevantes já existentes eram `app/Modules/Core/Routes/api.php`, `config/scribe.php`,
`tests/e2e-http/mzrt/tenant-lifecycle.php` e outros arquivos fora do escopo. O receipt não atribui
essas alterações prévias a este reparo.

## 5. F4 Resolution

Após o runner terminar com cleanup e antes da desmontagem da stack, foram executadas consultas
limitadas às entidades/dependências tocadas pela jornada, no banco dedicado. Resultado sanitizado:

```json
{
  "database": "ead2026_e2e",
  "marker_tenants": 0,
  "marker_users_including_soft_deleted": 0,
  "marker_users_soft_deleted": 0,
  "personal_access_tokens_in_dedicated_db": 0,
  "rbac_user_pivots_in_dedicated_db": 0,
  "tenant_activations_in_dedicated_db": 0,
  "tenant_configs_in_dedicated_db": 0,
  "config_revisions_in_dedicated_db": 0,
  "mzrt_activity_log_rows": 0,
  "cash_plugin_rows": 0
}
```

Os marcadores de tenant cobriram `e2e-primary-*`, `e2e-other-*` e `e2e-mzrt-*`; users foram
consultados incluindo soft-deleted. As demais consultas cobriram tokens, pivôs RBAC,
activations, configs, revisions, activity relacionada a Tenant/User e o plugin `cash`. O cleanup
do runner não lançou erro e o `runner_exit` final foi 0.

## 6. Feature / Invariant Evidence

Além dos Feature tests de F1, foi executado:

```text
./vendor/bin/sail artisan test --compact --testsuite=Architecture
```

Resultado: **22 testes passaram, 709 assertions, exit 0**.

Isso inclui os invariantes relevantes do targeting: `AreaRouteGuardTest`,
`RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest`,
`PermissionDriftTest`, `PermissionMetadataShapeTest`, `TenantScopingTest`,
`TenantIsolationSmokeTest`, `PiiAuditTest`, `ErrorEnvelopeShapeTest`,
`ModuleBoundaryTest` e demais testes da suite Architecture.

O guardrail final do diff também passou:

```text
printf '%s' '{"stop_hook_active":false}' | scripts/ai/verify-changes.sh
```

Resultado: **invariantes do diff verdes (6 arquivos de tests/Architecture), exit 0**.
`python3 scripts/ai/validate-harness.py` passou com o warning esperado de `.opencode/opencode.json`
opcional; essa validação não iniciou WS2.

## 7. Runtime / E2E Evidence

### Environment safety

- stack dedicada Compose: `ead2026-e2e`;
- `APP_ENV=e2e`, `APP_DEBUG=false`;
- banco: `ead2026_e2e`, nome aceito pelo gate de descartabilidade;
- base URL executada pelo runner: `http://localhost:8083`, listener interno do app E2E;
- portas isoladas auxiliares: 8109, 33109, 63909, 10309 e 8309;
- MySQL estava saudável antes da migration;
- `migrate:fresh` foi aplicado somente em `ead2026_e2e`;
- o app local `ead2026-laravel.test-1`/`APP_ENV=local` não foi usado;
- containers e rede E2E foram desmontados via Sail ao final.

### Attempts and final run

As duas primeiras tentativas foram RED exclusivamente operacionais: a primeira revelou que o
Supervisor não repassava a `APP_KEY` efêmera ao servidor HTTP; a segunda usou um listener com
document root incorreto e foi abortada pelo canário. Nenhuma mutação de produto foi aplicada e
nenhum bug funcional foi confirmado. O listener foreground correto respondeu `422
validation_error` numa requisição inválida não-mutante e permitiu a execução final.

Spec final: `tests/e2e-http/mzrt/tenant-lifecycle.php`.

| Caso HTTP | Resultado |
|---|---:|
| developer login canônico | 200 |
| create tenant + primeiro admin + `cash` | 201 |
| entitlement redacted | 200 |
| login do admin criado | 200 |
| suspend tenant | 200 |
| login com tenant suspenso | 401 |
| token com tenant suspenso | 422 `tenant_not_resolved` |
| reactivate tenant | 200 |
| login após reativação | 200 |
| token original após reativação | 200 |

Resultado do comando:

```text
10 passou, 0 falhou
runner_exit=0
```

Side effects confirmados pelo runner: tenant ativo criado sem header de tenant, primeiro admin
vinculado e com role, activation `cash` ativa, config `cash` enabled, senha ausente da resposta,
entitlement limitado a capability/status, configuração/credentials/actor ausentes, status
persistido, auditoria de suspensão, login/token negados durante suspensão e restaurados após
reativação.

## 8. Snapshot Provenance

O snapshot acima corresponde ao working tree usado pelo runner e pelos testes: `HEAD` sozinho não
foi tratado como prova. O hash do diff da spec vincula diretamente o reparo de F2; o manifesto de
114 arquivos vincula o código/testes relevantes executados; o status porcelain preserva o fato de
que havia alterações prévias fora desta trajetória.

Não foram registrados secrets, APP_KEY, tokens, passwords ou PII. A única alteração aplicada
durante este reparo no código de execução foi na spec E2E: caso de login developer e reutilização
do token retornado. Nenhum código de produto foi alterado.

## 9. Cleanup Evidence

O runner executou cleanup normalmente e a consulta pós-cleanup devolveu zero para todos os
marcadores relevantes listados em F4. A consulta incluiu usuários soft-deleted, tokens, RBAC
pivots, entitlements por activation/config/revision, activity relacionada e plugin `cash`.

O volume dedicado não foi compartilhado com o app local; containers e rede do projeto
`ead2026-e2e` foram removidos via:

```text
APP_ENV=e2e COMPOSE_PROJECT_NAME=ead2026-e2e ./vendor/bin/sail down --remove-orphans
```

## 10. Remaining Risks

- O working tree continua sujo por alterações anteriores e não houve commit/push nesta tarefa.
- A execução E2E precisou de listener PHP temporário por uma limitação operacional do repasse de
  ambiente do Supervisor; isso não alterou produto nem reduz a prova HTTP real.
- `MZRT-MUST-02` não foi reimplementado nem regenerado nesta trajetória; permanece respaldado pelo
  receipt Scribe anterior em `docs/reports/MZRT-CLOSURE-SLICE-1-2026-09-05.md`. A suite atual
  também passou `ScribeAuthAnnotationMatchesMiddlewareTest`.
- Nenhum risco funcional novo foi revelado pelas execuções finais.

## 11. Final Closure Gate

| Gate | Estado |
|---|---|
| F1 Feature/invariant evidence | PASS |
| F2 canonical developer HTTP login | PASS |
| F3 snapshot provenance | PASS |
| F4 scoped cleanup proof | PASS |
| MZRT-MUST-01 runtime skeleton | PASS / `RUNTIME_VERIFIED` |
| MZRT-MUST-02 Scribe | PASS, preservado sem reabrir |
| Feature/invariants verdes | PASS |
| E2E/runtime atual | PASS, 10/10 |
| receipt vinculado ao snapshot | PASS |
| cleanup auditável | PASS |
| MUST restantes | 0 |

## 12. Verdict

`MZRT_COMPLETE`

`EVIDENCE_PENDING`: **nenhuma pendência MZRT**.

SHOULD e LATER continuam diferidos. Admin, Instructor, Student, WS2 e WS3 não foram iniciados.
