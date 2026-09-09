# Commercial v0.1 — Paid Pilot Readiness Goal — 2026-09-09

## 1. Executive Status

Engineering atingiu `ENGINEERING_READY_FOR_PAID_PILOT`: não há blocker interno conhecido no
escopo OPS-04, o synthetic comercial HTTP real passou, readiness e alert machinery têm provas
repetíveis e os runbooks/guards estão versionados.

Ativação externa continua `EXTERNAL_ACTIVATION_PENDING`. O paid pilot não é liberado como
`READY` porque domínio/TLS, secrets, canal/owners, backup off-host e aceite humano ainda não
existem neste workspace.

## 2. Starting State

O worktree começou em `main`, HEAD `2bbf75d`, 9 commits locais à frente de `origin/main`, com o
slice OPS-04 inteiro não commitado. As mudanças foram classificadas como trabalho operacional
válido e preservadas. Não foram encontrados staged changes nem artefatos runtime untracked fora
do escopo OPS-04.

Estado inicial: monitoring mínimo pronto, alerting não pronto, synthetic falho por runtime
incorreto, backup/restore/storage/deploy/migration/rollback já verificados por OPS-03.

## 3. OPS-04 Reconciliation

O slice existente foi revisado e corrigido, sem alterar regras do produto:

- readiness ficou separado de liveness, JSON, `200/503`, barato e sem mutação de storage;
- sessão, cookies e CSRF foram removidos somente de `/readiness`, evitando cookie cifrado em probe;
- alertas preservam o incidente quando owner/canal não existem;
- adapter de alerta ganhou status de entrega, contexto seguro e deduplicação mínima;
- backup monitor passou a exigir checksum declarado e verificado dos payloads;
- outbox health ignora falha histórica de mensagem já despachada;
- synthetic exige stack `APP_ENV=e2e`, `APP_DEBUG=false`, DB marcada `e2e` e APP_KEY presente;
- `compose.e2e.yaml` injeta `.env.e2e` explicitamente e fecha a causa do ambiente `local`;
- probes de readiness validam o JSON (`status=ready` e checks essenciais), e não somente HTTP 200;
- o monitor sintético valida a saída do runner e exige ao menos um caso passado e zero falhas;
- validação domain/TLS, secrets e contrato remoto de backup foram adicionados.

## 4. Alerting

`scripts/ops/ops04-alert.sh` é provider-neutral e aceita webhook HTTPS, email via sendmail quando
configurado, ou stdout/exit-code para cron/systemd. Cada emissão contém `problem_detected`,
signal, severity, timestamp UTC, contexto safe, ação, owner, deduplication, delivery status e
exit code. URL de webhook, credencial, Authorization, token, password e PII nunca entram no
payload emitido.

Signals cobertos: app unavailable, readiness fail, DB/storage fail, migration mismatch,
scheduler/worker missing, storage capacity, backup missing/failed/stale, 5xx spike, critical
exception e synthetic failure.

Provas: sem provider, incidente preservado com `delivery_status=not_configured` e exit 1;
webhook indisponível/URL inválida e timeout retornam `delivery_status=failed`, mantêm
`problem_detected=true` e exit 1; repetição do mesmo signal é deduplicada sem virar sucesso.

Estado interno: `ALERTING_MINIMUM_READY`.

## 5. Readiness

`/readiness` verifica app, DB, storage local/object storage, manifest + schema de migrations,
outbox e queue somente quando `OPS_QUEUE_REQUIRED=true`. O storage local verifica root,
readability e writability sem criar/deletar arquivo; object storage usa operação de existência
não mutante. A resposta usa `Cache-Control: no-store` e não emite cookie.

O script externo adiciona processo scheduler, contrato de `schedule:list`, worker condicional e
capacidade livre de volume. O limiar documentado é 1 GiB para o piloto pequeno e deve ser
recalibrado pelo owner no host real.

Estado: `READINESS_MINIMUM_READY`.

## 6. Backup Monitoring

`scripts/ops/ops04-backup-monitor.sh` seleciona o manifest PASS mais recente, rejeita timestamp
futuro/stale/missing, exige DB e storage presentes, checksum hexadecimal de 64 caracteres e
compara os checksums calculados com o manifest.

Threshold operacional: RPO proposto de 24h + grace de 2h = `93600s`. Backup stale/missing/FAIL
gera signal crítico e exit 1; readiness não derruba liveness por backup stale, mas o monitor deve
alertar e impedir promoção.

