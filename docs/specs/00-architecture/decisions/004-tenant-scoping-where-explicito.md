# ADR-004: Isolamento de tenant via `where('tenant_id')` explícito com enforcement por teste de arquitetura

- **Data**: 2026-07-11
- **Status**: Aceito
- **Decisores**: Paulo (dono do projeto)

## Contexto e problema

O isolamento por `tenant_id` é a invariante de segurança mais crítica do sistema
([`../multi-tenancy.md`](../multi-tenancy.md)). Hoje há ~55 ocorrências de
`where('tenant_id', ...)` manuais em `app/` (Actions, FormRequests, Models), e a única defesa
contra *esquecer* o filtro numa query nova é revisão humana + testes de Feature por endpoint.
A auditoria de 2026-07-11 levantou a questão P1.6: adotar um trait `BelongsToTenant` com
**global scope** do Eloquent, ou manter filtragem explícita e fechar o gap com enforcement
automatizado.

## Drivers da decisão

- O contexto de tenant vem **por request** (`ApiContext` via middleware, resolvido por
  header/host) — não existe singleton global de "tenant atual" no container.
- `developer`/landlord tem `tenant_id = null` e opera cross-tenant; jobs de fila e comandos de
  console rodam sem request (logo sem tenant resolvido).
- O projeto já valida invariantes com testes de arquitetura executáveis
  (`tests/Architecture/`), com padrão de allowlist para dívida conhecida.
- Solo dev: previsibilidade e debuggability valem mais que menos digitação.

## Opções consideradas

- **A) `where('tenant_id')` explícito + teste de arquitetura** ✅ escolhida
- **B) Trait `BelongsToTenant` com global scope + bypass explícito**
- **C) Status quo** (explícito sem enforcement — o que existe hoje)

## Decisão

Escolhemos **A**. O global scope (B) perdeu porque exigiria criar um estado global de "tenant
atual" que hoje não existe (o tenant vive no `ApiContext` da request), e porque inverte o modo
de falha: em vez de *esquecer o `where`* (detectável por scan estático + smoke test), o bug
passa a ser *esquecer o `withoutGlobalScope`* em jobs, console, landlord e agregações
cross-tenant — uma falha silenciosa que devolve resultado vazio ou escopo errado sem nenhum
sinal. Com dezenas de pontos legítimos de bypass (developer é multi-tenant por design), a
mágica custaria mais do que economiza. C perdeu porque deixa a invariante crítica sem árbitro
executável.

Enforcement: novo teste em `tests/Architecture/` (padrão `ModuleBoundaryTest`) que escaneia
`app/` e falha se um arquivo consultar model tenant-scoped sem referenciar `tenant_id`,
com allowlist para exceções justificadas (ex.: verificação pública de certificado). O
`TenantIsolationSmokeTest` (HTTP) segue como árbitro end-to-end.

## Consequências

- ✅ Modo de falha único e detectável; queries legíveis sem estado implícito; zero fricção em
  jobs/console/landlord.
- ✅ Invariante vira código (scan estático + smoke HTTP), não prosa.
- ❌ Verbosidade: todo call site novo repete o filtro; o teste de arquitetura é heurístico
  (scan textual, não semântico) e depende de allowlist honesta.
- ❌ Se um dia houver dezenas de módulos novos, o custo por call site cresce linearmente —
  revisitar via novo ADR se o cenário mudar.

## Links

- Spec afetada: [`../multi-tenancy.md`](../multi-tenancy.md) (seção "Escopo em Queries"
  atualizada neste mesmo commit — antes recomendava scope/trait).
- Auditoria: `docs/auditoria-correcoes-2026-07-11-pending.md` (item P1.6).
- Invariantes: `tests/Architecture/TenantIsolationSmokeTest.php`, novo `TenantScopingTest`.
