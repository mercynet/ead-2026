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
- **Slice entregue:** `GET /courses/{id}` (admin view) — Feature 15/15, Architecture 14/14, E2E
  `courses-show` 5/5. (Detalhe em `20-catalog-learning/tasks.md`.)

## Próximos passos (1-3)

1. **Ratificar `areas-surfaces.md`** (draft→stable) e decidir formato de URL (área-first puro) +
   guard de área. Então re-slotar `GET /courses/{id}` em `/admin` (e abrir próximos slices já
   área-first).
2. **Porte do Financial** seguindo `eadia-port-review.md` + ADR-001: `PaymentGatewayInterface` +
   `TenantPaymentGateway` (`encrypted:json`) + `Order/OrderItem` polimórfico em **cents** +
   `StripeGateway` (Cashier). Corrigir colisão de PK em `plugins` antes de portar plugin tables.
3. Alternativa: continuar trilha Learning (`publish/unpublish`, attach categories) já no namespace
   da área correspondente.

## Para depois (parqueado — não é o foco agora)

- **Auditor de supply chain `security:audit-deps`** (spec em
  `00-architecture/dependency-supply-chain-security.md`) — **adiado** por decisão do Paulo.
- **Upgrade Laravel 13 / PHP 8.5** — task dedicada; hoje bloqueado por deps em `^12`
  (scribe/boost/sanctum/larastan/spatie). Ver `ROADMAP.md` §"Meta de stack".

## Decisões abertas

- `areas-surfaces.md` ainda **draft** — ratificar (sub-decisões A/B/C/D no fim da spec).
- **Disciplina de contexto (Paulo)**: `clear` por endpoint; retomar via `AGENTS.md` + STATE +
  `spec-task-planning`.
- Dívidas pré-existentes: allowlist `ModuleBoundaryTest` → Events/Contracts; phpstan level 5
  (~156 erros). Sem regressão nova.

## Último commit

- `c4a8a55` — `feat(learning): add POST /courses + live E2E validation tooling` — branch
  `harness/specs-foundation`, **não pushed**. Working tree **sujo e não commitado**: slice
  `GET /courses/{id}` (controller/rota/gate/policy/tests/E2E) **+** docs de replanejamento
  (`areas-surfaces.md`, `decisions/001-...`, `eadia-port-review.md`, ROADMAP, tasks.md 20/40/50).
