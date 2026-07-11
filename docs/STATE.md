# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 — **P0 completo + P1 executável completo** (auditoria
  [`docs/auditoria-correcoes-2026-07-11-pending.md`](auditoria-correcoes-2026-07-11-pending.md),
  cada item anotado ✅/🟡 no doc). Tudo commitado e **pushado** em `harness/specs-foundation`:
  - **P0.1** ✅ snapshot de quiz server-side (nota não é mais forjável; bug `maxPoints=0` morto).
  - **P0.2** ✅ hardening de `file_path` de material (cross-tenant bloqueado).
  - **P1.1** ✅ `access_days=0` = vitalício + presets validados.
  - **P1.2** ✅ `QuizAttemptPolicy::create` (junto do P0.1).
  - **P1.3** 🟡 parcial: coluna `course_id` em certificates + verify com título real;
    **emissão automática pendente** (task no `tasks.md` do Assessment).
  - **P1.4** ✅ `LessonPolicy` no padrão canônico (developer 403 resolvido; matriz RBAC consultada).
  - **P1.5** ✅ denominador de progresso só published+active; `completed_at` estampado em 100%.
  - **P1.6** ⏸ decisão de arquitetura (trait `BelongsToTenant` c/ global scope) — pede ADR/decisão.
  - Extra: bump `spatie/laravel-medialibrary` 11.23.2 (CVE-2026-48557/48555, exigido pelo pre-push).
  - Suites no fim: Feature 298/298, Architecture 15/15, Unit 8/8.

## Próximos passos (1-3)

1. **Emissão automática de certificado** (`Certificate::create` via `CourseCompletedEvent` ao
   atingir 100% + config `certificate_*` do curso) — pré-requisitos P1.3/P1.5 já fechados.
2. **Decisão de escopo** (piloto gratuito vs MVP pago) + **decisão P1.6** (global scope de tenant
   — discutir e registrar ADR via `create-adr`).
3. Depois: P2 da auditoria (recompra pós-cancelamento, vazamento de drafts na landing, slug único)
   e LGPD-03 (uniques tenant-scoped).

## Decisões abertas

- **Escopo de lançamento:** piloto gratuito controlado ou MVP comercial pago?
- **P1.6:** adotar trait `BelongsToTenant` com global scope (+ `creating` hook) mantendo `where`
  explícito como defesa em profundidade, ou só ampliar `TenantIsolationSmokeTest`? (ADR pendente.)
- Caminho pago: gateway inicial, política de reembolso, modelo de reconciliação.
- Dívidas transversais nos `tasks.md`/roadmap: teto RBAC, LGPD-03, CI, LGPD operacional,
  PHPStan/advisories, fronteiras cross-module.

## Último commit

- `e750ac4` (P1.4) — branch `harness/specs-foundation`, sincronizada com origin.
