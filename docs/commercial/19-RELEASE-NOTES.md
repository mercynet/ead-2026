# Commercial v0.1 — release notes

## Supported

- tenant dedicado com onboarding assistido;
- Admin, Instructor e Student no fluxo de course publicado;
- Course → Module → Lesson, conteúdo/material/media conforme storage;
- enrollment free/manual/cash e progress;
- API versionada `/api/v1`, autenticação, tenant isolation e Resources/envelopes;
- readiness, alerting mínimo, backup/restore, deploy/recovery e synthetic operacional.

## Manual operations

Provisioning inicial, convites, preparação de conteúdo, matrícula/coleta de cohort, billing e
confirmação cash/manual podem exigir operador. Isso é parte explícita do piloto, não automação escondida.

## Limitations / deferred

Student Assessment; certificates; PSP/checkout automático/webhooks; advanced media; analytics
avançado; marketplace/plugins; self-service completo; SLA enterprise; reset por e-mail quando
SMTP/worker não estiverem ativados.

## Recovery posture

Promotion exige backup local/remoto, checksum, restore reference, readiness, scheduler/outbox,
monitoring, TLS e synthetic. No primeiro deploy, recovery é restore de baseline + RC conhecida;
rollback de código N-1 só existe depois de uma release posterior.

## Evidence note

Os relatórios em `docs/reports/` são provenance. Cada ativação deve repetir os gates contra a RC e
host escolhidos; rehearsal não é production.
