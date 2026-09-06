# MZRT — Closure Targeting Review — 2026-09-05

## 1. Revised MZRT_COMPLETE Definition

### Escopo da decisão

Esta revisão interpreta `MZRT_COMPLETE` como **fechamento de `MZRT-SKELETON`**, não como a
implementação de todas as responsabilidades futuras da área MZRT. Essa distinção é necessária:
`areas-surfaces.md:64-76` descreve a visão de longo prazo da área, mas explicita que a migração
está em curso; `docs/ROADMAP.md:12` define o resultado atual como status/create de tenant, preset
`cash` e leitura de entitlements, com E2E create → login → suspend/deny → reactivate/allow.

Se o nome `MZRT_COMPLETE` for lido como “toda a área MZRT”, a definição anterior fica frouxa,
porque marketplace, billing da plataforma, quotas e catálogo amplo continuam ausentes. Para o
closure desta jornada, porém, exigir esses itens seria excessivo e misturaria
`MZRT-PLATFORM` com `MZRT-SKELETON`.

### Definição revisada

`MZRT_COMPLETE` significa que:

1. o developer autentica na superfície canônica `/api/v1/auth/*` e somente um developer alcança
   as rotas MZRT;
2. o provisionamento cria tenant ativo, primeiro admin, role e preset `cash` atomicamente, sem
   header de tenant, sem senha na resposta, com conflito de domínio e rollback corretos;
3. o developer consulta os entitlements do tenant indicado, com cursor pagination e resposta
   reduzida a capability/status, sem configuração ou credenciais;
4. suspensão e reativação são idempotentes, auditadas e alteram o login/token do tenant alvo sem
   afetar tenants vizinhos;
5. os quatro itens funcionais têm Feature/Architecture evidence e um receipt E2E HTTP atual em
   banco dedicado descartável, com side effects e limpeza verificados;
6. o Scribe gera com sucesso o contrato atual, incluindo as rotas canônicas MZRT e auth, e as
   anotações de autenticação coincidem com o middleware real.

Os itens 1–4 são os MUST funcionais. Os itens 5–6 são gates de release/fechamento e explicam por
que a implementação pode estar `TEST_VERIFIED` sem ainda poder ser declarada `MZRT_COMPLETE`.
Assim, há **6 critérios MUST de closure: 4 funcionais e 2 de evidência**; há **0 gaps funcionais
confirmados** e **2 gates de closure abertos**.

### Crítica da definição anterior

- Não está exigente demais ao requerer E2E atual e Scribe: o contrato do repositório exige API
  versionada/documentada (`AGENTS.md:7-13`), o DoD de jornada exige E2E e Scribe
  (`docs/ROADMAP.md:39-42`), e a política de evidência proíbe promover claims históricos a
  `RUNTIME_VERIFIED` (`AGENTS.md:27-41`).
- Estava ambígua ao apresentar evidência operacional como se fosse uma capability de produto. A
  revisão separa os quatro MUST funcionais dos dois gates de fechamento.
- Não deve converter SHOULD/LATER em MUST: listagem de tenants, `SystemSetting`, auditoria ampla
  de leitura/catálogo e autorização dinâmica de plugins não são dependências da jornada definida.
- A evidência E2E existente não é aceita sem ressalvas: seu metadata anuncia
  `GET /api/v1/mzrt/tenants`, que não é uma rota executável, e os casos de login usam a superfície
  legacy `/api/v1/core/auth/*` (`tests/e2e-http/mzrt/tenant-lifecycle.php:13-15,92-172`). Isso
  não cria um novo MUST; é uma pendência interna do receipt do MUST-01, pois o contrato canônico é
  `/api/v1/auth/*` (`AGENTS.md:35-38`, `docs/specs/10-core-identity/subspecs/auth.md:38-52`).
- Não há requisito de chave de idempotência para o POST de provisionamento: a spec define conflito
  de domínio e ausência de idempotência para esse POST (`docs/specs/10-core-identity/spec.md:77-82`).

## 2. Review of MZRT-MUST-01

### Descrição exata

