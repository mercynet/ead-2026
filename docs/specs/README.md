# Specs — Índice Canônico

Especificações de domínio da plataforma EAD 2026. Escritas em **PT-BR**; identificadores, código
e permissions em **inglês**.

## Como navegar

- **Cross-cutting** (vale para todos domínios) → `00-architecture/`. Regra escrita **uma vez** aqui;
  specs de domínio **linkam** em vez de repetir.
- **Por domínio** → pastas numeradas por dezenas. Cada uma tem:
  - `spec.md` — contrato durável (intenção, entidades, regras, boundaries, eventos, autorização,
    quick reference). **Nunca** contém status/checkbox.
  - `tasks.md` — estado mutável: feito, progresso, pendente, revisão e questões abertas.
  - `subspecs/` — detalhe por recurso (schema, endpoints, permissions, notas).

## Governança e hierarquia

1. [`docs/ROADMAP.md`](../ROADMAP.md) governa jornadas cross-domain por área e matriz de
   planejamento área × capability.
2. `tasks.md` decompõe slices locais. Metadados de jornada são migração **prospectiva**, não
   reclassificação em massa do backlog.
3. `STATE.md` registra somente sessão ativa, handoff e próximos passos; não é roadmap nem status
   durável.

**Endpoint é unidade de execução; jornada é unidade de sucesso.** Endpoint pode fechar fatia, mas
só jornada com DoD no roadmap entrega valor à persona.

### Metadados de task para jornada

Em task nova ou tocada, usar bloco compacto:
`Journey: <ID> | Area: <area|neutral> | Depends on: <IDs|none>`. Antes do próximo slice de código de
jornada ativa, retrofitar suas tasks ativas. Backlog intocado migra somente quando selecionado; não
alterar só para cumprir formato.

## Estrutura

```
docs/
  ROADMAP.md                 # jornadas cross-domain por área + matriz de planejamento
  STATE.md                   # efêmero: sessão ativa + próximos passos
  specs/
    README.md                # este arquivo
    00-architecture/
      overview.md            # visão, stack, princípios, lifecycle, módulos e áreas
      areas-surfaces.md      # contratos de área/persona e superfícies API
      decisions/             # ADRs arquiteturais
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

Pastas de domínio numeradas **por dezenas** (`10`, `20`, `30`, …) permitem inserir domínio entre
existentes (ex.: futuro `25-`) sem renumerar. `00-architecture/` é base cross-cutting.

## Como manter

- **Mudou regra de negócio / contrato?** Edite `spec.md` (ou subspec) e ajuste `last-reviewed`.
- **Avançou implementação?** Edite `tasks.md` (`last-updated`). **Nunca** coloque status em `spec.md`.
- **Regra cross-cutting?** Vai em `00-architecture/`; nas specs, apenas linke.
- **Jornada ou matriz cross-domain?** `docs/ROADMAP.md`. **Slice local?** `tasks.md`; tarefa nova ou
  tocada usa `Journey | Area | Depends on`, conforme migração prospectiva. **Sessão atual / próximos
  passos?** `docs/STATE.md`.

### Status e evidência

`Done` em `tasks.md` significa que o slice foi declarado entregue; `Pending` contém somente delta
aberto. `[x]` não promove uma capability a runtime verificado. A evidência deve ser classificada
separadamente como `RUNTIME_VERIFIED` (execução atual contra app/banco adequados), `TEST_VERIFIED`
(teste/resultado de teste disponível, sem substituir runtime atual), `STATIC_EVIDENCE_ONLY`
(código/rota/model/migration/teste presente sem resultado atual confiável), `DOCUMENTATION_ONLY`
(intenção sem implementação arbitrada) ou `UNVERIFIED` (evidência insuficiente ou conflitante).

Na reconciliação de 2026-09-05, nenhuma capability foi promovida a `RUNTIME_VERIFIED`; resultados
históricos continuam históricos.

### Frontmatter

- `spec.md`: `{domain, maturity: draft|stable|deprecated, last-reviewed, owners, related}` —
  `maturity` = maturidade do **contrato**, não status de implementação (vive em `tasks.md`).
- `tasks.md`: `{domain, last-updated}`
- `subspecs/*.md`: `{domain, parent, resource, last-reviewed}`
- `00-architecture/*`: `{layer: architecture, applies-to: all-domains, last-reviewed}`
