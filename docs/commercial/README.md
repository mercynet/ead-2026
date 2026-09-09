# Paid Pilot Launch Package — Commercial v0.1

Este diretório é a porta de entrada única para vender e ativar o primeiro cliente. O pacote não
substitui as specs de domínio nem os relatórios de evidência; ele transforma a evidência em um
processo operacional e comercial curto.

## Ordem de uso

1. [Golden Path](GOLDEN-PATH-V0.1.md) — entendimento rápido do produto e do piloto.
2. [Checklist canônico de ativação](00-ACTIVATION-CHECKLIST.md) — única fonte para saber o que falta.
3. [Orquestração e runbook de ativação](01-ACTIVATION-RUNBOOK.md) — staging/host, dry-run e recuperação.
4. [Intake do tenant](02-TENANT-INTAKE.md) e [onboarding](03-TENANT-ONBOARDING-RUNBOOK.md).
5. [Demo](04-DEMO-SCRIPT.md), [claims](05-COMMERCIAL-CLAIMS.md) e [oferta](06-PILOT-OFFER.md).
6. [ICP](09-ICP.md), [discovery](10-DISCOVERY-INTERVIEW.md) e [qualificação](11-QUALIFICATION-SCORE.md).
7. [Support](12-SUPPORT-RUNBOOK.md), [primeira semana](13-FIRST-WEEK-CHECKLIST.md) e [sucesso](14-SUCCESS-CRITERIA.md).
8. [Saída](15-EXIT-INTERVIEW.md), [feedback](16-FEEDBACK-INTAKE.md) e [gate pós-piloto](17-POST-PILOT-GATE.md).
9. [FAQ de segurança](18-SECURITY-PRIVACY-FAQ.md), [release notes](19-RELEASE-NOTES.md) e [revisão adversarial](21-ADVERSARIAL-REVIEW.md).

## Índice de verdade

| Classe | Fonte |
|---|---|
| Current Truth | [checklist](00-ACTIVATION-CHECKLIST.md), [Golden Path](GOLDEN-PATH-V0.1.md), `docs/STATE.md` |
| Product | `docs/specs/20-catalog-learning/`, `docs/specs/10-core-identity/`, `docs/specs/40-financial/` |
| Operations | `scripts/ops/`, [runbook](01-ACTIVATION-RUNBOOK.md), [receipt](07-ACTIVATION-RECEIPT.md) |
| Commercial | claims, offer, pricing, ICP, discovery e support deste diretório |
| Evidence Archive | `docs/reports/COMMERCIAL-V0.1-*`, `docs/reports/GLOBAL-ADVERSARIAL-*` |

## Estado de evidência

O pacote parte de `ENGINEERING_READY_FOR_PAID_PILOT` e de `PAID_PILOT_NEAR_READY`. Isso não é
ativação: host, domínio/TLS, secrets, usuários de banco, backup remoto, owners, scheduler no host
e aceite humano de RPO/RTO permanecem `EXTERNAL_PENDING` ou `HUMAN_PENDING` até execução real.

O relatório operacional principal é
`docs/reports/COMMERCIAL-V0.1-PAID-PILOT-READINESS-GOAL-2026-09-09.md`. Relatórios mais antigos são
arquivo de provenance e não promovem uma capability contra uma RC nova.