`MZRT-MUST-01` — **Jornada skeleton em runtime**: nenhum HTTP E2E atual foi executado na auditoria;
existe uma spec declarativa, mas os claims históricos de sucesso não equivalem a uma prova atual.

### Capability/jornada afetada

É o walking skeleton inteiro: developer entra, provisiona tenant + primeiro admin + `cash`, lê
entitlement, suspende, observa login/token negados, reativa e observa o acesso restaurado.
O resultado esperado está no roadmap (`docs/ROADMAP.md:12`), nas tasks Core
(`docs/specs/10-core-identity/tasks.md:57-76`) e nas regras de tenant
(`docs/specs/10-core-identity/spec.md:73-82`).

### Evidência concreta

- A implementação está presente: rotas MZRT de create/status em
  `app/Modules/Core/Routes/api.php:76-81` e entitlement em
  `app/Modules/Ecosystem/Routes/api.php:18-22`.
- Provisionamento usa transação e o participante Ecosystem em
  `app/Modules/Core/Actions/Tenants/ProvisionTenantAction.php:34-47,62-104`; o participante
  cria/ativa/configura `cash` em
  `app/Modules/Ecosystem/Services/EcosystemDefaultGatewayProvisioner.php:14-55`.
- Entitlement usa `where('tenant_id')`, eager load e `cursorPaginate` em
  `app/Modules/Ecosystem/Actions/Mzrt/ListTenantEntitlementsAction.php:12-18`; o Resource
  expõe somente capability/status em `app/Modules/Ecosystem/Http/Resources/Mzrt/TenantEntitlementResource.php:12-18`.
- Status é idempotente e só persiste quando dirty em
  `app/Modules/Core/Actions/Tenants/UpdateTenantStatusAction.php:9-19`; o Tenant audita
  `is_active` em `app/Modules/Core/Models/Tenant.php:41-47`.
- Há cobertura Feature discriminante para create/rollback
  (`tests/Feature/Api/Core/Mzrt/TenantCreateApiTest.php:40-260`), status/login/token/vizinhança
  (`tests/Feature/Api/Core/Mzrt/TenantStatusApiTest.php:14-143`) e entitlement/isolation/redaction
  (`tests/Feature/Api/Ecosystem/Mzrt/TenantEntitlementApiTest.php:26-92`).
- A spec E2E contém nove casos e cleanup (`tests/e2e-http/mzrt/tenant-lifecycle.php:27-214`),
  mas não há receipt de execução atual nesta revisão; `docs/STATE.md` também mantém a evidência
  runtime pendente.
- A própria spec E2E tem duas inconsistências que precisam ser resolvidas antes do receipt ser
  considerado válido: metadata stale (`:14`) e uso de auth legacy (`:95,120,132,155,167`).

### Por que é MUST HAVE

O roadmap define explicitamente o E2E HTTP e a ausência de resíduos como saída objetiva de
`MZRT-SKELETON`. Feature tests provam comportamento in-process; não provam que servidor, banco,
configuração de ambiente e runner externo estão alinhados. A política do repositório também
separa `TEST_VERIFIED` de `RUNTIME_VERIFIED`.

### O que quebra se ficar aberto

Nada no código fica demonstradamente quebrado apenas pela falta do receipt. O que fica quebrado é
a capacidade de declarar a jornada operacionalmente fechada: não há prova atual do vínculo
servidor ↔ banco, dos side effects HTTP, da suspensão observada externamente ou da limpeza.
Claims de `9/9` nas tasks (`tasks.md:72-76`) continuam históricos até serem reproduzidos.

### Bloqueia uso ponta a ponta?

Bloqueia a **declaração de closure**, não há evidência suficiente para dizer que bloqueia o uso em
produção. A implementação e os Feature tests indicam que o fluxo deve funcionar, mas o contrato
do projeto não permite transformar essa indicação em prova runtime.

### Escopo mínimo do fechamento

1. Usar um ambiente permitido e um banco descartável com nome contendo `e2e`, app e runner
   apontando para a mesma base.
2. Ajustar, se necessário, somente a spec de evidência: metadata coerente com a rota real e pelo
   menos a jornada crítica usando `/api/v1/auth/*`; a compatibilidade legacy pode ser verificada
   separadamente, mas não substitui o canonical path.
