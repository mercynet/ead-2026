# Release Candidate → First Customer Accepted

Checklist canônico. Uma linha só pode ser marcada como `PROVEN` com evidência atual da RC/host.
`PROVEN` em rehearsal não significa production. Itens externos ficam pendentes sem fabricar valores.

| # | Gate | Owner | Evidence | Command/process | PASS / FAIL | Rollback/recovery | State |
|---:|---|---|---|---|---|---|---|
| 1 | RC aprovada | Release owner | SHA da RC + aprovação | conferir `APP_BUILD_SHA`, `git cat-file` | SHA existe e approval ref existe / qualquer divergência | voltar à RC conhecida | HUMAN_PENDING |
| 2 | Host e env | Platform operator | host, env identity, `APP_ENV` | preencher env fora do Git; `validate-production-env.sh` | host e env production/rehearsal válidos / placeholder, debug ou DB e2e | não promover | EXTERNAL_PENDING |
| 3 | Secrets | Platform operator | somente nomes de keys no receipt | validator + cofre do host | APP_KEY, DB users e credenciais reais / key ausente ou fraca | remover env e reprovisionar | EXTERNAL_PENDING |
| 4 | DB users | DBA/platform | usuários separados | verificar privilégios sem expor senha | runtime ≠ migration ≠ bootstrap, runtime não-root / compartilhado ou root | restaurar baseline | EXTERNAL_PENDING |
| 5 | Storage | Platform operator | volume/disco e persistência | `ops04-readiness.sh` | storage privado, legível e gravável / indisponível | reanexar volume, não apagar | PROVEN |
| 6 | DNS | Domain owner | resolução do domínio | `ops04-domain-tls.sh live` | aponta para host correto / não resolve | manter URL anterior ou não abrir piloto | EXTERNAL_PENDING |
| 7 | TLS | Domain owner | hostname, cadeia, expiração, headers | `ops04-domain-tls.sh live` | redirect + HTTPS + headers / qualquer falha | renovar/corrigir proxy | EXTERNAL_PENDING |
| 8 | Migrations | Platform operator | manifest hash + applied state | `ops:migrate --manifest-only`, `ops04-readiness.sh` | manifest aplicado e consistente / mismatch | restore pre-deploy, RC conhecida | PROVEN |
| 9 | Backup local | Platform operator | manifest PASS, checksums, timestamp | `ops04-backup-monitor.sh` | dentro de RPO/grace e íntegro / missing, stale, FAIL | bloquear promoção e refazer | PROVEN |
| 10 | Backup remoto | Backup owner | remote transfer receipt/reference | `ops04-remote-backup.sh <dir>` | DB+storage, checksum e retenção confirmados / adapter/destino ausente | manter piloto fechado, repetir cópia | EXTERNAL_PENDING |
| 11 | Restore reference | Platform operator | backup ID + restore rehearsal | `ops03-restore.sh <backup>` em ambiente descartável | restore e smoke passam / payload/checksum falha | não cobrar; restaurar baseline | PROVEN |
| 12 | Deploy | Platform operator | deploy receipt e SHA | `activate-paid-pilot.sh --execute` | serviços iniciam na RC / serviço não saudável | stop traffic → restore → RC conhecida | EXTERNAL_PENDING |
| 13 | Readiness | Platform operator | JSON `status=ready` | `ops04-readiness.sh` | app/db/storage/migration/outbox/queue pass | corrigir dependência antes de abrir | PROVEN |
| 14 | Alerts | Alert owner | canal e owner confirmados | `ops04-alert.sh` + canário de entrega | incidente preservado e canal entregue / unconfigured/failed | operar fechado e corrigir canal | EXTERNAL_PENDING |
| 15 | Scheduler/outbox | Platform operator | process + schedule contract | readiness + `schedule:list` | scheduler ativo e outbox drena / ausente/backlog | reiniciar e conferir efeitos | EXTERNAL_PENDING |
| 16 | Synthetic | QA/platform | receipt HTTP real, tenant efêmero | `ops04-synthetic.sh` | pelo menos 1 passou, 0 falhas, cleanup / qualquer falha | teardown e investigar | PROVEN |
| 17 | First tenant | Platform operator | intake aprovado + tenant ID | [intake](02-TENANT-INTAKE.md) + API MZRT/Admin | dados completos e escopo conferido / campo obrigatório ausente | rollback de provisioning parcial | HUMAN_PENDING |
| 18 | Commercial smoke | Support owner | smoke Admin→Instructor→Student | [onboarding](03-TENANT-ONBOARDING-RUNBOOK.md) | login, course, lesson, enrollment, consumo, progress / falha | não abrir pilot; recovery | HUMAN_PENDING |
| 19 | Acceptance | Customer owner + platform | aceite e referência | assinatura/registro fora do código | escopo, limites, support e RPO/RTO aceitos / qualquer objeção aberta | cancelar ou adiar abertura | HUMAN_PENDING |
| 20 | First customer accepted | Account owner | receipt final | preencher [receipt](07-ACTIVATION-RECEIPT.md) | todos os MUST pass e aceite registrado / qualquer pending material | manter `NOT_ACCEPTED` | HUMAN_PENDING |

### Estados permitidos

`PROVEN` · `EXTERNAL_PENDING` · `HUMAN_PENDING` · `NOT_APPLICABLE` · `FAILED`.

O piloto só muda para `FIRST CUSTOMER ACCEPTED` quando não houver `FAILED`, nem pending material
em host/domain/TLS/secrets/backup/alerts/support, e o aceite humano estiver registrado.
