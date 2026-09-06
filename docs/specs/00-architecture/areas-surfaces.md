---
layer: architecture
applies-to: all-domains
maturity: stable
last-reviewed: 2026-07-29
owners: [paulo]
related:
  - rbac.md
  - api-conventions.md
  - backend-patterns.md
  - multi-tenancy.md
  - security-privacy-lgpd.md
---

# Áreas & Superfícies

> **Status: RATIFICADA (2026-06-13).** Decisão fundamental de produto+arquitetura. Define as
> **áreas** (audiências) do sistema e como elas se separam na API. Sub-decisões fechadas:
> **(A) URL área-first puro**, **(B) middleware `area.guard` dedicado**, **(D) re-slot só `/admin`
> primeiro**. **(C)** (estratégia de Resource) decide-se ao implementar o 1º recurso multi-área.

> **Referência:** o projeto anterior `../eadIA` (Laravel+Filament) é a referência de **modelo de
> domínio, plugins e financeiro** — **não** de áreas. O plano de painéis do eadIA está **errado**
> (ver §"Caveats do eadIA"): o que ele chama de **"Desktop"** é o painel **público/visitante**
> (nossa área **Home**) e o **"dashboard"** é o **Admin do tenant**. A definição canônica das áreas
> é **esta spec**.

## Intent / Why

O sistema atende **5 audiências completamente distintas**. Cada uma tem objetivo, escopo de
dados, contrato de payload e (no futuro) frontend próprios. Tratá-las como uma API genérica
diferenciada só por permission borra contratos, vaza campos errados e empilha `if` de persona em
cima dos `if` de plugin. Este documento torna a **área** um conceito de primeira classe.

Não há frontend hoje, mas haverá vários (web admin, app mobile do aluno, painel Mzrt, site
público). A área é o **contrato estável** que cada um desses clients consome. Por isso precisa
estar definida antes, não depois.

## Planejamento operacional

Área/persona define valor, contrato e ordem das jornadas; domínio limitado define ownership de
código. Ver [ROADMAP](../../ROADMAP.md) e
[ADR-006](decisions/006-planejamento-por-jornadas-de-area.md). Execução é por fatias verticais:
endpoint é unidade de execução, mas jornada é unidade de sucesso.

Mzrt começa cedo apenas com walking skeleton de control plane (tenant, primeiro admin, status e
limits/entitlements). Marketplace, billing da plataforma e expansão de plugins ficam para etapa
posterior, conforme demanda; não se completa todo Mzrt antes de Admin, Student e Instructor.

## As 5 Áreas

| # | Área | Persona / `UserType` | Scope de dados | Frontend(s) futuro(s) |
|---|------|----------------------|----------------|------------------------|
| 1 | **Mzrt** (Mozart) | `developer` | **Global**, multi-tenant | Painel interno da equipe |
| 2 | **Admin** | `admin` | Só o próprio tenant | Painel administrativo do tenant |
| 3 | **Instructor** | `instructor` | Só conteúdo próprio (`own`) | Painel do instrutor |
| 4 | **Student** | `student` | Só o que contratou + consumo próprio | App/web do aluno |
| 5 | **Home** (público) | anônimo / qualquer | Vitrine pública | Site público / landing |

> A área **não substitui o RBAC** — o RBAC (`rbac.md`) continua sendo o teto de permissão. A área
> é a **superfície**; a permission é o **direito**. Um request precisa de ambos: estar na área
> certa **e** ter a permission. Ver §"Área × RBAC".

### 1. Mzrt — equipe Mozart

Onde a equipe dev gerencia **tudo**, multi-tenant, com **logs e auditoria sérios** e LGPD/segurança
sempre (ver `security-privacy-lgpd.md`). Particularidades exclusivas:

- **Billing Mzrt → tenant:** cobra os tenants pela plataforma e pelos plugins assinados (camada de
  cobrança distinta do billing tenant→aluno; ver §"Três camadas de billing").