3. Executar create → entitlement → login canônico → suspend → login/token deny → reactivate →
   login/token allow.
4. Confirmar side effects de tenant/admin/role/`cash`, redaction, status e cleanup sem resíduos.
   O isolamento do tenant vizinho já está discriminado em Feature; se o receipt runtime não o
   cobrir, o receipt precisa anexar o teste Feature correspondente.

### Dependências

Docker/Sail, `.env.e2e`, migrations/seeders, servidor vivo, runner, banco `ead2026_e2e` ou
equivalente descartável, e contrato de auth canônico carregado pela aplicação.

### Testes e runtime necessários

- Feature: `TenantCreateApiTest`, `TenantStatusApiTest`, `TenantEntitlementApiTest`.
- Architecture: área, superfície de auth, Scribe/auth annotation, permission drift, tenant
  scoping, PII e envelope de erro, conforme os arquivos tocados.
- Runtime obrigatório: `artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost` contra a
  aplicação viva e banco dedicado; `--fresh` é aceitável somente após o gate de banco descartável.

### Risco de regressão e esforço

Risco baixo na execução pura; médio no fechamento da spec E2E, porque ela pode revelar divergência
entre `/auth` e `/core/auth`, ambiente, teardown ou side effect. Esforço relativo pequeno/médio:
configuração e receipt, com possível ajuste mecânico da spec, não implementação de domínio.

### Verdict

**`CONFIRMED_MUST`** — é um blocker real de closure/evidence, embora não seja um bug funcional
confirmado. O gap está corretamente mantido como MUST, com escopo refinado para incluir a rota
canônica e as inconsistências da spec E2E.

## 3. Review of MZRT-MUST-02

### Descrição exata

`MZRT-MUST-02` — **Contrato Scribe das rotas MZRT**: a geração documental enumerou rotas, mas não
terminou porque `.scribe/endpoints.cache` é owned por `nobody:nogroup`; portanto não existe
receipt atual de geração bem-sucedida.

### Capability/jornada afetada

É o contrato consumível da API MZRT e da superfície de auth que alimenta a jornada. Não é uma nova
capability de negócio, mas é parte do produto API-first: método/path, auth, parâmetros, respostas e
envelope precisam estar publicados de forma reproduzível.

### Evidência concreta

- O contrato canônico exige documentação via Scribe (`AGENTS.md:7-13,277-282`) e o DoD de jornada
  exige Scribe (`docs/ROADMAP.md:39-42`).
- `config/scribe.php:35-63` inclui `api/v1/*` e exclui explicitamente apenas os cinco endpoints
  de auth legacy; o texto introdutório aponta para `/api/v1/auth/login` (`config/scribe.php:19-29`).
- Os controllers MZRT têm grupos, parâmetros e respostas documentados:
  `MzrtTenantCreateController.php:13-34`, `MzrtTenantStatusController.php:13-49` e
  `TenantEntitlementController.php:13-35`.
- A arquitetura verifica coerência `@unauthenticated` ↔ `auth:sanctum`, mas não prova que a
  geração de artefatos terminou (`tests/Architecture/ScribeAuthAnnotationMatchesMiddlewareTest.php:13-51`).
- O relatório WS1 registra que Scribe enumerou rotas canônicas, mas falhou ao limpar
  `.scribe/endpoints.cache/17.yaml` por owner `nobody:nogroup`
  (`docs/reports/WS1-API-CONTRACT-CONVERGENCE-2026-09-05.md:134-157`). O estado atual do cache e o
  `public/docs/openapi.yaml` contêm artefatos anteriores, inclusive auth legacy; não são prova de
  geração atual conforme a config.

### Por que é MUST HAVE

Consumidores dependem do contrato documentado; a ausência de um artefato gerado atual deixa aberta
a divergência entre código, middleware, exemplos e OpenAPI/Postman. Isso viola a fonte de verdade
API-first mesmo que os endpoints funcionem.

### O que quebra se ficar aberto

Não impede o HTTP flow nem altera persistência. Impede afirmar que consumidores têm documentação
reproduzível e que o contrato publicado representa a superfície atual. Artefato stale pode expor
auth legacy como primária ou omitir a superfície canônica.

