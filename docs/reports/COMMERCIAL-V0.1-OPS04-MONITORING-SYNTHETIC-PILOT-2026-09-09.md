# COMMERCIAL V0.1 — OPS-04 Monitoring, Alerting, Readiness and Synthetic Pilot

Data: 2026-09-09
Escopo: hardening operacional provider-neutral; nenhum deploy em produção real e nenhuma expansão funcional.

## 1. Baseline

- Branch: `main`; HEAD no início: `2bbf75d` (`docs(ops): seal OPS-03B migration rollback evidence`).
- Primeira RC operacional válida: `655c939`.
- Evidências OPS-03 preservadas: `BACKUP_VERIFIED`, `RESTORE_VERIFIED`, `DEPLOY_REHEARSAL_VERIFIED`, `MIGRATION_DISCOVERY_HARDENED` e `ROLLBACK_VIA_RESTORE_VERIFIED`.
- O baseline operacional já tinha `/up`, backup/restore rehearsal, storage persistente, scheduler `schedule:work` e outbox `financial:drain-order-paid-outbox` a cada minuto.
- OPS-04 adiciona checks e automação; não altera Assessment, certificate, gateway ou produto funcional.

## 2. Readiness

Foi consolidado `/readiness`, separado de `/up`:

- `/up`: liveness Laravel, sem promessa de dependências.
- `/readiness`: resposta JSON `ready`/`not_ready`, HTTP `200`/`503`, verificando app, DB, storage write/read/delete, manifest/schema de migrations e outbox.
- fila só entra como MUST quando `OPS_QUEUE_REQUIRED=true`.
- O script `scripts/ops/ops04-readiness.sh` acrescenta scheduler running + `schedule:list`, worker condicional e espaço livre do volume.
- `OPS_STORAGE_MIN_FREE_BYTES=1073741824` (1 GiB) é o mínimo operacional documentado para o piloto pequeno; deve ser revisado pelo owner junto com o volume real.
- Janela de backup: RPO proposto de 24h + 2h de tolerância operacional = `93600s`.

**Readiness verdict:** `READINESS_MINIMUM_READY` por teste positivo/negativo e contrato versionado; runtime do ambiente real ainda não confirmado.

## 3. Error Visibility

`RequestTelemetry` global registra evento estruturado `http.request` com timestamp do logger, route name/path sem query, status, latência, request id, tenant id e user id numérico. Exceção não tratada registra `exception.unhandled` em nível crítico e é relançada para o handler.

`bootstrap/app.php` adiciona contexto seguro às exceções e o header `X-Request-ID` também nas respostas renderizadas pelo handler. Nenhum payload, Authorization header, senha, token ou stack é colocado na resposta.

`scripts/ops/ops04-error-scan.sh` conta 5xx e exceptions críticas nos logs JSON do app/Compose; threshold simples padrão: 5 ocorrências na janela configurada (`15m`).

**Error visibility status:** `MINIMUM_IMPLEMENTED`; canal de log e execução recorrente ainda dependem do host operacional.

## 4. Logging

- Canal produtivo continua `stderr`, agora com `Monolog\\Formatter\\JsonFormatter` e `LOG_LEVEL=info` no exemplo.
- Contexto permitido: `request_id`, `tenant_id`, `user_id`, route, status e `latency_ms`.
- Não é registrado: `Authorization`, password, `APP_KEY`, DB password, invite/reset token, payload PII ou URL assinada completa.
- O inventário LGPD não recebeu novo campo PII.
- Logs de Caddy/scheduler/MySQL continuam acessíveis pelo runtime Compose; o error scan pode consumir arquivo ou `docker compose logs`.

**Log redaction status:** `PASS_STATIC_AND_TESTED`; PII invariant passou e a inspeção runtime do host real continua pendente.

## 5. Monitoring Signals

Sinais mínimos versionados:

