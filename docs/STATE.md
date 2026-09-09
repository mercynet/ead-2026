# State — Sessão Atual

## Sessão

2026-09-09: auditoria adversarial do Commercial v0.1/Paid Pilot e campanha de recuperação de
confiança concluídas. O baseline e a revalidação estão em
`docs/reports/COMMERCIAL-V0.1-FULL-ADVERSARIAL-VALIDATION-2026-09-09.md`. Foram preservados os
29 claims auditados; a execução atual revalidou o código e uma qualification stack descartável,
mas não emitiu `PROVEN_CURRENT`. Verdicts continuam `LAUNCH_PACKAGE_VALID_WITH_GAPS`,
`ENGINEERING_NOT_READY`, `PAID_PILOT_NOT_READY`.

## Próximos passos (1-3)

1. Provisionar e validar independentemente host, DNS/TLS público, secrets reais, privilégios DB,
   owner/canal de alertas, destino off-host e scheduler recorrente.
2. Repetir activation execute, receipt, deploy e rollback N-1 em stack/host aprovados; não abrir
   cobrança antes de todos os gates externos e humanos.
3. Obter aceite humano de RPO/RTO, owners, política de rollback e claims/exclusões comerciais.

## Decisões abertas

Aceite humano de RPO ≤24h/RTO ≤4h úteis, owners e canal de alerta, destino/adapter de backup remoto,
host/domínio/TLS/secrets reais, política de rollback e data de promoção comercial. Student
Assessment, certificates e PSP automático continuam fora da promessa v0.1.

## Último commit

`4f5ee55ec58a24a3de0c0311c899095665c4bf10` (`main`, commit local de relatório/state). As
remediações de código estão em `f507f9124c397a06f2c0e4f77654ec78ab8eab3d` e
`4871373d86808bb585623298c3a3c573eabc4b2a`; não houve push, tag ou deploy produtivo.

## Evidência atual

- Architecture: `43 passed (1456 assertions)`.
- Ops/Assessment/Financial focados: `33 passed (245 assertions)`; confirmação manual financeira
  voltou a regression-green.
- E2E HTTP real em stack dedicada: `mzrt/tenant-lifecycle` `10/10`; `ops04/synthetic-pilot`
  `29/29`, incluindo confirmação manual, outbox/enrollment, isolamento e teardown sem resíduos.
- Qualification stack atual: `/readiness` `status=ready`; app/db/storage/manifest/outbox `pass`;
  migration `73/73`; scheduler requerido e running.
- OPS-03 descartável atual: backup assinado `PASS`, restore `PASS` e readiness `PASS`; marcador
  criado depois do backup foi removido pelo restore. Nenhum volume produtivo foi tocado.
- Proveniência: imagens app/web foram construídas do último commit de código e carregam revision +
  SHA do manifest; activation dry-run passou com `mutation=none` e sem receipt.
- `bash -n scripts/ops/*.sh`, Pint e `git diff --check` passam no delta atual. A lista detalhada
  de F-01–F-13 e os limites externos estão no relatório pós-remediação.

## CONTEXT CHECKPOINT

- context: alto, estimado; campanha e evidências atuais foram seladas no report.
- state: `docs/STATE.md` atualizado após a revalidação e os testes finais.
- recommendation: `waiting_for_user`.
- reason: blockers externos/humanos permanecem e activation execute/receipt, TLS público e rollback
  N-1 ainda não foram provados; não abrir cobrança.
