# Roadmap — EAD 2026

Fases e milestones **cross-domain**. Sem checkbox por endpoint — o status fino vive nos
`tasks.md` de cada domínio (`docs/specs/<NN>-<domain>/tasks.md`).

Visão de produto, stack e princípios: `docs/specs/00-architecture/overview.md`.

## Fase 1 — Permissões e Roles (fundação)

Base de RBAC completa antes de tudo: UserTypes, scope de roles, teto de permissions.
Milestone em `10-core-identity/tasks.md`.

## Fase 2 — Base Administrativa Learning

CRUD completo de catálogo: categorias, cursos, módulos, aulas, matrículas, reorder, publish.
Milestone em `20-catalog-learning/tasks.md`.

## Fase 3 — Fluxos do Aluno

Matrícula, acesso a aulas, progresso (heartbeat), estatísticas de visualização.
Milestone em `20-catalog-learning/tasks.md`.

## Fase 4 — Assessment

Ajuste de permissions, anexar questões a questionários, fluxo de tentativa do aluno
(start/submit/finish). Milestone em `30-assessment/tasks.md`.

## Fase 5 — Eventos + Extras

Eventos de domínio para estatísticas (RabbitMQ → MariaDB), geração de PDF de certificado,
pre-signed URLs de mídia. Milestones em `20-catalog-learning/tasks.md` e `30-assessment/tasks.md`.

## Fases posteriores (não iniciadas)

- **Financial** — orders, payments, webhooks, matrícula automática (`40-financial/tasks.md`).
- **Ecosystem & Plugins** — marketplace, assinaturas SaaS, billing recorrente (`50-ecosystem-plugins/tasks.md`).
- **Privacidade/LGPD de produção** — export, erasure, consent, audit trail
  (`00-architecture/security-privacy-lgpd.md`).

## Diferidos (decisão tomada, fora do MVP)

- **Modos de matrícula** `invite_only` / `sales` — MVP só `open`. Adicionar coluna `enrollment_type`
  + fluxos quando o produto exigir convite/funil. Decisão em
  `20-catalog-learning/subspecs/courses-modules-lessons.md`.
- **Upgrade de stack** Laravel 13 + PHP 8.5 + pacotes — bloqueado: hoje todas as deps travam em
  `^12` (verificado via `composer why-not`). Reavaliar quando spatie/scribe/larastan/sanctum
  publicarem versões `^13`. Critério de prontidão: `composer why-not laravel/framework ^13` limpo.

## Estado atual (resumo)

Fundação (Core/RBAC) e leitura de Learning/Assessment implementadas; CRUD administrativo
parcial. Detalhe sempre nos `tasks.md`. Sessão atual e próximos passos: `docs/STATE.md`.
