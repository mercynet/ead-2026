# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

Working tree acumulado foi consolidado em commits atômicos e a jornada `ADMIN-OPS` avançou três
fatias. `main` integra `feat/foundation-area-guard` por fast-forward.

Consolidação (7 commits sobre `a908827`): guarda de área com persona exata, teto de permissions por
`UserType`, control plane Mzrt de tenants, hardening do runner E2E + lifecycle de tenant, espelho
financeiro da concessão manual, pivô `category_course` ordenado + delete protegido de categoria.

Fatias novas:

- `PUT /api/v1/admin/courses/{id}/categories` — substitui o conjunto completo de categorias do curso;
  `sort_order` derivado da posição no array (cliente não envia ordem, o que torna a colisão com
  `UNIQUE(course_id, sort_order)` inexpressável); payload inválido preserva o vínculo anterior.
- Re-slot área-first de categorias: escrita saiu de `v1/learning/catalog/categories` (que fica só com
  `GET`) para `v1/admin/categories` (tenant) e `v1/mzrt/categories` (sistema, sem contexto de tenant).
  `is_system` virou campo **proibido** nos dois payloads — a área decide o escopo.
- `PATCH`/`DELETE /api/v1/admin/users/{id}` — admin administra instructor/student do próprio tenant;
  `user_type`/`email`/`cpf`/`password` proibidos; admin par → 403, developer/cross-tenant → 404.
  `DELETE` é soft delete (`deleted_at` novo em `users`) + revogação das sessões Sanctum na mesma
  transação.

Correções no caminho: `DeleteCategoryAction` consultava `Category` sem filtro de tenant e derrubava
`TenantScopingTest` (ADR-004); `Response::denyAsNotFound()` produzia 404 fora do envelope padrão, o
que denunciava a existência do recurso pelo corpo da resposta. Dívida removida: `Category` ganhou
docblock `@property` e 11 entradas obsoletas saíram do `phpstan-baseline.neon`;
`CategoryPolicy::create`, o gate `learning.categories.create-category` e o `StoreCategoryRequest`
legado foram apagados.

Evidência final: suíte completa 613/3609; Larastan sem erros; Pint, `git diff --check` e
`graphify update .` verdes. E2E HTTP real contra `APP_ENV=e2e` no DB `ead2026_e2e`, todos com zero
resíduos conferidos no banco: `learning/admin-course-categories` 8/8, `learning/admin-categories` 9/9,
`core/admin-users` 11/11. Evidência Foundation/MZRT anterior permanece válida (E2E real 9/9).

## Próximos passos (1-3)

1. Fechar a saída objetiva do `ADMIN-OPS`: E2E Admin integrado numa única corrida — criar categoria,
   criar/publicar curso, vincular categorias, administrar um aluno.
2. Decidir o destino de `GET /core/users` e `/core/users/me*`: área Admin vs superfície neutra de
   conta (`ROADMAP.md`, linha `/core/users*`).

## Decisões abertas

- Nenhuma bloqueante. Diferidos: billing manual `external` (aguarda reconciliação e momento do
  espelho) e restore/reaproveitamento de e-mail de usuário excluído.

## Último commit

- `main` = `feat/foundation-area-guard` por fast-forward, com as 11 fatias acima; pushed para `origin`.