| Sinal | Mecanismo | Estado |
|---|---|---|
| app/liveness | `/up` | implementado |
| readiness | `/readiness` + `ops04-readiness.sh` | implementado/testado |
| DB | readiness DB + Compose health | implementado |
| storage | canário write/read/delete + `df` | implementado/testado |
| migration mismatch | `ops:migrate --check-only` | implementado/testado |
| scheduler | processo + `schedule:list` | implementado; runtime rehearsal herdado do OPS-03 |
| queue | worker somente com `OPS_QUEUE_REQUIRED=true` | condicional |
| HTTP 5xx/latência | JSON `http.request` + error scan | implementado |
| backup age/status | manifest PASS e payloads | implementado/testado |
| outbox/failed jobs | `financial:outbox-health` | outbox implementado; fila condicional |

**Monitoring verdict:** `MONITORING_MINIMUM_READY`.

## 6. Alerting

`scripts/ops/ops04-alert.sh` é provider-neutral:

- sem webhook: emite linha estruturada em stderr e exit code para cron/systemd;
- com `OPS04_ALERT_WEBHOOK_URL`: envia payload textual sintético sem imprimir a URL;
- exige `OPS04_ALERT_OWNER`, severity e ação imediata; placeholder de owner é recusado.

Condições cobertas: app unavailable, readiness fail, DB/storage/migration failure, scheduler/worker missing, storage capacity, backup missing/failed, 5xx spike, critical exception, synthetic failure e monitoring unconfigured.

**Alerting verdict:** `ALERTING_NOT_READY`. O mecanismo e o canário com owner sintético passaram, mas nenhum owner humano real/canal real foi provisionado no workspace.

## 7. Backup Monitoring

`scripts/ops/ops04-backup-monitor.sh` seleciona o manifest mais recente por `timestamp_utc`, exige `status=PASS`, janela de 26h e os payloads DB/storage presentes. Um manifest `FAIL`, ausência, payload ausente ou receipt fora da janela emite alerta crítico e exit code 1.

Evidência:

- OPS-03: manifest PASS e backup FAIL com DB down já comprovados.
- Monitor OPS-04: PASS contra `/tmp/ead2026-ops03-backups-ops03b/20260909T133721Z-14594/manifest.txt`, idade observada `7650s`, janela `93600s`.
- Canário de backup stale/missing: exit `1` + `backup_missing_or_failed` emitido.

**Backup alert status:** `PASS_FOR_REHEARSAL`; destino remoto, retenção e execução recorrente do host real são blockers externos.

## 8. Synthetic Check

`scripts/ops/ops04-synthetic.sh` executa `/up`, `/readiness` e depois `php artisan e2e:run ops04/synthetic-pilot --base=http://localhost`. O alias usa a jornada comercial E2E já validada e mantém o guard do runner.

O runner exige ambiente `testing|e2e`; o ambiente E2E disponível nesta sessão reportou `APP_ENV=local` e `APP_DEBUG=true`, por isso a execução não foi promovida artificialmente como válida.

**Synthetic check:** `IMPLEMENTED_NOT_RUNTIME_VERIFIED`.

## 9. Synthetic Tenant

Estratégia explícita: **efêmero por execução**. O runner cria tenant/users/tokens descartáveis, executa a jornada, confere side effects e faz teardown obrigatório; `--keep` só existe para diagnóstico controlado.

A jornada reutilizada cobre MZRT provisioning/entitlement, Admin, Instructor, Student, course draft→published, module, lesson, media, material, enrollment via cash/outbox, My Courses, lesson/content, download, progress, instructor roster/progress e isolamento entre tenants/personas. Não promete Assessment nem certificate.

**Synthetic pilot verdict:** `SYNTHETIC_PILOT_FAILED` neste checkpoint operacional, precisamente porque o runtime dedicado atual não está configurado como `e2e|rehearsal`; a implementação não mascara essa falha.

## 10. Negative Canaries

| Falha | Evidência |
|---|---|
| app parada | probe HTTP falha e alerta `app_unavailable`; execução real no host final pendente |
| DB parada | Feature readiness: `503`, `checks.db=fail`; OPS-03 também provou readiness DB down |
| readiness failing | `503` do endpoint/script; synthetic aborta antes de mutar |
| storage missing/unavailable | Feature: disk inexistente → `503`; OPS-03 provou volume ausente |
| migration mismatch | Feature: manifest ausente → `503`; `ops:migrate` aborta antes do trabalho de DB |
| scheduler ausente | script exige serviço running + schedule; OPS-03 provou comando inexistente falhar |
| backup FAIL/missing/stale | monitor exit `1` + alerta crítico |
| synthetic login/course failure | runner retorna failure; script não converte em PASS |

