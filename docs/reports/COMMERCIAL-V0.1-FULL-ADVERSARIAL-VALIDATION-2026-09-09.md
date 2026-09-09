# Commercial v0.1 — validação adversarial completa — 2026-09-09

## Executive Verdict

`LAUNCH_PACKAGE_VALID_WITH_GAPS`, `ENGINEERING_NOT_READY` e `PAID_PILOT_NOT_READY`.
O pacote documental existe e é operacionalmente útil, mas não é adversarialmente validado como
pronto: há um BLOCKER de segurança no restore, readiness atual 503 por manifest ausente, uma
regressão financeira reproduzível e gaps de proveniência/receipt/deploy.

## 1. Escopo e regra de decisão

Esta é uma validação independente do que vinha sendo declarado como concluído para o Commercial
v0.1 e a prontidão para Paid Pilot. O relatório separa contrato-alvo, estado do Git, evidência
histórica, testes in-process, HTTP externo e dependências humanas/externas.

Não foram feitos push, tag, deploy, correções de código ou execução destrutiva. O único artefato
novo desta auditoria é este relatório; `docs/STATE.md` será atualizado somente no checkpoint final.

## 2. Proveniência selada

| Item | Observado |
|---|---|
| Branch | `main` |
| HEAD | `49ac6f87032ef0b6b4d1693b3c9ff4a51df55ad1` |
| `origin/main` | `9513d1efccc0bc5be2d4336eb4952badd1ef3196` |
| Relação | `main` está 28 commits à frente; não houve push nesta sessão |
| Working tree inicial | limpa, sem staged/unstaged/untracked rastreados |
| Último commit | `docs: refresh paid pilot handoff state` |
| Artefato de release no checkout | `release/migrations.manifest.json` ausente e não rastreado |
| Receipt atual | não encontrado no repositório nem em `/tmp` |
| Scribe/Postman | `public/docs/` ignorado e sem artefato rastreado |

Os receipts OPS-03/OPS-03B existentes em `/tmp` são íntegros como arquivos históricos, mas foram
gerados para `655c939`/`18456c0`, não para o HEAD atual. São `HISTORICAL_EVIDENCE_ONLY`.

## 3. Vocabulário de evidência

`PROVEN_CURRENT` significa execução atual contra o runtime correto. `PROVEN_WITH_LIMITATION` é
prova parcial (por exemplo, Feature/Architecture ou rehearsal histórico). `EXTERNAL_PENDING` e
`HUMAN_PENDING` dependem de host, provedor ou decisão fora do checkout. `NOT_PROVEN` não tem a
prova necessária. `INVALIDATED` é uma afirmação contradita por evidência atual ou por um caminho
de falso sucesso demonstrado no código.

## 4. Fontes examinadas

Foram confrontados `AGENTS.md`, `docs/specs/`, `docs/STATE.md`, `docs/commercial/`, reports OPS-01
a OPS-04, código dos módulos, rotas, Compose/Dockerfile, scripts operacionais, testes Pest,
`graphify-out/graph.json` como índice auxiliar e o runtime Docker atual. A fonte executável venceu
claims documentais quando houve conflito.

## 5. Launch Package

O pacote contém 24 arquivos substanciais em `docs/commercial/`, incluindo checklist, runbook,
intake/onboarding, demo, claims, oferta, pricing, ICP, discovery, qualification, support,
first-week, sucesso/exit/feedback, pós-piloto, FAQ, release notes, índice, revisão adversarial e
Golden Path. Os links relativos entre os arquivos foram verificados e não há link quebrado.

Isso prova existência e coerência básica do pacote, não sua execução no host. O próprio pacote
mantém vários itens externos/humanos como pendentes, mas `00-ACTIVATION-CHECKLIST.md` ainda marca
storage, migrations, backup local, restore, readiness e synthetic como `PROVEN`; essa classificação
é incompatível com o runtime atual e deve ser lida como histórica.

## 6. Golden Path versus API real

O Golden Path descreve uma jornada coerente: provisionar tenant, convidar Admin, criar/publicar
Course/Module/Lesson/material, matricular por fluxo suportado, consumir/progredir e consultar
roster. As rotas observadas correspondem às superfícies `/api/v1/mzrt`, `/api/v1/admin`,
`/api/v1/instructor` e `/api/v1/student`.

