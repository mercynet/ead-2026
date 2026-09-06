# Admin Closure — Slice 4 — Categorias System/Custom

**Data:** 2026-09-06
**Escopo:** Category System/Custom, somente superfícies Admin/Mzrt e leitura de catálogo.
**Status:** `CATEGORY_SYSTEM_CUSTOM_CONVERGED`

## Contrato fechado

- `System` é global (`tenant_id = null`) e permanece CRUD exclusivo de Developer/Mzrt.
- `Custom` pertence ao tenant atual e é o único tipo administrável por Admin.
- Parent é estritamente do mesmo escopo: System→System ou Custom→Custom do mesmo tenant.
- `normalized_name` é persistida por normalizador compartilhado; Custom não colide com System e
  não duplica semanticamente no próprio tenant, mesmo em parents diferentes.
- `tenant_key = COALESCE(tenant_id, 0)` e o índice único composto reforçam a separação no banco.
- `path`/`depth` materializam a árvore; move reescreve a subárvore e ciclo é rejeitado.
- Resources públicos expõem `type: system|custom`; `is_system` continua detalhe interno e proibido
  nos payloads de escrita.

## RED real

`CategoryScopeConvergenceApiTest.php` começou com **5 falhas**:

- Resource não expunha `type` nem os campos de hierarquia.
- Mesmo nome Custom sob parents diferentes era aceito.
- Custom sob parent System era aceito.
- Move não mantinha path/depth nem impedia ciclo.
- Schema não tinha `tenant_key`, `path` e `depth`.

## Implementação

- `CategoryNameNormalizer` compartilhado para lowercase, ASCII, trim e espaços colapsados.
- `CategoryHierarchy` para path/depth, moves, subárvore e cycle guard.
- migration `2026_09_06_120000_add_scope_and_hierarchy_fields_to_categories_table.php`.
- Store/Update com transação, unicidade por tenant/global e validação de parent de mesmo escopo.
- `CatalogCategoryResource` convergido para `type`, `path` e `depth`.
- testes e contrato Scribe ajustados para não expor `is_system`.

## Evidência

- Focal convergência: **6 passed (46 assertions)**.
- Regressão categorias/schema/authorization: **41 passed (140 assertions)**.
- RED foi executado antes da implementação.

## Pendências fora deste slice

- Não abertos Instructor/Student/Mzrt além do CRUD System necessário para a taxonomia.
- i18n, MediaProvider, quiz avançado, matrícula externa e lifecycle de plugins permanecem decisões
  explicitamente adiadas.
- E2E, Pint, PHPStan, arquitetura e Scribe serão registrados no relatório consolidado após a
  validação do conjunto Admin.
