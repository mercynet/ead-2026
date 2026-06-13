# Specs — Índice Canônico

Especificações de domínio da plataforma EAD 2026. Escritas em **PT-BR**; identificadores, código
e permissions em **inglês**.

## Como navegar

- **Cross-cutting** (vale para todos os domínios) → `00-architecture/`. Regra escrita **uma vez**
  aqui; as specs de domínio **linkam** em vez de repetir.
- **Por domínio** → pastas numeradas por dezenas. Cada uma tem:
  - `spec.md` — o **contrato durável** (intenção, entidades, regras, boundaries, eventos,
    autorização, quick reference). **Nunca** contém status/checkbox.
  - `tasks.md` — **estado mutável**: o que está feito, em progresso, pendente, em revisão e
    questões abertas.
  - `subspecs/` — detalhe por recurso (schema de colunas, endpoints, permissions, notas).

## Estrutura

```
docs/
  ROADMAP.md                 # fases/milestones cross-domain
  STATE.md                   # efêmero: sessão atual + próximos passos
  specs/
    README.md                # este arquivo
    00-architecture/
      overview.md            # visão, stack, princípios, lifecycle, mapa de domínios
      backend-patterns.md    # modular monolith, ports/adapters seletivo, YAGNI×SOLID, fronteira de módulo
      testing-strategy.md    # pirâmide unit/feature/e2e/architecture, onde cada nível cabe
      api-conventions.md     # envelope de erro, cursorPaginate, ApiContext, Response, FormRequest, API-DX
      multi-tenancy.md       # resolução, isolamento, stack de middleware
      rbac.md                # UserTypes, roles, plugins, matriz de permissões, golden rules
      security-privacy-lgpd.md
      dependency-supply-chain-security.md
      performance-scalability.md
      glossary.md
    10-core-identity/        # auth, users, tenant-config
    20-catalog-learning/     # catalog, courses/modules/lessons, enrollment/progress, media/ratings
    30-assessment/           # questionnaires/questions, attempts/scoring, certificates
    40-financial/            # orders/payments, webhooks/events
    50-ecosystem-plugins/    # marketplace, subscriptions/billing
```

## Convenção de Numeração

Pastas de domínio numeradas **por dezenas** (`10`, `20`, `30`, …) para permitir inserção de novos
domínios entre os existentes (ex.: um futuro `25-` entre learning e assessment) sem renumerar tudo.
`00-architecture/` é a base cross-cutting.

## Como manter

- **Mudou regra de negócio / contrato?** Edite a `spec.md` (ou subspec) e ajuste `last-reviewed`
  no frontmatter.
- **Avançou implementação?** Edite o `tasks.md` do domínio (`last-updated`). **Nunca** coloque
  status na `spec.md`.
- **Regra cross-cutting?** Vai em `00-architecture/`; nas specs, apenas linke.
- **Milestone cross-domain?** `docs/ROADMAP.md`. **Sessão atual / próximos passos?** `docs/STATE.md`.

### Frontmatter

- `spec.md`: `{domain, maturity: draft|stable|deprecated, last-reviewed, owners, related}`
  — `maturity` = maturidade do **contrato** (quão estável é a spec em si), **não** status de
  implementação (esse vive só no `tasks.md`).
- `tasks.md`: `{domain, last-updated}`
- `subspecs/*.md`: `{domain, parent, resource, last-reviewed}`
- `00-architecture/*`: `{layer: architecture, applies-to: all-domains, last-reviewed}`