A coerência não foi convertida em `PROVEN_CURRENT`: não existe app E2E descartável ativo neste
runtime, e a execução HTTP histórica usa fixtures diretas de harness para parte do setup. Não há
evidência de que a jornada completa esteja disponível no mesmo RC atualmente servido.

## 7. Closure de produto

As fatias MZRT, Admin, Instructor e Student têm testes Feature e Architecture relevantes. O grupo
selecionado nesta auditoria passou sequencialmente: `37 passed (469 assertions)`, cobrindo MZRT tenant
creation, Student S02 progress, Instructor I02 e checkout.

O status correto é `PROVEN_WITH_LIMITATION`: cobertura in-process e histórico HTTP são fortes,
mas não há replay HTTP atual contra stack E2E descartável vinculada ao HEAD.

## 8. MZRT

Tenant creation/entitlement e negativos de isolamento aparecem em Feature e nos receipts históricos
de `mzrt/tenant-lifecycle` (`10/10`). O container E2E correspondente não está ativo atualmente.

Status: `PROVEN_WITH_LIMITATION`, não `PROVEN_CURRENT`.

## 9. Admin

Admin authoring, publicação, convite e confirmação manual têm rotas e testes. A superfície usa
`auth:sanctum`, `area.guard:admin`, tenant requerido e tenant access nas rotas canônicas.

Status: `PROVEN_WITH_LIMITATION`; a autorização e o contrato in-process passaram, sem prova HTTP
externa atual do mesmo release servido.

## 10. Instructor

Course/module/lesson, media/material e roster/progress têm testes e specs E2E históricas. O teste
selecionado `InstructorI02ApiTest.php` passou dentro do grupo de 37 testes.

Status: `PROVEN_WITH_LIMITATION`.

## 11. Student

Student own-access, consumo, estados `pending/expired/cancelled`, progresso e negativos de
persona/tenant têm cobertura. O teste S02 de progresso passou, mas a jornada comercial integrada
de 29 casos não foi repetida no runtime atual.

Status: `PROVEN_WITH_LIMITATION`.

## 12. RBAC, áreas e isolamento de tenant

Architecture passou com `43 passed (1443 assertions)` na execução atual. Os probes HTTP sem
autenticação retornaram envelope 401 canônico para Student, Instructor, Admin e a rota de Assessment
canônica. As rotas canônicas carregam os guards de área esperados; o legado Assessment continua
fora do invariante de área e possui `assessment.legacy.student.blocked`.

Não foi demonstrado IDOR/cross-tenant contra o serviço real atual. O status é
`PROVEN_WITH_LIMITATION`, não uma garantia de isolamento em produção.

## 13. Student Assessment, certificates e superfície não vendável

O claim comercial “não vender Student Assessment/certificates” está explícito em
`docs/commercial/05-COMMERCIAL-CLAIMS.md` e no Golden Path. A rota pública de verificação de
certificado respondeu `200` com `valid=false` para número inválido, e existem superfícies Admin e
Instructor de Assessment documentadas/roteadas.

Isso não prova que Student possa executar Assessment: o bloqueio legacy Student existe. Porém,
“não vender” continua sendo uma decisão comercial, não sinônimo de superfície tecnicamente ausente.
A qualificação comercial deve impedir promessa desses endpoints internos.

## 14. Commercial Capability Gate

`CommercialCapabilityGateTest` e os testes de publicação/certificate/quiz passaram dentro das
suites executadas. O gate rejeita Course dependente de Assessment/certificate conforme o contrato,
e os testes exercitam flags de capability.

Status: `PROVEN_WITH_LIMITATION`: o gate tem evidência in-process; não foi replayado via HTTP no
runtime E2E atual.

## 15. Checkout, dinheiro e outbox

O checkout cash/manual usa cents inteiros, order/payment, idempotência, transação e outbox. O
grupo selecionado de checkout passou. A confirmação manual separa `record` dentro da transação de
`publish` fora dela.

Há, contudo, uma regressão reproduzível: `./vendor/bin/sail artisan test --compact
tests/Feature/Financial/ConfirmManualPaymentApiTest.php` terminou em `12 passed, 1 failed
(145 assertions)`. O cenário de publish falho em `:250` falha com
`BadMethodCallException: Received ... LogManager::error(), but no expectations were specified`.
O corpo do teste espera sucesso e somente permite `Log::warning`; a request passa pelo Handler
como erro não tratado. O caminho financeiro não pode ser chamado de regression-green.

