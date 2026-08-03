# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- Perna de usuários do `ADMIN-OPS` entregue: `PATCH`/`DELETE /api/v1/admin/users/{id}`. Admin
  administra instructor/student do próprio tenant; `user_type`/`email`/`cpf`/`password` são campos
  proibidos; admin par → 403 e developer/cross-tenant → 404. `DELETE` é soft delete (`deleted_at`
  novo em `users`) e revoga as sessões Sanctum na mesma transação.
- Conflito de spec resolvido em `subspecs/users.md`: a coluna "quem pode editar = apenas developer"
  vale para **mutação de `user_type`**, não para o registro inteiro.
- Envelope corrigido: `Response::denyAsNotFound()` virava `HttpException` 404 cru, fora do envelope
  padrão — o handler passou a cobrir qualquer 404 de API, senão o corpo denuncia que o recurso existe.
- Evidência: AdminUserManagementApiTest 18/56; suíte completa 613/3609; Larastan sem erros; Pint e
  `graphify update .` verdes. E2E HTTP real `core/admin-users` 11/11, com zero resíduos no
  `ead2026_e2e`.

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

1. Fechar a saída objetiva do `ADMIN-OPS`: E2E Admin integrado numa única corrida — criar categoria,
   criar/publicar curso, vincular categorias, administrar um aluno.
2. Decidir o destino de `GET /core/users` e `/core/users/me*`: área Admin vs superfície neutra de
   conta (`ROADMAP.md`, linha `/core/users*`).

## Decisões abertas

- Nenhuma bloqueante. Billing manual `external` continua diferido até definir reconciliação e momento
  do espelho; push aguarda pedido explícito.

## Último commit

- Branch `feat/foundation-area-guard`, HEAD em `feat(core): manage tenant users from the admin area`.
