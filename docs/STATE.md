# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`.

## Sessão

- 2026-06-10 — Reestruturação das specs: árvore por domínio (`00-architecture/` cross-cutting +
  `10/20/30/40/50` por domínio), separação contrato (`spec.md`) × estado (`tasks.md`),
  consolidação de status.

## Próximos passos (1-3)

1. Fase 1: implementar o "teto" de permissions por UserType (ver `10-core-identity/tasks.md`).
2. Fase 2: `POST /courses` + publish/unpublish e CRUD de módulos (`20-catalog-learning/tasks.md`).
3. Resolver a open question de modelo de acesso/conteúdo de cursos antes do CRUD de lessons.

## Decisões abertas

- CPF em outro tenant: erro duro vs. reaproveitamento de pool (`10-core-identity/tasks.md`).
- `enrollment_type`/`content_type` vs. `is_free`/`access_days`
  (`20-catalog-learning/subspecs/courses-modules-lessons.md`).