Estado: `BACKUP_MONITOR_READY_FOR_REHEARSAL`. Prova local PASS e canário stale PASS como falha
esperada; execução recorrente no host real permanece externa.

## 7. Remote Backup Contract

`scripts/ops/ops04-remote-backup.sh` define o contrato sem escolher provider: recebe diretório de
backup local comprovado, destino configurável, credencial externa no environment, adapter
executável, retenção e receipt pós-transferência. O adapter deve copiar/uploadar DB + storage,
escrever `status=PASS` e checksum combinado; o script compara o checksum pós-transferência e
retorna falha sem imprimir credencial ou destino completo.

Canário de ausência de destino/credencial/adapter retorna
`REMOTE_BACKUP_EXTERNAL_BLOCKER`. Um adapter mock de rehearsal passou com checksum e retenção.
Não se considera `/tmp` proteção de produção.

## 8. Synthetic Pilot

Estratégia escolhida: tenant efêmero por execução, com teardown obrigatório. O runner só aceita
DB explicitamente descartável e nunca `--keep` no monitor recorrente.

Prova HTTP real final: projeto Compose `ead2026-e2e-codex`, DB `ead2026_e2e`, `APP_ENV=e2e`,
`APP_DEBUG=false`, APP_KEY presente; `29 passed, 0 failed` na jornada comercial. O monitor
recorrente também executa `mzrt/tenant-lifecycle` como spec anterior (`10 passed, 0 failed`),
provando provisioning MZRT por HTTP e teardown antes da jornada. A jornada comercial cobre:

1. tenant descartável e capability cash preparada pelo harness; provisioning MZRT é provado pelo
   spec HTTP anterior do mesmo monitor;
2. Admin/Instructor/Student;
3. course draft, module, lesson, conteúdo, media e material;
4. publicação explícita;
5. checkout cash/manual por `POST /api/v1/student/checkout`, order/payment, outbox e enrollment active;
6. Student My Courses, navegação, consumo, download e progress;
7. Instructor roster/progress;
8. gate/negativas de persona, curso não consumível e cross-tenant.

Assessment, certificates e gateway automático não são usados.

Estado: `SYNTHETIC_PILOT_VERIFIED`.

## 9. Negative Canaries

| Canary | Resultado comprovado |
|---|---|
| app indisponível | canário runtime abortou antes de mutar, signal `synthetic_app_unavailable`, exit 1 |
| readiness fail | manifest removido no runtime descartável, HTTP 503, signal `synthetic_readiness_failed`, exit 1 |
| DB indisponível | readiness Feature retorna 503 com `checks.db=fail` |
| storage indisponível | readiness retorna 503 |
| migration mismatch | readiness retorna 503 e `ops:migrate --check-only` falha fechado |
| scheduler ausente | readiness externo falha quando serviço/contrato não existe |
| backup FAIL/missing/stale | monitor retorna 1 e emite signal crítico |
| webhook inválido/indisponível | `delivery_status=failed`, incidente original preservado |
| webhook timeout | `delivery_status=failed`, exit 1 |
| student/auth/course/progress | jornada S02 real passou as negativas e asserts de side effect |

## 10. Domain/TLS Activation

`scripts/ops/ops04-domain-tls.sh structural` valida APP_URL HTTPS, hostname e distinção
rehearsal/production. Em modo live valida redirect HTTP→HTTPS, HTTPS, headers de segurança,
cadeia, hostname e expiração por OpenSSL.

Estado: `DOMAIN_TLS_EXTERNAL_BLOCKER`. O exemplo `pilot.example.com` é placeholder e não é
prova de DNS, IP, ACME ou certificado real.

## 11. Secrets Activation

`scripts/ops/validate-production-env.sh` falha fechado para env ausente, placeholder, debug,
APP_URL HTTP, CORS wildcard, DB de teste/E2E, root runtime, usuário runtime igual ao migrator ou
bootstrap, APP_KEY ausente, credenciais remotas ausentes, owner placeholder, webhook não HTTPS
e SMTP sem password quando SMTP estiver ativado. A saída lista somente nomes de chaves.

Estado: `SECRETS_EXTERNAL_BLOCKER`: APP_KEY, credenciais DB runtime/migration/bootstrap/admin,
credencial de backup remoto e credencial/canal de alerta precisam ser provisionados no host
seguro e validados sem receipt contendo valores.

## 12. Security

