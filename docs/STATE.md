# State — Sessão Atual

## Sessão

2026-09-09: OPS-03B fechou a auditoria de N-1, build reproducibility, schema compatibility,
discovery de migrations e rollback. Não existe N-1 operacional válido anterior à primeira RC;
`b045025` é `NOT_DEPLOYABLE_HISTORICALLY`. Relatório:
`docs/reports/COMMERCIAL-V0.1-OPS03B-N1-MIGRATION-ROLLBACK-2026-09-09.md`.

## Próximos passos (1-3)

1. Usar restore de baseline pré-deploy/synthetic para recovery do primeiro deploy.
2. A partir da próxima release, validar a RC anterior como N-1 e exercitar code rollback quando
   o schema permitir.
3. Manter Paid Pilot como `NOT_READY` até os gates restantes de operações e aceitação estarem
   fechados.

## Decisões abertas

O build exato de `b045025` falha no estágio vendor porque o Dockerfile histórico instala `exif`
somente no runtime. O schema entre `b045025` e a RC é compatível, mas não há artefato N-1
reproduzível. O caminho comprovado é `ROLLBACK_VIA_RESTORE_VERIFIED`; migration rehearsal é
`NOT_APPLICABLE_FIRST_RELEASE`. Monitoring/alerting, ambiente real, domínio/TLS/segredos e
aceitação do piloto permanecem fora desta task e abertos.

## Último commit

Implementação: `4879457 feat(ops): harden migration discovery and rollback gates`.
Checkpoint documental: `a8bc317 docs(ops): seal OPS-03B migration rollback evidence`. Não houve
push nem tag.

## Evidência atual

- Build exato de `4e69cbc` e `b045025`: falha `ext-exif`; build exato da RC `655c939`: PASS.
- Imagem final gerou manifest `expected=73 discovered=73`; deploy canônico e readiness passaram
  com `applied_after=73`.
- Backup `20260909T133721Z-14594`: `PASS`; deploy/readiness final e primeiro `/up`: HTTP `200`.
- Testes tooling: `4 passed (11 assertions)`; infraestrutura + tooling: `9 passed (58 assertions)`.
- Checkout comercial: `16 passed (183 assertions)`; S02: `2 passed (8 assertions)`.
- `git diff --check` e `bash -n scripts/ops/*.sh`: PASS; não houve alteração funcional de produto.

## CONTEXT CHECKPOINT

- context: alto, com OPS-03B consolidado no relatório.
- state: `docs/STATE.md` atualizado com fatos comprovados.
- recommendation: `clear`.
- reason: a task foi selada; retomar somente para os gates restantes ou para validar a próxima RC
  como N-1.
