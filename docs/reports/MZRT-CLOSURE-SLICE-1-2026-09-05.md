# MZRT — Closure Slice 1 — 2026-09-05

## 1. Target MUST

**`MZRT-MUST-02` — contrato Scribe das rotas MZRT.**

O primeiro slice concreto do relatório de targeting é `MZRT-CLOSE-01 — Scribe/contract receipt`.
O gap era de evidência/reprodutibilidade: a geração enumerava as rotas, mas falhava ao limpar
`.scribe/endpoints.cache` por ownership preexistente (`nobody:nogroup`). Sem uma geração atual
bem-sucedida, os artefatos publicados não podiam ser tratados como contrato vigente.

Escopo mantido: geração do Scribe, confirmação das seis rotas MZRT executáveis, auth canônica e
ausência de auth legacy na documentação primária. Não foram abertas áreas Admin, Instructor ou
Student; não foram alterados lifecycle, entitlements, RBAC, tenant isolation ou o receipt E2E.

## 2. Baseline / RED Evidence

Antes da correção, foi executado:

```text
./vendor/bin/sail composer docs
```

Resultado: **exit 1**. O Scribe enumerou as rotas, mas falhou com
`League\\Flysystem\\UnableToDeleteDirectory` ao apagar
`.scribe/endpoints.cache/17.yaml`. A inspeção anterior ao slice mostrou o cache como
`nobody:nogroup` e os artefatos continham `/api/v1/core/auth/*` legacy.

Essa foi a reprodução real do gap documentado; nenhum RED artificial foi criado.

## 3. Implementation

Foi aplicada somente a correção operacional prevista no slice:

- ownership de `.scribe` e `public/docs` ajustado para o usuário do processo Sail (`1000:1000`);
- `./vendor/bin/sail composer docs` executado novamente;
- artefatos regenerados sem edição manual ou alteração de código de domínio.

`config/scribe.php` já continha, no working tree anterior a esta task, as exclusões explícitas das
cinco rotas auth legacy. Esse arquivo não foi alterado por este slice; sua configuração existente
foi validada pela geração.

## 4. Tests

Teste existente e discriminante reutilizado:

```text
tests/Architecture/ScribeAuthAnnotationMatchesMiddlewareTest.php
```

Também foram executadas as provas de superfície diretamente relacionadas:

```text
tests/Architecture/ScribeAuthAnnotationMatchesMiddlewareTest.php
tests/Architecture/AreaRouteGuardTest.php
tests/Architecture/RouteSecuritySurfaceTest.php
```

Resultado: **6 testes passaram, 12 assertions**. Nenhum teste novo foi adicionado: o teste de
anotação já prova a propriedade relevante e a lacuna deste slice era a geração/receipt operacional.

## 5. Validation

Após o ajuste:

- `./vendor/bin/sail composer docs` — **exit 0**;
- `./vendor/bin/sail artisan route:list --path=api/v1/mzrt --json` — **6 rotas**;
- cada rota MZRT real contém `auth:sanctum`, `area.guard:mzrt` e `api.context`;
- OpenAPI gerado contém as cinco rotas `/api/v1/auth/*` canônicas;
- auth legacy `/api/v1/core/auth/*` não aparece em `.scribe` nem em `public/docs`;
- metadata stale `GET /api/v1/mzrt/tenants` não aparece nos artefatos;
- os seis endpoints MZRT atuais estão presentes: três de categorias e três de tenants;
- `git diff --check` — **passou**;
- `python3 scripts/ai/validate-harness.py` — **passou**, com o warning esperado de
  `.opencode/opencode.json` opcional.

A geração emitiu warnings já existentes sobre ausência de `bodyParameters()` em FormRequests de
Financial, Learning e Assessment. Eles não impediram o exit 0 e estão fora do escopo deste slice.

## 6. Cross-cutting Changes

Nenhuma mudança de fundação, rota, controller, Action, model, permission, middleware, teste de
tenant ou harness foi necessária. A única mudança operacional foi tornar graváveis os diretórios
de saída do Scribe. O harness foi apenas validado; não foi corrigido nem expandido.

## 7. Remaining Risks

- O working tree já estava sujo antes desta task, inclusive em `config/scribe.php`, código, testes
  e documentação; essas mudanças não são atribuídas a este slice.
- Os artefatos Scribe são saídas regeneráveis e não constituem, sozinhos, prova de execução HTTP
  contra aplicação e banco.
- Os warnings de `bodyParameters()` permanecem em endpoints fora do escopo MZRT.

## 8. Evidence Pending

`MZRT-MUST-02` tem receipt atual de geração com exit 0 nesta task.

Permanece **`EVIDENCE_PENDING`** para o fechamento global MZRT: o receipt HTTP E2E atual do
`MZRT-MUST-01` ainda não foi executado. Essa pendência pertence exclusivamente ao Slice 2 e não
foi usada para reabrir gap funcional.

Não houve execução runtime/E2E neste slice, conforme a definição de done de `MZRT-CLOSE-01`.

## 9. Slice Verdict

**`SLICE_1_COMPLETE_WITH_EVIDENCE_PENDING`**

O contrato Scribe foi gerado com sucesso, a configuração existente exclui auth legacy, as rotas
MZRT reais e auth canônica foram confirmadas nos artefatos, e os checks discriminantes passaram.
O sufixo `WITH_EVIDENCE_PENDING` registra somente o receipt E2E global ainda aberto; não promove
MZRT para `MZRT_COMPLETE`.

## 10. Remaining MZRT Closure Work

- `MZRT-CLOSE-02` / `MZRT-MUST-01`: alinhar e executar o receipt E2E HTTP contra aplicação viva e
  banco descartável `e2e`, cobrindo create → `cash`/entitlement → login canônico → suspend/deny →
  reactivate/allow, side effects e cleanup.
- Aplicar o Final Closure Gate somente depois dos dois receipts.

O Slice 2 continua pendente. SHOULD, LATER, WS2 e WS3 permanecem fora desta execução.
