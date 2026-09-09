# Activation receipt

O receipt é machine-readable, não é log de execução e não contém secrets, tokens, senhas, URLs de
webhook, PII ou conteúdo completo de env. O gerador é `scripts/ops/activate-paid-pilot.sh`.

Campos obrigatórios:

```json
{
  "timestamp_utc": "2026-09-09T12:00:00Z",
  "rc_sha": "<release-sha>",
  "migration_manifest_sha256": "<sha256>",
  "environment_identity": "pilot.example.com",
  "environment": "production",
  "readiness": "PASS",
  "backup_reference": "<backup-id>",
  "restore_reference": "<restore-reference>",
  "remote_backup": "PASS",
  "tls": "PASS",
  "synthetic": "PASS",
  "alert_channel_configured": "yes",
  "scheduler": "PASS",
  "support_owner_id": "<non-sensitive-id>",
  "rpo_rto_accepted": "yes",
  "human_approval_reference": "<approval-ref>",
  "final_verdict": "PASS"
}
```

Valores permitidos para resultados são `PASS`, `FAIL`, `PENDING` ou `NOT_APPLICABLE`; `final_verdict`
só pode ser `PASS` quando o checklist estiver selado. Guardar o receipt em storage operacional
privado, fora do repositório público.
