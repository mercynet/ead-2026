# COMMERCIAL V0.1 — OPS-04 Receipt Reconciliado

Data: 2026-09-09
Escopo: hardening operacional provider-neutral; nenhum deploy real e nenhuma expansão funcional.

Este receipt substitui o checkpoint anterior deste arquivo. A implementação e o veredito selados
estão em [COMMERCIAL-V0.1-PAID-PILOT-READINESS-GOAL-2026-09-09.md](COMMERCIAL-V0.1-PAID-PILOT-READINESS-GOAL-2026-09-09.md).

## Resultado

- `MONITORING_MINIMUM_READY`.
- `ALERTING_MINIMUM_READY`: provider-neutral, preserva o incidente original mesmo sem canal ou
  quando a entrega falha; owner/canal reais permanecem externos.
- `READINESS_MINIMUM_READY`: `/up` é liveness; `/readiness` é JSON 200/503, barato, determinístico
  e não mutante, com app, DB, storage, manifest/schema, outbox e fila condicional.
- `SYNTHETIC_PILOT_VERIFIED`: stack E2E descartável dedicada, `10 passed, 0 failed` em provisioning
  MZRT e `28 passed, 0 failed` no journey comercial, ambos via HTTP real e teardown obrigatório.
- `ENGINEERING_READY_FOR_PAID_PILOT`; ativação externa ainda não liberada.

## Alerting e canários

`scripts/ops/ops04-alert.sh` aceita webhook genérico, e-mail somente quando configurado, ou
stdout/exit code para scheduler externo. Cada resultado contém sinal, severidade, timestamp,
contexto seguro, ação, deduplicação, canal, status de entrega e exit code. Nenhum token, password,
APP_KEY, Authorization, PII ou URL assinada é emitido.

Sinais cobertos: app/readiness, DB, storage, migrations, scheduler/worker, outbox, capacidade,
backup, 5xx/exceções e synthetic. Foram comprovados no rehearsal: provider ausente, webhook
indisponível/destino inválido, timeout, deduplicação, backup stale/missing e falha de synthetic.
O relatório diferencia `problem_detected=true` de `delivery_status=failed`.

## Backup, domínio e secrets

- `ops04-backup-monitor.sh` exige manifest PASS, idade dentro de `93600s` (RPO 24h + grace de 2h),
  DB/storage presentes e checksums SHA-256 correspondentes.
- `ops04-remote-backup.sh` define adapter, destino, credencial externa, checksum pós-transferência,
  retention e receipt; mock adapter passou, provider real não foi inventado.
- `ops04-domain-tls.sh` passou no modo estrutural; DNS, host, redirect, certificado real, hostname,
  expiração e trusted proxy aguardam ambiente real.
- `validate-production-env.sh` falha fechado para placeholders, APP_KEY inválida, secrets
  default/dev, debug, DB de teste/e2e, usuário runtime root, credenciais compartilhadas e canais
  inseguros. Nenhum secret foi armazenado.

## Synthetic strategy e escopo

O tenant é efêmero por execução, criado pelo runner E2E e removido em teardown obrigatório. A
execução usa somente DB/stack marcados `e2e`, não cobra, não aciona PSP e não usa Student
Assessment/certificates. O journey comercial cobre provisioning MZRT, Admin, Instructor, Student,
Course/Module/Lesson/content/media/material, matrícula manual/cash, outbox, consumo, progresso,
roster, isolamento cross-persona/cross-tenant e rejeição do gate de Assessment/certificate.

Worker continua opcional: reset por e-mail não é promessa v0.1. Scheduler e outbox são MUST e
monitorados.

## Regressão

- Alert unit: `4 passed (15 assertions)`; error-scan unit: `2 passed (8 assertions)`.
- Readiness Feature: `5 passed (29 assertions)`.
- Infrastructure contract: `6 passed (100 assertions)`; Architecture: `43 passed (1438 assertions)`.
- PHPStan e Pint: PASS; `bash -n`, `git diff --check` e `scripts/ai/verify-changes.sh`: PASS.
- Backup PASS/stale/missing, remote mock e domain structural: PASS.
- Synthetic MZRT HTTP: `10 passed, 0 failed`; synthetic comercial HTTP: `28 passed, 0 failed`.

## Pendências que não são OPS-04 internos

Host/IP, DNS/domínio/TLS, secrets reais e privilégios DB, owner/canal de alertas, adapter/destino/
credencial de backup remoto, suporte/cron-systemd e aceite humano de RPO ≤24h/RTO ≤4h úteis.
Primeiro deploy não possui N-1: recovery é restore do baseline/pre-release, seguida de RC conhecida,
readiness e synthetic.

Veredito pago permanece `PAID_PILOT_NEAR_READY` até essas dependências externas e o aceite humano
serem comprovados.