- **Provisionamento de plugins:** Mzrt é o **único** que cria/disponibiliza plugins. Nunca
  abriremos para terceiros criarem. Define o catálogo e o *range* que cada tenant pode ativar.
- Vê e gerencia qualquer tenant; único que edita permissions e `UserType`.

> **Contrato-alvo, migração em curso:** esta seção define superfície pretendida; não afirma que
> todos endpoints, limites ou operações Mzrt já existem.

### 2. Admin — administrador do tenant

Vê **tudo do próprio tenant e só dele**, sob os mesmos critérios de auditoria/LGPD. Particularidade:

- **Gerencia plugins dentro do range que o Mzrt liberou** — ativa/configura o que está no teto da
  assinatura do tenant; não cria plugin nem ultrapassa o range.

### 3. Instructor — criador de conteúdo

Gerencia **todos os seus** cursos, módulos, aulas, questões, testes e o **progresso dos seus
alunos**. Tudo `own`: nunca alcança conteúdo de outro instrutor.

### 4. Student — aluno

Acessa **tudo que contratou** — compra avulsa, plano de assinatura, etc. Consumo próprio (aulas,
progresso, certificados). Só vê dados de terceiros em **contexto de plugin** (forum, ranking);
nunca edita o de outra pessoa (ver `rbac.md` §1).

### 5. Home — público

**Última a ser implementada.** Vitrine sem login: catálogo, banners de destaque, cursos em
destaque, entrada do funil de compra. É o que hoje vive em `v1/learning/catalog/*` e será
re-slotado para a área Home.

## Decisão: separação por **namespace de área** (não por permission-if)

A API segmenta por **área primeiro**, não por domínio. A separação é **real** (rota + controller +
Resource por audiência), não condicional em runtime.

### Por quê (resumo da ponderação)

1. **Branch declarativo, não runtime.** Cada área monta suas próprias rotas; zero `if` de persona
   no controller. Plugins já injetam muitas permissions — empilhar branching de persona em cima
   seria explosão combinatória de `if`/eventos.
2. **Contrato explícito por audiência.** Payloads divergem de verdade (Student vê só publicado +
   entitlement; Admin vê drafts/stats; Mzrt vê multi-tenant + auditoria). Resource por área é
   legível e versionável; Resource único cheio de `when()` é frágil.
3. **Segurança/LGPD por construção.** Campo de área errada é fisicamente inalcançável de outra
   área — não depende de lembrar um `if`.
4. **Multi-frontend.** Cada client consome 1 namespace de área (padrão BFF).

### A repetição é controlada — cortamos a fronteira no lugar certo

```
COMPARTILHADO (1×, no módulo de domínio)   |  POR ÁREA (a borda que DEVE divergir)
-------------------------------------------|----------------------------------------
Model                                      |  Route file
Action (regra de negócio)                  |  Controller (fino — chama a Action)
Policy                                     |  FormRequest
Events / migrations                        |  Resource (contrato de payload)
                                           |  Query scope (quais linhas a área enxerga)
```

A duplicação que sobra (rota+controller+Resource finos) é **intencional**: é onde o contrato de
cada audiência diverge. **Regra de negócio nunca é duplicada** — vive na Action compartilhada.

## Estrutura de código

Área é **sub-namespace de borda dentro de cada módulo de domínio** — não um módulo top-level
cruzando domínios (a lógica de `Course` é coesa em Learning). Áreas tocam só `Http/` + `Routes/`.

```
app/Modules/Learning/
  Http/Controllers/
    Mzrt/CourseController.php
    Admin/CourseController.php
    Instructor/CourseController.php
    Student/CourseController.php
    Home/CourseController.php
  Http/Resources/
    Mzrt/…  Admin/…  Instructor/…  Student/…  Home/…
  Http/Requests/
    Admin/…  Instructor/…           # por área onde a validação diverge
  Routes/
    mzrt.php  admin.php  instructor.php  student.php  home.php
  Actions/        # COMPARTILHADO entre áreas
  Models/         # COMPARTILHADO
  Policies/       # COMPARTILHADO
  Events/         # COMPARTILHADO
```