Status agregado: `PROVEN_WITH_LIMITATION`; a falha é Finding F-07.

## 16. Progress, roster e side effects

O grupo selecionado confirmou 37 testes/469 assertions, incluindo Student S02 e Instructor I02.
As specs HTTP históricas incluem persistência de progresso, aggregate de enrollment, replay sem
regressão, roster próprio e isolamento de Student/Instructor.

Status: `PROVEN_WITH_LIMITATION`, por ausência de execução externa atual e pela divergência de
proveniência dos receipts.

## 17. Storage e mídia

O contrato estático usa disks privados e paths tenant-scoped; `GenerateCourseMaterialDownloadUrlAction`
rejeita path fora de `tenants/<id>/`, `..` e backslash, e usa URL temporária. O readiness atual
reportou storage `pass`, `disk=local`, `mutating=false`.

Isso cobre configuração e safety path básico, não persistência após restart no RC atual nem object
storage real. Status: `PROVEN_WITH_LIMITATION`.

## 18. Backup local

Os backups históricos em `/tmp` foram reinspecionados: manifests `PASS`, checksums SHA-256 dos
payloads conferem independentemente, com 73 migrations e contagens de dump/storage coerentes. O
canário de backup inválido também foi rejeitado.

Eles são de releases ancestrais e não do HEAD/runtime atual. Status: `PROVEN_WITH_LIMITATION`.

## 19. Restore

O relatório OPS-03 documenta restore histórico com checksum e smoke. Nesta auditoria, o restore
não foi repetido destrutivamente; o manifest de falha foi rejeitado antes de qualquer mutação.
Além disso, `scripts/ops/ops03-restore.sh:17-27` valida apenas status/payload/checksum e
`.:29-57` restaura DB/storage sem pós-check de migrations, registros canônicos, auth, catálogo,
enrollment, progresso ou fluxo financeiro.

Status: `NOT_PROVEN` para restore operacional atual. O receipt histórico não prova compatibilidade
do HEAD nem integridade funcional pós-restore.

## 20. Migrations e manifest

O Dockerfile de produção cria o manifest em build (`Dockerfile.production:59-66`), mas o checkout
não contém `release/migrations.manifest.json`. No container atual:

```text
php artisan ops:migrate --manifest-only --no-interaction
MIGRATION_MANIFEST_MISSING: /var/www/html/release/migrations.manifest.json
exit=1
```

`/readiness` confirmou `503` com `checks.migration_manifest.status=fail` e
`reason=dependency_unavailable`. Isso invalida o claim atual de migration/readiness pronto.
Status: `INVALIDATED`.

## 21. Deploy e rollback

O histórico OPS-03/OPS-03B registrou deploy rehearsal e explicitou que N-1 real não foi comprovado.
O código atual apresenta riscos adicionais: `ops03-deploy.sh:19-21` para/substitui serviços e
limpa cache antes de validar `OPS03_ENV_FILE` em `:23-25`; `:30-33` passa a credencial de migration
como argumento `-e`; e o script só confere `status=PASS` do backup, não checksum/payload completo
antes de mutar.

Rollback N-1 não tem build/runtime atual demonstrado; o próprio report histórico registrou falha do
build `b045025` e `ROLLBACK_NOT_READY`. Status deploy: `INVALIDATED` como claim de prontidão;
rollback: `NOT_PROVEN`.

## 22. Readiness e liveness

O app vivo `ead2026-laravel.test-1` responde `/up`, mas é `sail-8.4/app`, `APP_ENV=local`,
`APP_DEBUG=enabled`, com manifest ausente. O request atual a `http://localhost:8099/readiness`
retornou `HTTP/1.1 503 Service Unavailable` e checks app/db/storage/outbox pass, migration fail.

O código de readiness é não mutante e o Feature `ReadinessTest` passou, mas o claim
`READINESS_MINIMUM_READY` não é verdadeiro no runtime servido. Status: `INVALIDATED`.

## 23. Monitoring e error scan

`ops04-readiness.sh`, `ops04-deploy-observe.sh`, backup monitor, error scan e scripts de alert
foram exercitados em canários. Contra o app atual, `ops04-readiness.sh` falhou com
`readiness_failed`, e `ops04-deploy-observe.sh` falhou com quatro checks (readiness, synthetic,
backup sem contexto e log ausente). O error scan reconhece somente shapes JSON específicos de 5xx
e `level_name=CRITICAL`, portanto pode perder logs textuais/proxy fora desse formato.

