# Golden Path — Commercial v0.1

## O produto

Uma API-first, multi-tenant para organizações que precisam operar cursos para seus clientes,
parceiros ou equipes, com onboarding assistido e tenant dedicado.

## Para quem

Principalmente consultorias de treinamento e empresas que treinam clientes/parceiros; secundariamente
franquias/redes e academias profissionais pequenas. Não é, ainda, um LMS enterprise self-service.

## Jornada suportada

Operador provisiona tenant → Admin recebe convite → Admin/Instructor prepara e publica Course,
Module, Lesson e material/media → operador/Admin matricula estudantes via fluxo suportado → Student
entra, consome conteúdo e registra progress → Instructor acompanha roster/progresso → support opera
incidentes e o piloto mede valor.

## Ativação

Começar no [checklist](00-ACTIVATION-CHECKLIST.md), executar [dry-run/orchestrator](01-ACTIVATION-RUNBOOK.md),
provar env, RC, backup, migrations, readiness, scheduler/outbox, TLS, synthetic e aceite. Não abrir
comercialmente enquanto pending material permanecer.

## Operação

Onboarding e billing são assistidos. O operator mantém saúde, backup/restore e recovery; o cliente
mantém conteúdo, usuários e contatos; support triageia. O primeiro piloto não esconde trabalho manual.

## Restrições

Não vender Student Assessment, certificates, PSP automático, advanced media/analytics, marketplace,
SLA enterprise ou compliance jurídico não revisado.

## Evidência

Baseline: `docs/reports/COMMERCIAL-V0.1-PAID-PILOT-READINESS-GOAL-2026-09-09.md`; implementation gates
em `scripts/ops/`; cada RC precisa de novo receipt. `RUNTIME_VERIFIED` é específico da execução, não
herdado de texto histórico.

## Primeiro piloto

Usar intake → onboarding → demo → primeira semana → exit interview → gate `DOUBLE DOWN`, `ADJUST ICP`,
`ADD CAPABILITY`, `HOLD` ou `STOP/REPOSITION`. Preço e RPO/RTO final permanecem decisão humana.