O `ModuleBoundaryTest` continua valendo: áreas não cruzam módulos; um controller de área importa
só Actions/Models do próprio domínio (+ contratos compartilhados).

## Taxonomia de URLs

**Produto por persona** é área-first; domínio é organização de código, não URL:

```
/api/v1/{area}/{resource}/...

/api/v1/mzrt/courses          /api/v1/mzrt/tenants        (billing Mzrt→tenant)
/api/v1/admin/courses         /api/v1/admin/plugins       (dentro do range)
/api/v1/instructor/courses    /api/v1/instructor/students
/api/v1/student/courses       /api/v1/student/enrollments
/api/v1/home/courses          /api/v1/home/highlights     (público, sem auth)
```

> **Sub-decisão (A) — FECHADA:** `v1/{area}/{resource}` (área-first puro, domínio fora da URL). O
> domínio é organização de código, não da URL.

Rotas técnicas/cross-area usam namespace neutro explícito quando necessário: `/api/v1/auth/*`,
`/api/v1/webhooks/*` e `/api/v1/public/*`. Para autenticação, `/api/v1/auth/*` é o
`TARGET_CANONICAL` público e está implementado. A superfície `/api/v1/core/auth/*` permanece como
`CURRENT_IMPLEMENTED` e `LEGACY_COMPATIBILITY` durante a v1, com a mesma semântica, middleware e
throttling; sua remoção não tem data artificial e exige inventário de consumidores e decisão
explícita.