Status: `PROVEN_WITH_LIMITATION` para a machinery; não há monitoring recorrente real nem prova de
cobertura de todos os logs.

## 24. Alerting

Sem provider, `ops04-alert.sh` preserva `problem_detected=true`, `delivery_status=not_configured`
e exit 1. Canários de webhook inválido/indisponível e deduplicação estão cobertos por testes.

Não existe canal/owner real confirmado. O comando aceita `stdout-exit-code` como default e o
orchestrator não executa um canário de entrega antes de escrever o receipt. Status: `EXTERNAL_PENDING`;
essa lacuna também participa de F-05.

## 25. Synthetic pilot

O synthetic histórico de MZRT `10/10` e comercial `29/29` foi HTTP real em stack descartável, mas
os fixtures de `student-s02-commercial-integrated.php:29-83` criam gateways/users/enrollments
diretamente e `:172-177` escreve storage diretamente. Isso é válido como setup de harness, mas não
prova onboarding externo inteiro. O runner exige cases não vazios (`E2eRunCommand.php:449-454`) e
possui canário de alinhamento DB/servidor, porém a stack E2E correspondente não está presente agora.

O synthetic atual contra `localhost:8099` falhou antes de fixtures por readiness 503. Status:
`NOT_PROVEN` para o HEAD/runtime atual.

## 26. Cleanup e resíduos

O runner remove users/tenants conhecidos e activity log associada em
`E2eRunCommand.php:527-574`; o histórico reporta teardown e volumes descartáveis removidos.
Não há execução atual para confirmar zero resíduos no banco/runtime atual. Fixtures adicionais
criadas por closures fora dos IDs conhecidos dependem do cleanup da própria spec.

Status: `PROVEN_WITH_LIMITATION` como desenho/histórico, não current.

## 27. Safety de produção e caminhos destrutivos

`ops03-destroy.sh:5-11` exige rehearsal, acknowledgement e nomes de volumes contendo `ops03`.
Porém `ops03-restore.sh:5-7` não exige que `PRODUCTION_STORAGE_VOLUME` seja um volume ops03;
`ops03-restore.sh:48-55` monta esse valor e executa `find /destination -mindepth 1 -delete`.
Logo, um operador pode combinar guards de rehearsal com volume de produção e apagar seu storage.

Esse é caminho source→sink confirmado e BLOCKER F-01.

## 28. Secrets, PII e logging

Não foi encontrado secret real rastreado; os `sk_test` encontrados são fixtures de teste. `.env`,
`.env.e2e` e receipts são ignorados, conforme esperado. `RequestTelemetry` registra request id,
tenant/user id, rota, status e latência, sem Authorization/payload.

Há um sink inseguro em `app/Modules/Assessment/Listeners/IssueCertificateOnCourseCompletedListener.php:21-26`:
o objeto completo da exceção é enviado ao logger em `'exception' => $exception`. Mensagem e trace
podem carregar entrada sensível de uma falha downstream. A invariante `PiiAudit` passou, mas isso
não elimina esse caminho de log. Finding F-08.

## 29. API contract, auth surface e Scribe

Probes HTTP atuais de 401 retornaram envelope canônico. A rota pública de verificação de certificado
é deliberada. As rotas Instructor/Admin Assessment existem e têm guards de área próprios; as rotas
legacy Assessment não estão cobertas por `AreaRouteGuardTest`, conforme contrato.

`public/docs/collection.json` e demais saídas Scribe não são rastreadas; sua timestamp é anterior
ao HEAD atual e não há receipt Scribe atual nesta auditoria. Os reports históricos dizem que
`composer docs` passou, mas isso é evidência histórica/ignorada, não contrato versionado atual.
Status do contrato documental: `PROVEN_WITH_LIMITATION`.

## 30. Qualidade dos testes

Não foram encontrados `skip`, `todo`, `only`, `markTestSkipped` ou assertions triviais pelos
patterns auditados. Architecture passou `43/1443`; o estado/documento declarava `43/1438`, uma
divergência de cinco assertions. O teste financeiro vermelho é reproduzível isoladamente.

Uma primeira execução paralela contaminou o banco compartilhado de testes por concorrência de
`RefreshDatabase`; o processo foi encerrado e os testes relevantes foram repetidos sequencialmente.
As falhas daquele lote paralelo não foram usadas como evidência de produto.

