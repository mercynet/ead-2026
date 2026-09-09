# Adversarial consistency review

Review obrigatório antes de comunicar o pacote a um cliente.

| Pergunta | Resultado esperado | Status atual |
|---|---|---|
| Alguma claim promete capability não implementada? | Claims apontam para matrix e exclusions | PASS — Assessment/certificates/PSP estão excluídos |
| Sales contradiz API/área? | demo usa Admin/Instructor/Student canônicos e não legacy sem guard | PASS com atenção a legacy; não demonstrar endpoints legacy |
| Procedimento depende de ferramenta inexistente? | cada comando existe em `scripts/ops/` ou fica external pending | PASS — confirmar command availability no host |
| Secret entra no Git/receipt/chat? | placeholders fora do Git; receipt só non-sensitive | PASS — revisar env path e storage do receipt |
| Manual operation está escondida? | offer/release notes/claims explicitam operator e billing manual | PASS |
| Rehearsal foi confundido com production? | status e checklist separam PROVEN de EXTERNAL_PENDING | PASS |
| Synthetic representa o piloto? | cobre personas, content, enrollment, checkout cash, progress, isolation e cleanup | PASS qualificado — repetir na RC/host; não usa Assessment/certificates |
| Criamos burocracia sem reduzir risco? | daily checks poucos minutos, feedback simples, um checklist único | PASS |
| Preço foi inventado? | placeholders e hipótese de modelo | PASS |
| Compliance foi prometido? | FAQ marca `LEGAL_REVIEW_REQUIRED` | PASS |

## Findings corrigidos/assumidos

- Relatórios históricos contraditórios não são apagados; o índice aponta Current Truth e Evidence Archive.
- `PAID_PILOT_NEAR_READY` não é `FIRST CUSTOMER ACCEPTED`; a ativação externa e o aceite humano continuam bloqueios legítimos.
- O orchestrator não transforma ausência de host, remote backup, owner ou RPO/RTO em sucesso.
- O receipt só é escrito depois de todos os gates e dos identificadores humanos obrigatórios.

Reabrir esta revisão quando uma claim, capability, provider, modelo de preço ou requisito jurídico mudar.