Prefixos existentes `/api/v1/core/*`,
`/api/v1/learning/*` e `/api/v1/assessment/*` são rotas legadas; não se tornam conformes por serem
versionadas. Inventário, alvo e condição de remoção vivem no [ROADMAP](../../ROADMAP.md#inventário-agrupado-de-migração-legada).

## Área × RBAC

A área restringe **qual superfície** o usuário alcança; o RBAC restringe **o que ele faz** lá.
Cada grupo de rota de área aplica um guard que valida `UserType × área`:

| Área | `UserType` permitido | Auth |
|------|----------------------|------|
| `mzrt` | `developer` | obrigatória |
| `admin` | `admin` | obrigatória |
| `instructor` | `instructor` | obrigatória |
| `student` | `student` | obrigatória |
| `home` | qualquer / anônimo | opcional |

Dentro da área, permission (`<domain>.<resource>.<action>` / `<plugin>.<resource>.<action>`)
decide ação. Hierarquia de `UserType` em [`rbac.md`](rbac.md) é teto de autorização, mas não altera
silenciosamente contrato de persona: developer não passa a consumir payload Admin/Instructor/Student
por herança. Override operacional de developer deve ser explícito no endpoint e auditado. Atuar como
outra persona requer impersonação futura, explícita e auditada; não usar hierarquia como
impersonação implícita.

> **Sub-decisão (B) — FECHADA:** middleware `area.guard:{area}` dedicado (legível, testável por
> invariante), não reuso de `tenant.required.unless.developer` + checagem ad-hoc de `UserType`.
> Implementação atual é migratória: somente slices entregues devem alegar enforcement; tabela e
> regras acima são contrato-alvo até cada rota ser migrada e testada.

## Três camadas de billing

| Camada | Quem cobra/paga quem | Área | Domínio |
|--------|----------------------|------|---------|
| **Plataforma** | Mzrt → tenant (assinatura SaaS + plugins) | Mzrt | `50-ecosystem-plugins` / `40-financial` |
| **Venda** | tenant → aluno (curso avulso / assinatura) | Admin / Student / Home | `40-financial` |
| **Comissão** | tenant/plataforma → instrutor (repasse, `commission_rate`) | Admin / Instructor | `40-financial` |

São fluxos distintos e não devem se misturar no payload nem no controller. A 3ª camada
(comissão de instrutor) vem do modelo do `../eadIA` (`Commission` + `commission_rate`) e ainda
**não existe** no ead2026 — entra como domínio do Financial (ver `tasks.md` do domínio).

> **Três camadas, dois ledgers.** Das três camadas, **duas são ledgers de Order** — Plataforma
> (`platform_orders`) e Venda (`orders`) — tabelas irmãs, mesmo padrão, **nunca a mesma tabela**
> (pagador/gateway/escopo/LGPD divergem). A **Comissão** não é ledger próprio: é **repasse derivado**
> de uma venda confirmada. O *porquê* e o schema vivem em
> [`decisions/003-billing-dois-ledgers-itemable-seam.md`](decisions/003-billing-dois-ledgers-itemable-seam.md)
> (canônico) — não reabrir aqui.

## Impacto e migração (não é rewrite imediato)

Endpoints atuais domínio-first são legados e persona-borrados. Sob este modelo:

- **Novos endpoints de produto** nascem área-first; técnicos/cross-area seguem taxonomia neutra.
- **Existentes** migram incrementalmente (slice a slice), não de uma vez. Mapa de migração e
  ordem ficam no `tasks.md` de cada domínio + `ROADMAP.md`.
- **Home é a última área** a implementar (decisão de produto).
- Admin já entregou `GET /api/v1/admin/courses/{id}` e publish/unpublish. Re-slot de Instructor e
  Student continua pendente. Destino final e remoção/depreciação de `GET /api/v1/learning/courses/{id}`
  ainda são decisão pendente; não presumir que todo legado migra para todas áreas.

## Caveats do eadIA (referência) — o que NÃO copiar

O `../eadIA` é referência de **domínio/plugins/financeiro**, mas o plano de **áreas/painéis** dele
está errado. Ao consultar o eadIA:

- **"Desktop"** (eadIA) = painel **público/visitante** = nossa área **Home**. Não é uma área de
  usuário logado.
- **"dashboard"** (eadIA) = painel do **Admin do tenant** = nossa área **Admin**.
- eadIA mistura `super_admin/tenant_admin` em config de roles ad-hoc; nosso RBAC canônico é
  `rbac.md` (UserType enum + Spatie). Não importar o esquema de roles do eadIA.
- eadIA é **Filament (frontend + monolito)**; ead2026 é **API-first modular**. Reusar **design e
  regras de negócio**, não código de painel.

## Decisões de modelo importadas (do eadIA, já ratificadas)

- **Eixo único = Tenant.** ead2026 é **multi-tenant**, não multi-país. `Country`/locale/moeda são
  atributos dentro do tenant, nunca eixo de isolamento. (eadIA antigo usava Country — descartado.)
- **Comissão de instrutor é domínio.** Modelar `Commission` + `commission_rate` no Financial
  (3ª camada de billing). Ver §"Três camadas de billing".
- **Conteúdo i18n traduzível.** `title`/`description` de Course/Module/Lesson/Category traduzíveis
  (JSON por locale, como no eadIA). Afeta migrations/Resources — registrar no `tasks.md` do
  domínio Catalog & Learning.

## Open Questions

- ~~(A) Formato de URL~~ — **FECHADA (2026-06-13):** área-first puro `v1/{area}/{resource}`.
- ~~(B) Guard de área~~ — **FECHADA (2026-06-13):** middleware `area.guard` dedicado.
- (C) Estratégia anti-repetição de Resource: base Resource compartilhada + subclasses por área,
  vs Resources independentes por área. **Aberta** — decidir ao implementar o 1º recurso multi-área.
- ~~(D) Ordem de migração~~ — **FECHADA (2026-06-13):** re-slot incremental, começando por
  `/admin` do `GET /courses/{id}`; instructor/student/home viram slices separados depois.