## 31. Runtime drift e proveniência

O runtime atual é local/debug e serve o checkout montado por volume; não é a imagem imutável de
produção nem a stack E2E. O container OPS-03 ativo é `ead2026-ops03/exact-current:18456c0`, também
não o HEAD. Os claims de 29/29, readiness 200 e 73 migrations applied pertencem a receipts de
outros RCs.

O `activate-paid-pilot.sh:112-117` só verifica que `APP_BUILD_SHA` existe como commit no checkout;
não exige igualdade com HEAD, checkout limpo, digest/label OCI ou pull/build imutável. Isso é F-04.

## 32. Performance, concorrência e failure injection

O código financeiro usa locks e idempotência conforme o contrato; os testes selecionados passaram.
Não foi executado benchmark, teste de carga, corrida de checkout concorrente no app vivo ou ensaio
de falha de PSP contra ambiente seguro atual.

Failure injection disponível foi observada em readiness/alert/backup/synthetic e falhou fechado.
Não houve game day atual de restore/deploy/rollback; o histórico não fecha N-1. Portanto não há
claim de performance ou recovery current.

## 33. Limites humanos e externos

Permanecem não confirmados: host operacional, domínio/DNS, certificado ACME, secrets reais,
separação/privileges de DB no host, canal e owners de alerta, destino/adapter/credencial off-host,
cron/systemd, scheduler recorrente, support owner, aceite de RPO ≤24h/RTO ≤4h úteis e aprovação
humana dos claims/exclusões.

O `validate-production-env.sh` rejeitou o `.env.production.example` por placeholders, como deve.
Isso prova fail-closed do exemplo, não ativação.

## 34. Findings e remediações mínimas

| ID | Severidade | Exploitabilidade | Evidência | Source → sink e impacto | Remediação mínima |
|---|---|---|---|---|---|
| F-01 | BLOCKER | confirmada | `scripts/ops/ops03-restore.sh:5-7,48-55` | env/operador controla `PRODUCTION_STORAGE_VOLUME` → `find ... -delete` no volume montado; storage de produção pode ser apagado | exigir allowlist explícita de volume/project/db ops03, resolver/inspecionar nomes antes do mount e abortar se qualquer volume não for disposable; testar matriz production-like |
| F-02 | HIGH | confirmada | `scripts/ops/ops03-deploy.sh:19-33` | env ausente/ inválido → stop/up/optimize antes do preflight; indisponibilidade parcial e mudança sem prerequisites | mover toda validação de env, backup, imagem, volumes e credenciais antes de qualquer mutação |
| F-03 | HIGH | provável/confirmada no processo local | `scripts/ops/ops03-deploy.sh:30-33` | senha lida do env → argumento `docker compose ... -e DB_MIGRATION_PASSWORD=...`; exposição em argv/process audit | usar secret file/stdin/credential store ou job container com env injetado sem valor na argv; verificar `ps`/Docker events |
| F-04 | HIGH | confirmada | `scripts/ops/activate-paid-pilot.sh:103-117,119-157` | SHA apenas existente → compose pode usar tag/cache diferente; receipt atribui RC não vinculada a digest/label | exigir SHA==release commit selado, checkout limpo, imagem construída/puxada e conferir `org.opencontainers.image.revision`/digest em todos os serviços |
| F-05 | HIGH | confirmada | `scripts/ops/activate-paid-pilot.sh:154-180` | gates não parseados + ausência de canário real → campos `remote_backup`, `tls`, `synthetic`, `alert_channel_configured` e `final_verdict` podem ser gravados `PASS` sem prova correspondente | capturar exit/status/payload de cada gate, exigir alert delivery canary e gerar receipt somente a partir dos resultados observados |
| F-06 | HIGH | confirmada | runtime `/readiness`, `ops:migrate`, `docs/reports/...READINESS...:5-11,68,117` | claim histórico `READY` → runtime atual 503 por manifest ausente; cobrança/promoção poderia ocorrer sem readiness | não promover nenhum receipt histórico; gerar manifest no build efetivamente servido e bloquear abertura enquanto `/readiness` não for 200 com checks pass |
| F-07 | HIGH | confirmada como regressão de validação | `tests/Feature/Financial/ConfirmManualPaymentApiTest.php:224-268` | publish do outbox falha → Handler chama `Log::error` não esperado e request/test falha; fluxo de confirmação não está regression-green | corrigir a composição/mocking do caminho de falha, preservar `warning` seguro e adicionar teste que confirme 2xx + outbox pendente + drain; repetir suíte Financial |
| F-08 | MEDIUM | confirmada | `app/Modules/Assessment/Listeners/IssueCertificateOnCourseCompletedListener.php:20-26` | exceção downstream → objeto completo no log; mensagem/trace podem carregar PII/secret | registrar somente classe, IDs de domínio e request id; nunca objeto/trace bruto no canal operacional; adicionar teste de redaction |
| F-09 | MEDIUM | confirmada | `scripts/ops/ops04-remote-backup.sh:36-48` | adapter escreve receipt `PASS/checksum` → script confia nele sem readback/listagem do destino; adapter defeituoso pode forjar sucesso | exigir readback independente de DB+storage remoto, checksum remoto e retenção/versão verificáveis |
| F-10 | MEDIUM | confirmada | `scripts/ops/ops04-backup-monitor.sh:13-37` | qualquer manifest mais recente com checksum declarado → monitor aceita sem binding a release/backup provenance; risco de backup arbitrário/forjado | validar `backup_id`, `rc_sha`, migration count, error vazio, diretório esperado e assinatura/proveniência do produtor |
| F-11 | MEDIUM | confirmada como limite | `app/Console/Commands/E2eRunCommand.php:527-574`, spec S02 `:29-83` | setup/capture pode criar dados fora do conjunto conhecido → teardown pode deixar resíduos; synthetic não prova onboarding real | rastrear todos os IDs criados por execução, validar contagens antes/depois e falhar se qualquer fixture permanecer |
| F-12 | MEDIUM | confirmada | `public/docs/` ignorado; `docs/STATE.md:39-56` | artifact Scribe fora do Git e receipt ausente → consumidores podem usar documentação stale sem revisão do commit | versionar ou armazenar artifact endereçado ao commit, gerar Scribe no pipeline e comparar rotas/auth contra HEAD |
| F-13 | LOW | confirmada | `scripts/ops/ops03-backup.sh:45`, report de readiness `:3` | diff check encontra trailing whitespace em código/docs do branch → hygiene e gate declarados como PASS ficam divergentes | formatar os dois arquivos e repetir `git diff --check` |