### Bloqueia uso ponta a ponta?

Não bloqueia a execução funcional ponta a ponta. Bloqueia o **closure do produto API-first**, pois
o DoD do roadmap inclui Scribe e o gate de evidência ainda não foi satisfeito.

### Escopo mínimo do fechamento

1. Tornar o cache/ambiente de geração gravável pelo processo correto, sem editar código para
   mascarar o problema de ownership.
2. Rodar `./vendor/bin/sail composer docs` com a aplicação em estado permitido.
3. Confirmar que os artefatos atuais contêm as seis rotas MZRT executáveis e a auth canônica,
   excluem a legacy conforme a configuração, e não carregam metadata de endpoint inexistente.
4. Rodar a Architecture Scribe/auth test e registrar o exit code/output sanitizado.

### Dependências, testes, runtime, risco e esforço

- Dependências: permissões de `.scribe`, Sail/app boot e diretório de saída da documentação.
- Testes/checks: `ScribeAuthAnnotationMatchesMiddlewareTest`, `route:list --path=api/v1/mzrt` e
  inspeção dos artefatos gerados; não exige banco funcional nem E2E.
- Runtime: não é necessário HTTP runtime da jornada; é necessário um boot/generation Scribe
  bem-sucedido.
- Risco: baixo se for apenas correção de ambiente/receipt; médio se a geração revelar stale
  metadata ou divergência de contrato que exigir decisão.
- Esforço relativo: pequeno.

### Verdict

**`CONFIRMED_MUST`** — é um blocker de closure documental confirmado. A causa observada é
operacional (ownership do cache), não um defeito de autorização MZRT; isso não reduz a exigência
do gate de contrato.

## 4. Missing MUST HAVE Search

Foi feita uma busca adversarial em auth/Sanctum, provisionamento e lifecycle, RBAC, isolamento,
guards, operações globais, plugin/entitlement, segurança, envelopes, side effects, idempotência,
persistência, Architecture, Feature e E2E.

| Área examinada | Evidência encontrada | Conclusão |
|---|---|---|
| Auth MZRT | `/api/v1/auth/*` canônico e `/api/v1/core/auth/*` legacy compartilham controller, middleware e throttles; login developer não exige tenant (`app/Modules/Core/Routes/api.php:15-41`, `app/Modules/Core/Actions/Auth/LoginAction.php:40-96`) | Coberto pelo MUST funcional de entrada; não é novo gap |
| Tenant provisioning | Transação tenant/admin/role/participant, conflito de domínio e rollback Feature (`ProvisionTenantAction.php:62-104`, `TenantCreateApiTest.php:120-260`) | Coberto; runtime é MUST-01 |
| Tenant lifecycle | Status `active|suspended`, idempotência, audit log e restauração de login/token (`UpdateTenantStatusAction.php:9-19`, `TenantStatusApiTest.php:92-143`) | Coberto; runtime é MUST-01 |
| RBAC/permissions | Permissions canônicas `core.tenants.create`, `core.tenants.update-status`, `ecosystem.entitlements.list` elegíveis só para developer (`config/permissions.php:67-75,390-393`); Feature cobre permission negativa | Não há MUST ausente |
| Cross-tenant isolation | MZRT não usa contexto de tenant; entitlement filtra tenant alvo; status testa tenant vizinho preservado | Não há IDOR confirmado; não criar novo MUST |
| Area guard/surface | Rotas MZRT usam exatamente `auth:sanctum`, `area.guard:mzrt`, `api.context` (`Routes/api.php` acima); Architecture arbitra guard e superfície | Coberto; falta apenas receipt atual quando aplicável |
| Platform-wide operations | Listagem global, `SystemSetting`, reporting e suporte não têm task executável para o skeleton | SHOULD/unclear; não MUST |
| Plugin/config/entitlements | `cash` é provisionado no contrato Ecosystem; entitlement redige config/credentials e não implica quotas (`50-ecosystem-plugins/spec.md:42-53`) | Coberto pelo skeleton; autorização dinâmica de plugin é SHOULD |
| Security/error handling | Envelopes centrais para 401/403/404/409/422, área antes de binding e Resource redacted | Não há blocker adicional confirmado |
| Side effects/persistência | Feature testa tenant/admin/role/config/activation, audit e status; E2E spec declara DB asserts | Coberto funcionalmente; verificação atual é MUST-01 |
| Idempotência | Status é idempotente; POST não possui idempotency key por contrato (`spec.md:80-82`) | Nenhum gap |
| Observabilidade | Mudança de status é auditada; não há mutação MZRT de catálogo/entitlement no skeleton | Auditoria ampla permanece SHOULD |
| Testes discriminantes/E2E | Feature é ampla; E2E existe, mas não foi reproduzido e usa auth legacy | Subgap do MUST-01, não novo MUST |

