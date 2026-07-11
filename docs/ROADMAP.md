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
- **Segurança de supply chain** — comando `security:audit-deps`, policy/baseline de dependências,
  hooks `pre-commit` + `pre-push` e validação gratuita em CI
  (`00-architecture/dependency-supply-chain-security.md`).

## Áreas & reuso do eadIA (replanejamento 2026-06-13)

Decisão fundamental: o sistema tem **5 áreas/audiências** (Mzrt, Admin, Instructor, Student,
Home/público), separadas por **namespace de API** (`/api/v1/{area}/...`). Contrato durável em
[`specs/00-architecture/areas-surfaces.md`](specs/00-architecture/areas-surfaces.md). **Home é a
última** a implementar. Endpoints atuais (domínio-first, persona-borrados) migram incrementalmente.

Reuso do projeto anterior `../eadIA` (referência de domínio/plugins/financeiro — **não** de áreas):
baseline de pacotes + billing via abstração de gateway em
[`specs/00-architecture/decisions/001-reuso-eadia-pacotes-billing.md`](specs/00-architecture/decisions/001-reuso-eadia-pacotes-billing.md).
Itens a importar por domínio estão nos `tasks.md` (seção "Reuso eadIA").

## Diferidos (decisão tomada, fora do MVP)

- **Modos de matrícula** `invite_only` / `sales` — MVP só `open`. Adicionar coluna `enrollment_type`
  + fluxos quando o produto exigir convite/funil. Decisão em
  `20-catalog-learning/subspecs/courses-modules-lessons.md`.
## Meta de stack — Laravel latest + PHP 8.5 (decisão: queremos o mais atual)

Objetivo do dono: rodar sempre a versão mais atual. Hoje: Laravel **12.52** / PHP **8.4.1**;
Laravel **13.15** já existe. **Bloqueio atual (2026-06-13, `composer why-not laravel/framework ^13`):**
`knuckleswtf/scribe`, `laravel/boost`, `laravel/sanctum` v4.2, `larastan`, `spatie/*`
(medialibrary, activitylog) ainda fixam `^12` nas versões instaladas. **Task dedicada** (não no meio
de outra): branch própria, bump framework + todas deps com `--with-all-dependencies`, subir PHP 8.5,
**suíte como árbitro**. Critério de prontidão: `composer why-not laravel/framework ^13` limpo.

## Estado atual (resumo)

Fundação (Core/RBAC) e leitura de Learning/Assessment implementadas; CRUD administrativo
parcial. Detalhe sempre nos `tasks.md`. Sessão atual e próximos passos: `docs/STATE.md`.
