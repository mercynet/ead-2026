# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-06-13 — **Replanejamento fundamental: ÁREAS + reuso do eadIA.** Definidas as **5 áreas**
  (Mzrt, Admin, Instructor, Student, Home/público), separadas por **namespace de API**
  (`/api/v1/{area}/...`), Home por último. Contrato em
  `docs/specs/00-architecture/areas-surfaces.md` (**draft, a ratificar**). Referência de
  domínio/plugins/financeiro = projeto `../eadIA` (o plano de áreas dele está errado:
  "Desktop"=Home, "dashboard"=Admin — não copiar). **ADR-001** (`decisions/001-...`) registra
  baseline de pacotes + billing por abstração de gateway.
- **Decisões fechadas:** só multi-tenant (não multi-país); conteúdo i18n traduzível; comissão de
  instrutor é domínio (3ª camada de billing); gateways = **plugins financeiros** tenant-seleção
  (Stripe no MVP via `laravel/cashier` sob `PaymentGatewayInterface`); deps aprovadas e estagiadas
  (cashier→task Stripe, telescope→dev, impersonate→feature de impersonação).
- **Pacotes:** ead2026 já tem multitenancy/permission/activitylog **ligados** e medialibrary
  instalado (usar p/ materiais/PDF). Não reinventar.
- **Revisão crítica das migrations do eadIA** gravada em
  `docs/specs/00-architecture/eadia-port-review.md` (adotar/adaptar/rejeitar + problemas pegos).
  Itens de reuso espalhados nos `tasks.md` de 20/40/50 (seção "Reuso eadIA").
- **Estratégia de porte (decidida com Paulo): just-in-time por domínio.** Ao desenvolver um
  domínio, revisar as migrations do eadIA daquele domínio + ligadas, decidir o que compensa, trazer
  **adaptado** (modular, cents, `encrypted:json`, FK inteiro, i18n+fallback) com teste — nunca `cp`.
  **Courses já revisado** (verdito no `20-catalog-learning/tasks.md` §Reuso): ead2026 mais rico;
  trazer só i18n traduzível + `is_fifo` + `meta_title/meta_description`.
- **`areas-surfaces.md` RATIFICADA (2026-06-13):** maturity `stable`. Sub-decisões fechadas:
  **(A)** URL área-first puro `v1/{area}/{resource}`; **(B)** middleware `area.guard` dedicado;
  **(D)** re-slot incremental começando por `/admin`. **(C)** (estratégia de Resource) segue aberta
  — decide ao implementar 2ª área.
- **Slice entregue — 1º da área Admin:** `GET /courses/{id}` re-slotado para
  `GET /api/v1/admin/courses/{id}`. Scaffold criado: `Area` enum + `UserType::rank()`,
  `EnsureAreaAccess` (`area.guard`, alias em `bootstrap/app.php`), `AreaAccessDeniedException`
  (envelope `area_forbidden` 403), `Routes/admin.php`, `Http/Controllers/Admin/CourseController`.
  Rota antiga `/v1/learning/courses/{id}` removida (sem frontend consumindo). student/instructor
  agora barrados pela guarda. **Verde:** Feature CourseCrud 17/17, Architecture 14/14, Area unit
  7/7, E2E `courses-show` 7/7.

## Próximos passos (1-3)

1. **Próximo slice área-first.** Candidatos: re-slotar outros endpoints de course (modules/CRUD)
   em `/admin`, ou abrir área `instructor`/`student` (aí decide **sub-decisão C** = base Resource
   compartilhada vs independente por área).
2. **Porte do Financial** seguindo `eadia-port-review.md` + ADR-001: `PaymentGatewayInterface` +
   `TenantPaymentGateway` (`encrypted:json`) + `Order/OrderItem` polimórfico em **cents** +
   `StripeGateway` (Cashier). Corrigir colisão de PK em `plugins` antes de portar plugin tables.
3. Alternativa: continuar trilha Learning (`publish/unpublish`, attach categories) já no namespace
   da área correspondente.

## Para depois (parqueado — não é o foco agora)

- **Auditor de supply chain `security:audit-deps`** — **ENTREGUE** (commit `1996c01`). Comando +
  `DependencyAuditService` + fixtures clean/suspicious + `.githooks/{pre-commit,pre-push}`. Hooks
  são **opt-in**: `git config core.hooksPath .githooks` pra ativar (ainda não ativado).
- **Upgrade Laravel 13 / PHP 8.5** — task dedicada; hoje bloqueado por deps em `^12`
  (scribe/boost/sanctum/larastan/spatie). Ver `ROADMAP.md` §"Meta de stack".

## Decisões abertas

- **Sub-decisão (C)** de `areas-surfaces.md`: estratégia anti-repetição de Resource (base
  compartilhada + subclasses por área vs independentes). Decide ao implementar a 2ª área.
- **Disciplina de contexto (Paulo)**: `clear` por endpoint; retomar via `AGENTS.md` + STATE +
  `spec-task-planning`.
- Dívidas pré-existentes: allowlist `ModuleBoundaryTest` → Events/Contracts; phpstan level 5
  (~156 erros). Sem regressão nova.

## Último commit

- `1996c01` — `feat(security): supply-chain dependency auditor (security:audit-deps)`
- `48968fa` — `feat(learning): area-first guard + re-slot GET /admin/courses/{id}`
- Branch `harness/specs-foundation`. **Ainda NÃO pushed** (3e9a02c era o último push).
  Working tree limpo após estes 2 commits + este update de STATE.