**NO_ADDITIONAL_MUST_HAVE_FOUND**

Os pontos adversariais mais próximos de um gap — `GET /api/v1/mzrt/tenants`, `SystemSetting`,
roles/plugins dinâmicos, quotas, billing, marketplace e impersonation — não são pré-requisitos da
jornada cujo critério de parada está definido no roadmap e nas tasks atuais.

## 5. Revised Completion Estimate

### Denominador

O denominador é somente `MZRT-SKELETON`: 4 capacidades funcionais e 2 gates de fechamento.
`MZRT-PLATFORM` não entra no cálculo.

| Critério | Peso relativo | Estado atual |
|---|---:|---|
| Auth/área/RBAC de developer | 15% | Implementado e `TEST_VERIFIED`; runtime atual não confirmado |
| Provisionamento + rollback + `cash` | 30% | Implementado e `TEST_VERIFIED`; runtime atual não confirmado |
| Entitlements + paginação + redaction | 15% | Implementado e `TEST_VERIFIED`; runtime atual não confirmado |
| Suspend/reactivate + efeito de acesso | 25% | Implementado e `TEST_VERIFIED`; runtime atual não confirmado |
| Receipt E2E HTTP/DB/cleanup atual | 10% | Aberto: não executado nesta revisão |
| Receipt Scribe atual | 5% | Aberto: geração interrompida por ownership do cache |
| **Total** | **100%** | **85% de implementação funcional evidenciada; closure não selado** |

### Estimativa

**Estimated completion: 85–90%, confidence medium.**

O 90% anterior é defensável apenas como limite superior de “implementação observável”: os quatro
blocos funcionais têm código, rotas e Feature evidence. Não é defensável como percentual exato de
closure, porque os 15% de gates obrigatórios não têm receipt atual. O ponto de vista conservador é
85%; o intervalo 85–90% representa a incerteza sobre creditar parcialmente os artefatos e claims
históricos sem promovê-los a `RUNTIME_VERIFIED`.

## 6. Revised Verdict

**`MZRT_COMPLETE_WITH_EVIDENCE_PENDING`**

Não há MUST funcional aberto confirmado. Há dois MUST de closure/evidence confirmados: runtime E2E
atual e geração Scribe atual. Portanto `MZRT_COMPLETE` seria prematuro; `MZRT_PARTIAL` seria
excessivamente severo para uma jornada funcional implementada e coberta por Feature tests. A
classificação correta é “near-complete/implemented, pending two closure receipts”, expressa pelo
verdict acima.

## 7. Minimal Closure Slices

Somente os dois MUST confirmados entram na execução principal. SHOULD/LATER ficam fora.

