# Evidence index

## Current Truth

- `docs/STATE.md` — handoff atual e pendências externas.
- `docs/reports/COMMERCIAL-V0.1-PAID-PILOT-READINESS-GOAL-2026-09-09.md` — baseline OPS/readiness/synthetic.
- este diretório — processo comercial/operacional e limites.

## Product

- `docs/specs/README.md` — mapa das specs.
- `docs/specs/20-catalog-learning/` — course/content/enrollment/progress.
- `docs/specs/10-core-identity/` — users/auth/tenant.
- `docs/specs/40-financial/` — order/payment/cash/manual e limites.
- `docs/specs/00-architecture/` — áreas, segurança, testes e invariantes.

## Operations

- `scripts/ops/validate-production-env.sh`
- `scripts/ops/ops04-readiness.sh`
- `scripts/ops/ops04-backup-monitor.sh`
- `scripts/ops/ops04-remote-backup.sh`
- `scripts/ops/ops04-domain-tls.sh`
- `scripts/ops/ops04-synthetic.sh`
- `scripts/ops/activate-paid-pilot.sh`

## Commercial

- [claims](05-COMMERCIAL-CLAIMS.md), [offer](06-PILOT-OFFER.md), [pricing](08-PRICING-FRAMEWORK.md),
  [ICP](09-ICP.md), [discovery](10-DISCOVERY-INTERVIEW.md), [support](12-SUPPORT-RUNBOOK.md).

## Evidence archive

Relatórios `docs/reports/COMMERCIAL-V0.1-*`, `GLOBAL-ADVERSARIAL-*`, closures de persona e auditorias
anteriores preservam contexto e provenance. Se contraditórios, usar o relatório mais recente,
confirmar no código e repetir o gate; não apagar histórico nem tratá-lo como prova da RC atual.