## 35. Matriz final de claims

| # | Claim auditado | Status |
|---:|---|---|
| 1 | Closure API-first do produto v0.1 | PROVEN_WITH_LIMITATION |
| 2 | MZRT tenant lifecycle | PROVEN_WITH_LIMITATION |
| 3 | Admin operations | PROVEN_WITH_LIMITATION |
| 4 | Instructor authoring | PROVEN_WITH_LIMITATION |
| 5 | Student consumption | PROVEN_WITH_LIMITATION |
| 6 | Tenant isolation | PROVEN_WITH_LIMITATION |
| 7 | RBAC/area guards | PROVEN_WITH_LIMITATION |
| 8 | Commercial capability gate | PROVEN_WITH_LIMITATION |
| 9 | Cash/manual checkout | PROVEN_WITH_LIMITATION |
| 10 | OrderPaid outbox/enrollment side effect | PROVEN_WITH_LIMITATION |
| 11 | Student progress | PROVEN_WITH_LIMITATION |
| 12 | Instructor roster/progress | PROVEN_WITH_LIMITATION |
| 13 | Private storage/media | PROVEN_WITH_LIMITATION |
| 14 | Local backup integrity | PROVEN_WITH_LIMITATION |
| 15 | Restore current/functional | NOT_PROVEN |
| 16 | Migration manifest/current schema | INVALIDATED |
| 17 | Deploy readiness | INVALIDATED |
| 18 | N-1 rollback | NOT_PROVEN |
| 19 | Current readiness 200 | INVALIDATED |
| 20 | Monitoring machinery | PROVEN_WITH_LIMITATION |
| 21 | Alert delivery channel | EXTERNAL_PENDING |
| 22 | Current synthetic pilot | NOT_PROVEN |
| 23 | Activation orchestrator gates | INVALIDATED |
| 24 | Activation receipt | INVALIDATED |
| 25 | Launch Package completeness | PROVEN_WITH_LIMITATION |
| 26 | Commercial claims/exclusions | PROVEN_WITH_LIMITATION |
| 27 | Manual onboarding/billing operation | PROVEN_WITH_LIMITATION |
| 28 | `ENGINEERING_READY_FOR_PAID_PILOT` | INVALIDATED |
| 29 | `PAID_PILOT_NEAR_READY` as current launch state | INVALIDATED |