| Slice | Objetivo | Arquivos/áreas prováveis | Testes discriminantes e checks | Runtime/E2E | Dependências | Definition of done | Modelo |
|---|---|---|---|---|---|---|---|
| `MZRT-CLOSE-01` — Scribe/contract receipt | Obter um artefato Scribe atual e reproduzível para auth canônica e seis rotas MZRT | `config/scribe.php`, docblocks dos três controllers MZRT, `.scribe`/saídas geradas e inspeção da rota real | `ScribeAuthAnnotationMatchesMiddlewareTest`; `route:list --path=api/v1/mzrt`; `composer docs`; verificar inclusão de `/api/v1/auth/*`, rotas MZRT atuais, exclusão legacy configurada e ausência do metadata stale | Não requer E2E HTTP nem DB de domínio | Ownership gravável do cache; Sail/app boot | `composer docs` termina com exit 0; artefatos refletem rotas atuais; auth annotation test verde; receipt com comando, data e artefato identificado | `CHEAP/MECHANICAL` |
| `MZRT-CLOSE-02` — Runtime skeleton receipt | Provar externamente a jornada crítica e seus efeitos no banco descartável | `tests/e2e-http/mzrt/tenant-lifecycle.php` somente para alinhar metadata/auth canônica, `.env.e2e`, runner e DB descartável | `TenantCreateApiTest`, `TenantStatusApiTest`, `TenantEntitlementApiTest`; Architecture relevante; confirmar conflito/rollback/redaction/idempotência por Feature e create→cash→entitlement→login→suspend/deny→reactivate/allow por HTTP | Obrigatório: `artisan e2e:run mzrt/tenant-lifecycle --base=http://localhost` contra app vivo; conferir side effects e zero resíduos | Docker/Sail, app e runner na mesma base, migrations, seed/RBAC, DB nomeado `e2e`; Slice 01 recomendado antes | exit 0; casos críticos verdes; canonical auth exercitada; tenant/admin/role/cash/status/login/token verificáveis; cleanup sem resíduos e sem tocar registros alheios; receipt registrado | `LUNA_HIGH` |

Após os dois slices, a decisão final é uma revisão de gate, não um terceiro slice de implementação:
`PREMIUM_REVIEW_ONLY`, usando os receipts e o diff/status já existente para não atribuir mudanças
pré-existentes a este fechamento.

## 8. Final Closure Gate

Declarar `MZRT_COMPLETE` somente quando todos forem verdadeiros:

- escopo aprovado é `MZRT-SKELETON`, sem exigir `MZRT-PLATFORM`;
- os 4 MUST funcionais permanecem implementados, sem gap funcional confirmado;
- Feature tests focados de create/status/entitlement e auth canônica estão verdes;
- Architecture checks relevantes estão verdes: area guard, route security, Scribe auth,
  permission drift, tenant scoping/isolation, PII e error envelope;
- `composer docs` termina com sucesso e o artefato atual documenta auth canônica e as rotas MZRT
  reais, sem metadata stale/legacy indevidamente primária;
- E2E HTTP atual executa contra aplicação viva e banco descartável dedicado, cobrindo
  create → `cash`/entitlement → login canônico → suspend/deny → reactivate/allow;
- os asserts de persistência, redaction, auditoria, idempotência, vizinhança e cleanup passam;
- os receipts registram comandos, exit codes, ambiente/DB permitido, data e resultado, sem token,
  segredo ou PII sensível;
- nenhuma conclusão de runtime depende apenas de `[x]`, relatório histórico ou artefato Scribe
  stale.

Não é necessário fechar marketplace, billing SaaS, quotas, catálogo amplo, impersonation ou outra
jornada de área para satisfazer este gate.

## 9. SHOULD/LATER Confirmation

### SHOULD — continuam fora do closure

1. **`MZRT-SHOULD-01` — visão/listagem global de tenants:** não há rota/task atual inequívoca;
   create retorna o ID necessário para a jornada skeleton.
2. **`MZRT-SHOULD-02` — `SystemSetting` e configuração global:** entidade está na spec, mas não
   é necessária para create/status/entitlement.
3. **`MZRT-SHOULD-03` — auditoria/observabilidade ampla:** status já é auditado; operações amplas
   de catálogo/entitlement ainda não fazem parte do skeleton mutável.
4. **`MZRT-SHOULD-04` — permissions de plugin em runtime e roles de tenant:** consumo dinâmico e
   CRUD de roles permanecem em Pending; o skeleton só precisa provisionar/ler o entitlement
   `cash`.

As quatro classificações continuam adequadas. Nenhuma foi promovida a MUST.

### LATER — continuam fora do closure

1. **`MZRT-LATER-01` — marketplace/catalogo developer.**
2. **`MZRT-LATER-02` — billing SaaS e ledger da plataforma.**
3. **`MZRT-LATER-03` — subscriptions, grants e suspensão por inadimplência.**
4. **`MZRT-LATER-04` — quotas, licenses, usage e rate tier.**
5. **`MZRT-LATER-05` — adapters externos first-party.**
6. **`MZRT-LATER-06` — impersonation segura e auditada.**