Não foi provocada corrupção de dados nem destruição adicional.

## 11. Scheduler/Outbox

- Scheduler continua MUST para o piloto: `php artisan schedule:work` e drainer a cada minuto.
- Outbox possui comando `financial:outbox-health`; pending stale ou qualquer falha registrada retorna exit `1`.
- `OPS04_QUEUE_REQUIRED=false`: worker não é exigido neste release.
- Falha de scheduler/outbox é detectável por readiness externo, schedule list e error scan/alert.

**Scheduler/outbox status:** `SCHEDULER_CONTRACT_READY; OUTBOX_MONITOR_READY; RUNTIME_HOST_CHECK_PENDING`.

## 12. SMTP/Worker Decision

Decisão fechada: **o primeiro piloto NÃO promete reset de senha por e-mail**. Worker permanece opcional e só vira MUST se reset/e-mail assíncrono for incluído no release. O scheduler permanece MUST por causa do outbox. SMTP real não é usado como justificativa para liberar o piloto.

## 13. Domain/TLS

O Caddy mantém redirect HTTP→HTTPS, TLS ACME em produção e `internal` apenas em rehearsal. O exemplo usa `pilot.example.com`; DNS, hostname real, certificado válido e teste externo ainda não foram fornecidos.

**Domain/TLS status:** `EXTERNAL_BLOCKER`.

## 14. Secrets

O contrato versionado valida APP_KEY não-placeholder, credencial runtime separada da migration/bootstrap, debug off, DB não-testing/e2e, mail contract e owner de alerta. Nenhum valor secreto foi adicionado ao Git ou ao relatório.

O `.env.production.example` ainda contém placeholders deliberados, inclusive `OPS04_ALERT_OWNER`.

**Secrets status:** `EXTERNAL_BLOCKER` até provisionamento seguro e validação no host final.

## 15. Storage Capacity

Readiness prova storage lógico; o script externo prova volume montado e `df` acima de 1 GiB. OPS-03 já comprovou persistência DB+storage no rehearsal. Capacity/retention/offsite do host real não foram confirmados.

**Storage capacity status:** `CHECK_IMPLEMENTED_REHEARSAL_PASS; REAL_HOST_PENDING`.

## 16. Incident Runbook

| Incidente | Detectar | Conter | Diagnosticar | Recuperar | Validar |
|---|---|---|---|---|---|
| app down | `/up`, proxy, alert | retirar tráfego/restart app | logs por request id/commit | restart ou restore | `/up` + readiness + synthetic |
| DB down | readiness/Compose health | bloquear escrita | health, conectividade e credencial runtime | recuperar DB/restore | readiness + smoke |
| storage failure | canário write/read/df | bloquear upload/download | volume, mount, espaço/permissão | remount/expand/restore storage | canário + material sintético |
| backup failure | manifest FAIL/missing/stale | não promover deploy | receipt, dump e destino | corrigir backup e repetir | manifest PASS + checksum |
| 5xx spike | error scan threshold | conter rota/tráfego | route/status/latency/request id | corrigir/revert/restore | error scan zero + smoke |
| migration mismatch | readiness/`ops:migrate` | não abrir tráfego | manifest vs discovered/applied | usar release compatível/restore | readiness + migration check |
| scheduler/outbox failure | schedule/process/outbox health | impedir acúmulo comercial | scheduler logs/outbox age/failure | restart/drain/retry | outbox PASS + side effect |

Rollback de primeiro deploy continua via restore, conforme OPS-03; não executar rollback destrutivo de migration automaticamente.

## 17. Human Acceptance

Checklist pendente de assinatura do owner:

