# First-week operations — poucos minutos por dia

Executar no início do dia pelo operator; registrar apenas PASS/FAIL, referência e ação.

| Dia | Checks |
|---|---|
| D0 | readiness; scheduler/outbox; backup local e remoto; synthetic; smoke Admin/Instructor/Student; support owner/canal |
| D1 | readiness; último backup/checksum/idade; alert channel; 5xx/error scan; disk; tickets e enrollments |
| D2 | readiness; remote backup receipt; scheduler/outbox; synthetic; progresso de uma amostra; tickets |
| D3 | readiness; 5xx; disk; backup/restore reference; anomalies de matrícula e progresso |
| D4 | readiness; backup remoto; alert canary se necessário; synthetic; support burden e workaround |
| D5 | readiness; scheduler/outbox; 5xx; storage; enrollment/progress anomalies; resumo do cliente |
| D6 | readiness; backup local/remoto; synthetic; tickets P1/P2; blockers do piloto |
| D7 | readiness; restore confidence; total tickets/manual tasks; progress usage; success criteria e decisão de continuar |

Comandos canônicos: `ops04-readiness.sh`, `ops04-backup-monitor.sh`, `ops04-synthetic.sh` e
`ops04-error-scan.sh`. Um FAIL em readiness, backup, alert, scheduler ou synthetic bloqueia promoção
e abre incidente; não é convertido em “observação”.