Contagem: `PROVEN_WITH_LIMITATION=17`? Não: contando a matriz acima, são 18
`PROVEN_WITH_LIMITATION`, 3 `NOT_PROVEN`, 7 `INVALIDATED` e 1 `EXTERNAL_PENDING`, total 29.
Não há claim `PROVEN_CURRENT` nem `HUMAN_PENDING` entre os 29 claims técnicos/comerciais; os
pendentes humanos estão explicitados na seção 33.

## 36. Verdict final

| Dimensão | Verdict |
|---|---|
| Launch Package | `PACKAGE_EXISTS_WITH_LIMITATIONS` |
| Engineering | `ENGINEERING_NOT_READY` |
| Paid Pilot | `PAID_PILOT_NOT_READY` |
| Comercialização hoje | não abrir cobrança nem comunicar `READY` |

O blocker decisivo é F-01, agravado por F-02/F-03/F-04/F-05, readiness atual 503 por manifest
ausente e a regressão financeira F-07. O próximo passo seguro é registrar e corrigir esses
findings, reconstruir/verificar uma imagem vinculada ao HEAD, repetir migrations/readiness,
backup/restore/rollback e synthetic em stack descartável, e somente então obter os inputs externos
e a aprovação humana do piloto.

## 37. Comandos e resultados observáveis

- `git status --short --branch`: `main...origin/main [ahead 28]`, sem mudanças rastreadas no início.
- `./vendor/bin/sail artisan test --compact tests/Feature/Financial/ConfirmManualPaymentApiTest.php`:
  `12 passed, 1 failed (145 assertions)`.
- Grupo sequencial MZRT/Student/Instructor/checkout: `37 passed (469 assertions)`.
- Architecture: `43 passed (1443 assertions)`; a documentação anterior dizia 1438.
- `docker exec ... php artisan ops:migrate --manifest-only --no-interaction`: exit 1,
  `MIGRATION_MANIFEST_MISSING`.
- `curl http://localhost:8099/readiness`: HTTP 503, `status=not_ready`, migration manifest fail.
- `docker ps`: app principal `sail-8.4/app`; OPS-03 `exact-current:18456c0`; nenhuma app E2E dedicada.
- `scripts/ops/ops04-readiness.sh` contra `localhost:8099`: exit 1, readiness failed.
- `scripts/ops/ops04-synthetic.sh` contra `localhost:8099`: exit 1 antes de fixtures, readiness failed.
- `scripts/ops/ops04-deploy-observe.sh` contra runtime atual: exit 1, quatro checks falhos.
- `activate-paid-pilot.sh --dry-run` sem env externo: exit 0, `mutation=none`, receipt não escrito,
  `EXTERNAL_PENDING`.
- `validate-production-env.sh .env.production.example`: falha por placeholders, como esperado.
- `bash -n scripts/ops/*.sh`: PASS.
- `git diff --check origin/main...HEAD`: falhou por trailing whitespace em dois arquivos; não foi
  corrigido nesta auditoria.

## 38. Auditoria não executada por limite seguro

Não foram executados Scribe mutante, activation `--execute`, restore destrutivo no volume ativo,
deploy real, rollback real, carga/performance, PSP externo, alert provider real, backup off-host ou
E2E HTTP contra o banco local. Cada item permanece não confirmado ou externo, nunca implicitamente
verde.

## 39. Revalidação pós-remediação — 2026-09-09

As remediações foram aplicadas em dois commits locais, sem push, tag ou deploy produtivo:

- `f507f9124c397a06f2c0e4f77654ec78ab8eab3d` — proveniência de release, preflight/deploy,
  backup/monitoramento remoto, readiness, activation gates, logging seguro e regressão financeira.
- `4871373` — guards de identidade dos volumes descartáveis, correção dos templates Docker Go,
  teardown E2E por fixtures aninhadas e limpeza do plugin cash criado pelo synthetic.

O baseline acima continua preservado como registro da auditoria inicial. Esta seção registra somente
evidência nova e não converte automaticamente qualquer claim em `PROVEN_CURRENT`.

### Findings revalidados

