# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- Re-slot área-first de categorias entregue: escrita saiu de `v1/learning/catalog/categories`
  (que fica só com `GET`) para `v1/admin/categories` (tenant) e `v1/mzrt/categories` (sistema, sem
  contexto de tenant). `is_system` virou campo **proibido** nos dois payloads — a área decide o
  escopo. `StoreCategoryAction`/`UpdateCategoryAction`/`CategoryPolicy` aceitam tenant nulo;
  `CategoryPolicy::create` e o gate `learning.categories.create-category` foram removidos junto com
  o `StoreCategoryRequest` legado.
- Evidência do re-slot: suíte completa 595/3553; Larastan sem erros; Pint, `git diff --check` e
  `graphify update .` verdes. E2E HTTP real `learning/admin-categories` 9/9 (inclui `405` na escrita
  legada e `404` de categoria de tenant vista pela Mzrt), zero resíduos no `ead2026_e2e`.

- Working tree acumulado foi consolidado em 7 commits atômicos sobre `a908827`: guarda de área com
  persona exata, teto de permissions por `UserType`, control plane Mzrt de tenants, hardening do
  runner E2E + lifecycle de tenant, espelho financeiro da concessão manual, pivô ordenado + delete
  protegido de categoria, e docs.
- Fatia `ADMIN-OPS` entregue: `PUT /api/v1/admin/courses/{id}/categories` substitui o conjunto
  completo de categorias do curso pelo pivô dedicado — `sort_order` derivado da posição no array
  (cliente não envia ordem, evitando colisão com `UNIQUE(course_id, sort_order)`), `is_featured` por
  item, categoria de sistema ou do próprio tenant, e payload inválido não altera o vínculo existente.
- Regressão corrigida no caminho: `DeleteCategoryAction` consultava `Category` sem filtro explícito
  de tenant e derrubava `TenantScopingTest` (ADR-004). Lock agora é por chave + escopo de tenant
  (`whereNull` para categoria de sistema).
- Dívida removida: `Category` ganhou docblock `@property` e 11 entradas obsoletas saíram do
  `phpstan-baseline.neon`.
- Evidência: AdminCourseCategoriesApiTest 15/46; Learning + Authorization + Architecture 294/1856;
  Larastan sem erros (baseline reduzido); Pint, `git diff --check` e `graphify update .` verdes.
  E2E HTTP real `learning/admin-course-categories` 8/8 contra app `APP_ENV=e2e` no DB `ead2026_e2e`,
  com zero resíduos verificados no banco.
- Branch `feat/foundation-area-guard`. Nada pushed.

## Próximos passos (1-3)

1. Perna de usuários do `ADMIN-OPS`: `PATCH`/`DELETE /users/{id}` re-slotados para a área Admin
   (`Pending` em `10-core-identity/tasks.md`).
2. Fechar a saída objetiva da jornada: E2E Admin integrado operando o mínimo no próprio tenant
   (catálogo + conteúdo + categorias numa única corrida).

## Decisões abertas

- Nenhuma bloqueante. Billing manual `external` continua diferido até definir reconciliação e momento
  do espelho; push aguarda pedido explícito.

## Último commit

- Branch `feat/foundation-area-guard`, HEAD em `feat(learning): move category writes to area-first surfaces`.