- [ ] domínio e DNS
- [ ] TLS/certificado/redirect
- [ ] secrets e rotação
- [ ] RPO 24h / RTO 4h úteis
- [ ] backup e restore
- [ ] rollback-via-restore
- [ ] alert owner humano e support contact
- [ ] curso sem Assessment/certificate
- [ ] manual billing/onboarding

**Human acceptance status:** `PENDING`.

## 18. Pilot Constraints

- Student Assessment: `NOT RELEASED`.
- Certificate: `NOT PROMISED`.
- Paid external automation: `DEFERRED`.
- Manual onboarding/billing: permitido.
- Primeiro release: rollback via restore.
- Worker: opcional, salvo mudança explícita de promessa de reset por e-mail.

## 19. Tests

- `docker exec ead2026-laravel.test-1 php artisan test --compact tests/Feature/Ops/ReadinessTest.php` — `5 passed (25 assertions)` before the final focused rerun.
- `docker exec ead2026-laravel.test-1 php artisan test --compact tests/Feature/Ops/ReadinessTest.php --filter='fails readiness when the migration manifest is missing'` — `1 passed (4 assertions)`.
- `docker exec ead2026-laravel.test-1 php artisan test --compact tests/Architecture/ProductionInfrastructureContractTest.php` — `6 passed (74 assertions)`.
- `docker exec ead2026-laravel.test-1 php artisan test --compact tests/Architecture/PiiAuditTest.php tests/Architecture/ErrorEnvelopeShapeTest.php tests/Architecture/ProductionInfrastructureContractTest.php` — `13 passed (125 assertions)`.
- `bash -n scripts/ops/ops04-*.sh` — PASS.
- `git diff --check` — PASS.
- Alert owner ausente — exit `2`.
- Alert stdout/exit-code com owner sintético — PASS.
- Backup PASS receipt — PASS.
- Backup stale/missing — exit `1` + alerta.
- Error scan de log sem 5xx/crítico — PASS (`0/0`).
- Docker runtime externo para `/readiness` e synthetic — não confirmado; rede/socket sandbox e ambiente E2E `local` impediram uma prova válida.

- `docker exec ead2026-laravel.test-1 vendor/bin/phpstan analyse --memory-limit=1G` — PASS, `No errors`.
- `docker exec ead2026-laravel.test-1 php artisan test --compact --testsuite=Architecture` — `43 passed (1412 assertions)`.
- `bash scripts/ai/verify-changes.sh <<< '{"stop_hook_active":false}'` — invariantes do diff verdes.

## 20. External Blockers

- domínio/DNS e TLS ACME reais;
- secrets reais (APP_KEY, DB runtime/migration, owner/webhook se usado);
- owner humano e support contact;
- destino/retention/offsite do backup;
- host operacional com logs acessíveis/cron ou systemd;
- ambiente E2E/rehearsal correto para executar `ops04/synthetic-pilot` sem `APP_ENV=local`;
- decisão/aceite humano de RPO/RTO e constraints do piloto.

## 21. Operations Verdict

| Área | Veredito |
|---|---|
| Monitoring | `MONITORING_MINIMUM_READY` |
| Alerting | `ALERTING_NOT_READY` |
| Readiness | `READINESS_MINIMUM_READY` |
| Synthetic | `SYNTHETIC_PILOT_FAILED` |
| Error visibility | `MINIMUM_IMPLEMENTED` |
| Logging redaction | `PASS_STATIC_AND_TESTED` |
| Backup alerts | `PASS_FOR_REHEARSAL` |
| Scheduler/outbox | `SCHEDULER_CONTRACT_READY; OUTBOX_MONITOR_READY; RUNTIME_HOST_CHECK_PENDING` |
| Operations | `OPERATIONS_NEAR_READY` |

## 22. Paid Pilot Verdict

`PAID_PILOT_NOT_READY`.

O mínimo técnico está versionado e testado, mas o gate não abre enquanto alert owner/canal, synthetic runtime dedicado, domínio/TLS e secrets reais não forem provisionados e aceitos por humano. Nenhum blocker funcional novo foi criado; Assessment/certificate continuam fora do release.

## Commits

Nenhum commit OPS-04 foi criado nesta sessão. As alterações permanecem no working tree da branch `main`; não houve push nem deploy real.