Esses itens continuam alinhados a `docs/specs/50-ecosystem-plugins/tasks.md:38-63` e às tasks Core
Pending (`docs/specs/10-core-identity/tasks.md:96-106`). Reporting, feature flags e suporte
operacional aparecem como possibilidades condicionais no relatório anterior, mas não são um
sétimo `LATER` formal e não entram no denominador.

## 10. Harness Closure Readiness

Tentativa de falsificação:

- `.codex/hooks.json:2-23` registra Graphify e roteamento em `PreToolUse`, mas não um Stop hook
  que obrigue automaticamente o receipt E2E/Feature;
- `scripts/ai/verify-changes.sh:46-60` mapeia rotas/RBAC para Architecture tests, mas não seleciona
  automaticamente os três Feature tests MZRT;
- o harness pode deixar passar a ausência de Feature MZRT se o agente não executar os testes
  manualmente.

Esse risco não bloqueia o closure porque o gate final nomeia explicitamente os Feature tests, o
runner E2E e os checks de Architecture; a prova pode ser executada e registrada sem depender da
seleção automática do hook. A validação estática do harness passou com um warning esperado sobre a
ausência opcional de `.opencode/opencode.json`; o contrato canônico diz que OpenCode é best-effort
(`AGENTS.md:42-46`). Não foi aberto WS2.

**HARNESS_NOT_BLOCKING_MZRT_CLOSURE**

## 11. Evidence Pending

Permanece pendente:

- receipt atual de Feature/Architecture executado no ambiente adequado, se o fechamento exigir
  nova execução além dos resultados históricos;
- receipt E2E HTTP real com DB `e2e`, auth canônica, side effects e cleanup;
- receipt `composer docs` com sucesso após resolver o ownership do cache;
- inspeção dos artefatos Scribe gerados para confirmar que não são os artefatos stale atualmente
  encontrados.

Esta revisão foi read-only. Não executou testes Pest, E2E ou Scribe porque os primeiros usam DB e
os dois últimos mutam banco/artefatos; não houve promoção indevida para `RUNTIME_VERIFIED`.

## 12. Final Recommendation

Manter o verdict `MZRT_COMPLETE_WITH_EVIDENCE_PENDING`, não implementar nenhum SHOULD/LATER e
fechar somente os dois receipts confirmados. A ordem mínima é:

1. `MZRT-CLOSE-01`: resolver o ambiente de geração e selar o contrato Scribe atual;
2. `MZRT-CLOSE-02`: alinhar a spec E2E à rota canônica, executar a jornada contra app/DB
   descartáveis e registrar cleanup/side effects;
3. aplicar o Final Closure Gate.

Se os dois receipts forem verdes, a recomendação é promover para `MZRT_COMPLETE`. Se qualquer um
falhar por divergência de comportamento, reabrir somente o gap funcional específico revelado pelo
receipt, sem transformar backlog de `MZRT-PLATFORM` em blocker por inércia.

### Audit scope and working-tree note

Foram lidos `AGENTS.md`, `docs/STATE.md`, `docs/ROADMAP.md`, o gap analysis anterior, WS1,
specs/tasks Core/Ecosystem/Architecture, rotas, middleware, Actions, Models, Resources,
permissions, Feature/Architecture/E2E relevantes e scripts de harness. O working tree já estava
sujo antes desta revisão, com alterações em código, testes, specs e relatórios; este relatório não
atribui essas alterações à revisão.

Checks read-only executados nesta revisão:

- `graphify query "MZRT closure blockers MUST-01 MUST-02 auth tenant provisioning lifecycle RBAC area guard platform-wide plugin security persistence tests E2E harness"` — navegação estrutural concluída;
- `python3 scripts/ai/validate-harness.py` — passou, com warning esperado de `.opencode/opencode.json` opcional;
- `git diff --check` — passou;
- inspeção estática via `rg`, `nl`, `git status` e artefatos Scribe — concluída.

Não foram executados comandos que mutassem banco, servidor, cache Scribe ou artefatos de
documentação.
