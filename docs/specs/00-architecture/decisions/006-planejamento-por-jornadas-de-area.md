# ADR-006: Planejamento por jornadas de área com ownership por domínio

- **Data**: 2026-07-29
- **Status**: Aceito
- **Decisores**: Paulo

## Contexto e problema

Fases horizontais por domínio incentivavam “terminar” Core, Learning ou Mzrt antes de provar valor
para persona, embora resultados atravessem módulos. Mzrt precisa aparecer cedo para provisionar
tenants, mas marketplace e billing SaaS completos antes de Admin/Student atrasariam produto.

## Drivers

- Contrato API explícito por persona e isolamento tenant/RBAC verificável.
- Valor observável em E2E, menor WIP e ownership técnico coeso.
- Fundação finita, sem fase horizontal interminável.

## Opções consideradas

1. **Fases por domínio.** Agrupa código, mas não mede jornada utilizável.
2. **Completar Mzrt primeiro.** Entrega control plane amplo, mas antecipa marketplace, plugins e
   billing sem demanda.
3. **Jornadas por área com ownership por domínio.** Escolhida. Área/persona governa valor, contrato
   e ordem; domínio limitado mantém Actions, models, eventos e fronteiras de implementação.

## Decisão

Executar uma jornada principal por vez em fatias verticais. Endpoint é unidade de execução; jornada
é unidade de sucesso. `FOUNDATION-0` contém somente invariantes universais de cada rota de área;
gates de plugin, auditoria/segredos e idempotência/outbox entram antes da jornada que os usa.

Mzrt começa como walking skeleton: tenant, primeiro admin, status e limits/entitlements. Marketplace,
`PlatformOrder*`, assinaturas, quotas e plugins amplos ficam para `MZRT-PLATFORM`. Produto por persona
usa `/api/v1/{area}/*`; rotas técnicas/cross-area podem usar namespaces neutros explícitos, como
`/auth/*`, `/webhooks/*` e `/public/*`. Prefixos domínio-first são legados até migração ou depreciação.

`tasks.md` adota metadados `Journey | Area | Depends on` prospectivamente: nova/tocada task exige
formato; jornada ativa é retrofitted antes do próximo slice; backlog intocado migra quando selecionado.

## Consequências

Benefícios: prioridade por persona, E2E como evidência e domínios coesos. Custos: disciplina contra
abrir várias jornadas, decisão explícita para rotas mistas e manutenção de inventário legado. Matriz
de status é snapshot de planejamento, não prova de hardening.

## Links

- [Roadmap](../../../ROADMAP.md)
- [Áreas & Superfícies](../areas-surfaces.md)
- [ADR-003 — dois ledgers](003-billing-dois-ledgers-itemable-seam.md)
- [ADR-005 — plugins capability-gated](005-plugins-capability-gated-gateway-como-plugin.md)