Confirmados no código/testes: production compose não publica DB nem FPM, rede privada é interna,
storage/media são privados, debug production é false no contrato, runtime DB não é root,
`Authorization`/tokens/passwords/PII não entram em telemetry ou alert payload, erros preservam
envelope seguro, e o commercial capability gate rejeita Course dependente de Assessment/certificate.

O synthetic validou APP_DEBUG off, APP_KEY presente, isolamento de tenant e negativas de persona.
`PiiAudit`, `ErrorEnvelope`, `TenantIsolation`, `MoneyNeverFloat`, `ModuleBoundary`, superfície
de rotas e permissões permanecem verdes.

## 13. Host Runbook

1. Instalar Docker/runtime e criar volumes/narrow private network; garantir ownership do storage.
2. Copiar env fora do Git; preencher secrets, DB users separados, domínio, TLS e owners.
3. Executar `scripts/ops/validate-production-env.sh /secure/path/.env.production`.
4. Construir imagem imutável com `APP_BUILD_SHA` da RC e publicar somente no registry confiável.
5. Criar backup DB+storage, checksum/manifest e executar adapter remoto; conservar receipt.
6. Subir `compose.production.yaml`; rodar migrations somente pelo `ops:migrate` canônico.
7. Confirmar `/up`, `/readiness`, scheduler/outbox, storage e backup monitor.
8. Configurar cron/systemd para readiness, error scan, backup monitor e synthetic; registrar
   stdout/exit-code no canal do owner.
9. Executar synthetic com a stack E2E dedicada antes da ativação; não usar a stack local.
10. Obter aceite humano do checklist abaixo antes de cobrar o piloto.

## 14. Incident/Recovery

Falha de app/DB/storage: conter tráfego, preservar request id/logs safe, corrigir/reiniciar e
revalidar readiness + synthetic. Falha de backup: bloquear promoção, corrigir dump/copy/checksum
e repetir. Falha de scheduler/outbox: reiniciar, verificar backlog e drenar; não declarar sucesso
sem side effect.

Primeiro deploy não tem N-1 legítimo: rollback é `stop traffic → restore baseline/pre-deploy
backup → deploy same known-good RC → readiness → synthetic smoke`. Não fingir code rollback. A
partir da próxima release, a RC atual passa a ser N-1 obrigatório.

## 15. Pilot Claims

### Suportado

Tenant dedicado; Admin; Instructor; Student; Course/Module/Lesson; content; media/material
conforme storage contratado; matrícula manual/free/cash; progress; roster; cobrança externa ou
manual; onboarding assistido.

### Não prometer

Student Assessment; certificate; automated checkout/PSP/webhooks; advanced MediaProvider;
advanced analytics; marketplace/plugins; advanced quiz; reset por e-mail enquanto SMTP/worker não
forem ativados.

## 16. Acceptance Checklist

| Item | Estado |
|---|---|
| RC SHA | `[PROVEN]` — RC operacional `655c939`; OPS-04 hardening `c410e9b`/`c167cf8` |
| host | `[EXTERNAL_PENDING]` |
| domain | `[EXTERNAL_PENDING]` |
| TLS/hostname/expiration | `[EXTERNAL_PENDING]`; validação estrutural passou |
| secrets | `[EXTERNAL_PENDING]` |
| DB runtime privilege | `[PROVEN]` no contrato; confirmação no host `[EXTERNAL_PENDING]` |
| migrations/manifest | `[PROVEN]` — 73 discovered/applied no rehearsal E2E |
| storage | `[PROVEN]` persistência OPS-03 + readiness não mutante |
| backup local | `[PROVEN]` rehearsal PASS + stale canary |
| backup remote | `[EXTERNAL_PENDING]` |
| restore | `[PROVEN]` OPS-03 rehearsal |
| readiness | `[PROVEN]` 5 testes, 29 assertions + HTTP 200 |
| monitoring | `[PROVEN]` minimum signals e error scan |
| alerts | `[PROVEN]` machinery/canaries; `[EXTERNAL_PENDING]` owner/canal |
| scheduler | `[PROVEN]` contract/schedule; host recurrence `[EXTERNAL_PENDING]` |
| outbox | `[PROVEN]` health + S02 side effect; runtime host `[EXTERNAL_PENDING]` |
| synthetic pilot | `[PROVEN]` 29/29 HTTP cases; checkout cash por endpoint real |
| RPO/RTO | `[HUMAN_PENDING]` — RPO ≤24h / RTO ≤4h úteis |
| support owner | `[EXTERNAL_PENDING]` |
| alert owner | `[EXTERNAL_PENDING]` |
| commercial scope | `[PROVEN]` frozen; Assessment/certificate/payment automático fora |
| human approval | `[HUMAN_PENDING]` |

