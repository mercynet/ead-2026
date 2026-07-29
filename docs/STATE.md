# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- `main` integra `feat/financial-checkout` por fast-forward até `008c2be`, incluindo checkout
  financeiro durável, gateways Admin e `CoursePriceHistory` no Learning: histórico append-only e
  tenant-aware, gravado atomicamente sob lock; Financial mantém somente preço/snapshot contratado no
  `OrderItem`.
- Evidência focada: `CoursePriceHistoryTest` 7/24, `CourseCrudApiTest` 34/89, Architecture 17/68,
  Larastan e Pint verdes; antes do push, `CoursePriceHistoryTest`, Architecture e Pint revalidados.
- Governança replanejada por jornadas de área: ADR-006, `ROADMAP.md`, taxonomia área-first/neutra e
  inventário legacy atualizados. Área define valor; domínio define ownership; Mzrt inicia como
  walking skeleton, sem bloquear Admin/Student por control plane completo. Committed e pushed em
  `cd0a63b`.
- Evidência documental: revisão Oracle reconciliada, `git diff --check`, links internos e
  `graphify update .` verdes.

## Próximos passos (1-3)

1. Antes do próximo slice, retrofitar metadados da jornada ativa e derivar primeiro gate
   `FOUNDATION-0` (`area.guard`/permission ceiling) no `tasks.md` dono.
2. Planejar `MZRT-SKELETON`; `STUDENT-PAID` retoma depois conforme sequência do `ROADMAP.md`.

## Decisões abertas

- Definir política do override developer nas áreas: remover bypass implícito ou torná-lo explícito
  por endpoint e auditado.
- Fechar destinos das rotas legacy mistas e estratégia de Resources multi-área quando cada slice
  entrar em execução.

## Último commit

- Base integrada em `main`: `008c2be` (`docs: record learning and roadmap push`).
