# Activation runbook

## Pré-condições

- RC aprovada e SHA anotada.
- `.env.production` existe fora do Git e passa `scripts/ops/validate-production-env.sh`.
- DB de produção não contém `testing`/`e2e`; runtime, migration e bootstrap usam usuários distintos.
- backup local PASS e destino remoto/adapter provisionados.
- owner de suporte, owner de alerta, canal, domínio/TLS e aceite RPO/RTO identificados.

## Comando único

Dry-run é o padrão e não muta nada:

```bash
scripts/ops/activate-paid-pilot.sh --dry-run --env-file /secure/ead/.env.production
```

Execução exige confirmação deliberada e escreve receipt somente depois de todos os gates:

```bash
PAID_PILOT_ALLOW_MUTATION=true \
PAID_PILOT_ACTIVATION_CONFIRM=I_UNDERSTAND \
PAID_PILOT_SUPPORT_OWNER_ID=owner-123 \
PAID_PILOT_RPO_RTO_ACCEPTED=yes \
PAID_PILOT_HUMAN_APPROVAL_REF=approval-123 \
scripts/ops/activate-paid-pilot.sh --execute \
  --env-file /secure/ead/.env.production \
  --receipt /secure/ead/receipts/paid-pilot.json
```

O exemplo não contém secrets. O script valida env, verifica provenance, exige backup local e
remote, calcula o hash do manifest de migrations, executa migration, sobe os serviços, valida
readiness, observabilidade, TLS live e synthetic. Falha em qualquer etapa encerra com exit 1.

## Dry-run

Mostra gates, comandos principais e inputs externos faltantes. Se houver um env file, valida sua
estrutura sem executar mutação; se não houver, informa `EXTERNAL_PENDING` e retorna 0. Não faz
`source`, não sobe containers, não roda migrations, não chama remote backup e não cria receipt.

## Recovery

1. interromper tráfego/abertura comercial;
2. preservar receipts, request IDs e logs safe;
3. se falha for backup, corrigir cópia/checksum e bloquear promoção;
4. no primeiro deploy, não fingir rollback de código: restaurar baseline/pre-deploy backup;
5. subir a RC conhecida, migrar somente pelo comando canônico;
6. repetir readiness, scheduler/outbox, backup monitor e synthetic;
7. atualizar o checklist e registrar a decisão.

O script deliberadamente não esconde ações perigosas: `--execute` é explícito, a confirmação é
dupla e os comandos de Compose aparecem no dry-run.