## 17. Internal Blockers

Nenhum blocker interno conhecido permanece após as correções e provas deste relatório.

## 18. External Blockers

Host real; IP/SSH/runtime; DNS; domínio; certificado TLS/ACME; APP_KEY e secrets reais; DB users
e privilégios; owner/support; canal de alerta (`ALERT_PROVIDER_EXTERNAL_BLOCKER`); adapter/destino/
credencial/retention de backup off-host (`REMOTE_BACKUP_EXTERNAL_BLOCKER`); execução recorrente de
cron/systemd; e provisioning/aceite do ambiente de ativação.

## 19. Human Decisions

Aceitar formalmente RPO ≤24h e RTO ≤4h úteis (`HUMAN_ACCEPTANCE_PENDING`); nomear support owner e alert owner; aceitar
rollback-via-restore do primeiro deploy; aceitar claims e exclusões comerciais v0.1; decidir se e
quando SMTP/worker/reset por e-mail entram em promessa.

## 20. Regression

- `bash -n scripts/ops/ops04-*.sh scripts/ops/validate-production-env.sh` — PASS.
- Alert unit: `4 passed (15 assertions)`; error-scan unit: `2 passed (8 assertions)`.
- Readiness Feature: `5 passed (29 assertions)`.
- Infrastructure contract: `6 passed (105 assertions)`.
- PHPStan: `No errors`.
- Pint: PASS.
- Architecture: `43 passed (1438 assertions)`.
- Backup PASS, stale/missing fail, remote adapter mock PASS, domain structural PASS.
- Synthetic HTTP real comercial: `29 passed, 0 failed`; provisioning MZRT adicional: `10 passed,
  0 failed`.
- Negative canaries runtime: app indisponível e readiness inválido por manifest ausente, ambos
  com exit 1 e incidente original preservado.
- `git diff --check` e `scripts/ai/verify-changes.sh` passaram após o commit de implementação.

## 21. Commits

`c410e9b feat(ops): harden paid-pilot monitoring and synthetic` — implementação/harness.
`0dd2249 docs(ops): seal paid-pilot readiness evidence` — relatório, receipt OPS-04 e STATE;
STATE inicial.
`9b0c86f docs(ops): record final local commit provenance` — provenance local reconciliada; nenhum
push/tag/deploy real foi feito.
`c167cf8 feat(ops): close synthetic and harden activation validation` — provisioning MZRT no
monitor, APP_KEY/secrets validation e proteção do remetente de alertas.
`b66175a docs(ops): reconcile final paid-pilot evidence` — receipts reconciliados; `3a98cf3`
atualizou o handoff final.
`1b0f226 fix(ops): make error scan failures explicit` — erro 5xx/critical não pode mais emitir
`error_scan=PASS`; regressão dedicada adicionada.
`12c5516 docs(ops): classify external activation blockers` e `e15469a docs(ops): finalize
paid-pilot handoff state` — códigos externos e handoff final selados.
`5fd4d21 fix(ops): exercise real checkout in synthetic pilot` — removeu fixtures financeiras
diretas da jornada e comprovou checkout cash por HTTP real (`29/29`).
`81cb6fd fix(ops): validate readiness probe payload` — probes passaram a validar o JSON de
readiness e os canários runtime de app/manifest foram comprovados.
`5582322 fix(ops): reject empty synthetic runner results` — o monitor passou a exigir resultado
de runner com casos executados, pelo menos um sucesso e zero falhas.

## 22. Engineering Verdict

`ENGINEERING_READY_FOR_PAID_PILOT` — machinery, guards, runbooks, regression e synthetic interno
estão comprovados; activation externa ainda não foi simulada como produção.

## 23. Paid Pilot Verdict

`PAID_PILOT_NEAR_READY` — não usar `PAID_PILOT_READY_WITH_MANUAL_OPERATIONS` enquanto os itens
external/human pending não estiverem comprovados e aceitos.

## 24. Exact Next Inputs Required

1. hostname/IP/host operacional real e owner de deploy;
2. domínio/DNS e email/credencial ACME/TLS;
3. APP_KEY, DB credentials separadas e demais secrets provisionados fora do Git;
4. alert owner, canal real e webhook/email opcional;
5. adapter, destino, credencial e retenção de backup remoto;
6. support owner e confirmação da execução scheduler/monitor;
7. aceite humano explícito de RPO/RTO e checklist/claims v0.1.
