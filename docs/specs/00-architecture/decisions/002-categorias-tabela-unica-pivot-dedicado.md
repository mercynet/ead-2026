# ADR-002: Categorias — tabela única, pivô dedicado por tipo, hierarquia por materialized path, soft delete

- **Data**: 2026-06-13
- **Status**: Aceito
- **Decisores**: Paulo, Claude

## Contexto e problema

Categorias são fonte recorrente de **drift**, **duplicação** e **custo de performance**. O modelo
precisa: servir cursos agora e **produtos no futuro** sem virar um n:n monolítico; manter
integridade referencial; suportar **categorias de sistema** (taxonomia canônica, mantida só pelo
developer) e **categorias de tenant** (custom, fechadas ao tenant); e permitir **hierarquia em N
níveis** sem pagar recursão a quente em toda leitura.

A spec anterior (`subspecs/catalog.md`) já previa tabela única com `is_system` + antiduplicação,
mas não fechava: estratégia de pivô (havia menção a "polimórfico"), mecânica de hierarquia,
unicidade no nível de banco e ciclo de vida de delete/restore.

## Drivers da decisão

- **Integridade** — evitar órfão/drift (FK reais nos vínculos).
- **Antiduplicação** — system é único global; nome de tenant é único **dentro** do tenant; entre
  tenants diferentes o mesmo nome é livre; tenant não pode colidir com nome de sistema.
- **Performance de leitura de subárvore** — "cursos da categoria X incluindo descendentes" não pode
  custar recursão a cada request.
- **Extensão** — adicionar um novo "categorizável" (produto) sem inchar um único pivô gigante.
- **Superfícies distintas** — system = developer/Mzrt; tenant = Admin.

## Opções consideradas

### Tabela
- **Tabela única `categories` + `is_system`** ✅ escolhida.
- Duas tabelas (system / tenant) ❌ — duplica schema e força `UNION` manual em toda leitura.

### Vínculo com categorizáveis
- **Pivô dedicado por tipo** (`category_course`, futuro `category_product`) ✅ escolhida — FK reais
  nos dois lados, colunas de pivô específicas do tipo (`order`, `is_featured`), índices/partição
  independentes, tabelas menores.
- `MorphToMany` único (`categorizables`) ❌ — **sem FK real** (drift), hotspot único, impossível ter
  coluna de pivô por tipo. É o n:n gigante, **não** a mitigação dele.

### Hierarquia
- **Adjacency list (`parent_id`) + materialized path (`path`, `depth`)** ✅ escolhida — leitura de
  subárvore por `path LIKE 'X/%'`; escrita (rara) mantém `path`/`depth`.
- Closure table ❌ — over-engineering para árvore rasa/baixa cardinalidade.
- Adjacency puro ❌ — subárvore exige CTE recursiva a quente.

### Ciclo de vida (delete/restore)
- **Soft delete + unicidade canônica no app** ✅ escolhida — mesmo `id` preservado, `path`/pivôs
  estáveis; checagem de unicidade escopada a `whereNull('deleted_at')`, então soft-deletado não
  bloqueia nome novo.
- Tabela de arquivados (remover de `categories`, mover p/ `archived_categories`) ❌ — id novo no
  restore **quebra pivôs e referências** (vira o órfão que queremos evitar), exige reescrever pivôs
  e espelhar schema em duas tabelas para sempre. Pior justamente no restore-relink.
- Hard delete puro ❌ — perde audit trail e recuperação.

## Decisão

1. **Tabela única `categories`**: `is_system` (bool), `tenant_id` nullable (`null` = sistema).
2. **Pivô dedicado por categorizável**: `category_course` (`tenant_id`, FK `course_id`, FK
   `category_id`, `order`, `is_featured`). Produtos no futuro = `category_product` no mesmo molde.
   **Nunca** morph.
3. **Hierarquia N níveis** via `parent_id` + **materialized path** (`path`, `depth`) mantido na
   escrita. Prevenção de ciclo (não setar pai em descendente próprio) na camada de aplicação.
4. **Parent de mesmo escopo**: system→system, tenant→mesmo tenant. Cross-escopo **proibido**. O
   tenant **seleciona** categorias de sistema como banco/tag, não as usa como pai da própria árvore.
5. **Unicidade**:
   - `normalized_name` **persistida** (lowercase, sem acento, `trim`, espaços colapsados).
   - `UNIQUE(tenant_key, normalized_name)` onde `tenant_key` é coluna gerada `COALESCE(tenant_id, 0)`
     — contorna o "NULL é distinto" do MySQL e cobre, de uma vez, **system único global** e
     **tenant único interno**.
   - Regra "nome de tenant ≠ qualquer nome de sistema" no app (`StoreCategoryAction`); não cabe em
     índice (escopos diferentes).
   - **Fonte de verdade** da unicidade = app, escopada a não-deletados. O `UNIQUE` de banco é rede
     secundária.
6. **Soft delete**. No delete:
   - **System com cursos** → **bloqueia** (nem developer apaga categoria de sistema com cursos).
   - **Tenant com cursos** → permite com `force`/`confirm` explícito; ao confirmar, **detach** dos
     pivôs. **Invariante: nenhum pivô aponta para categoria soft-deletada.** Restore traz a
     categoria de volta **vazia** (re-tag manual).
   - Restore cujo nome colida com categoria ativa → rename forçado ou recusa.
7. **Áreas/autorização**: system = superfície **Mzrt/developer**; tenant = **Admin**. A decisão por
   `is_system` vive na `CategoryPolicy`, nunca em `if` de controller.

## Consequências

- **(+)** Integridade por FK nos pivôs; sem órfão. Subárvore barata por `path`. Extensão a produtos
  sem tocar schema de `categories`.
- **(−)** Materialized path exige manter `path`/`depth` na escrita; **mover** uma subárvore reescreve
  os descendentes (aceitável — escrita rara).
- **(−)** Unicidade canônica no app tem janela TOCTOU sob concorrência (aceitável — criação de
  categoria é rara; `UNIQUE` de banco mitiga quando não há soft-deletado em jogo).
- **(−)** `normalized_name` depende de normalização consistente — extrair helper compartilhado para
  store/update/validação.