| Finding | Evidência atual | Estado pós-remediação |
|---|---|---|
| F-01 | `ops03-destroy.sh` removeu somente `ead2026-ops03` após inspeção de projeto/volume; `ops03-restore.sh` validou os três volumes canônicos. Restore passou e removeu um marcador criado depois do backup. | `PROVEN_WITH_LIMITATION`: stack descartável atual; nenhum volume produtivo tocado. |
| F-02/F-03 | Preflight de deploy/env e migração usam arquivo de ambiente; nenhum segredo foi colocado na argv. Migração atual reportou `expected=73`, `discovered=73`, `applied_after=73`. | `PROVEN_WITH_LIMITATION`: host e secrets reais continuam externos. |
| F-04 | Imagens app/web foram reconstruídas do último commit de código `4871373d86808bb585623298c3a3c573eabc4b2a`; ambas carregam esse revision e `org.opencontainers.image.migrations.manifest.sha256=2dd9bc56739e2df0ac697b3c5b5cfeee6de537be8a7b3404c07a608d8162dc78`. Digests: app `sha256:f70b2319b5d367a8c83a30c76fa5f50074292f80d8cf967137dee3aef906a059`, web `sha256:d1c16c0a195e8f15ca05db5de5399c808f67fb6418d4781d4dee970e1d32bec7`. | `PROVEN_WITH_LIMITATION`: build local atual, sem promoção externa. |
| F-05 | `activate-paid-pilot.sh --dry-run` continua sem mutação/receipt; gates agora exigem os resultados observados, incluindo canário de alerta e readback remoto. | `PROVEN_WITH_LIMITATION`: activation execute permanece bloqueado por dependências externas. |
| F-06 | Qualification stack atual respondeu `/readiness` com `status=ready`, app/db/storage/migration_manifest/outbox `pass` e queue `not_required`. | `PROVEN_WITH_LIMITATION`: certificado público/live TLS e host real não foram validados. |
| F-07 | Confirmação manual financeira voltou a verde; lote ops/assessment/financial passou `33/33` com `245` assertions. | `PROVEN_WITH_LIMITATION`: PSP automático permanece fora do escopo. |
| F-08 | Listener registra classe da exceção, não o objeto completo; teste de segurança passou. | `PROVEN_WITH_LIMITATION`: observabilidade externa ainda não provisionada. |
| F-09/F-10 | Backup monitor atual passou com manifest assinado; upload remoto + verificação independente e canário de entrega de alerta passaram usando adapters sintéticos locais. | `PROVEN_WITH_LIMITATION`: destino off-host, credenciais e canal real permanecem externos. |
| F-11 | E2E HTTP atual passou `29/29` no synthetic commercial pilot e teardown não deixou resíduos; tenant lifecycle passou `10/10`. | `PROVEN_WITH_LIMITATION`: a execução é uma stack local descartável. |
| F-12/F-13 | Activation exige geração/hash do Scribe; `bash -n scripts/ops/*.sh`, Pint e `git diff --check` passam no delta atual. | `PROVEN_WITH_LIMITATION`: artifact Scribe não foi promovido a contrato versionado externo. |

### Resultado de suites e runtime atual

- Architecture: `43 passed (1456 assertions)`.
- Ops/Assessment/Financial focados: `33 passed (245 assertions)`.
- E2E HTTP real: `mzrt/tenant-lifecycle` `10/10`; `ops04/synthetic-pilot` `29/29`.
- Restore/backup/readiness OPS-03: `backup=PASS`, `restore=PASS`, `readiness=PASS`, migrations
  `73/73`, scheduler requerido e running.
- Dry-run de activation: `PASS`, `mutation=none`, sem receipt escrito.

### Verdict preservado

Mesmo com a revalidação local atual, o verdict de lançamento não sobe: `LAUNCH_PACKAGE_VALID_WITH_GAPS`,
`ENGINEERING_NOT_READY` e `PAID_PILOT_NOT_READY`. Não há `PROVEN_CURRENT` emitido. Continuam
bloqueadores externos host, DNS/TLS público, secrets reais, privilégios DB, owner/canal de alertas,
backup off-host e scheduler recorrente; continuam decisões humanas RPO/RTO, owners, rollback,
claims/exclusões comerciais e aprovação de promoção. N-1 rollback, activation execute e receipt
atual seguem não provados. A cobrança permanece fechada.
