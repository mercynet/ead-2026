---
layer: architecture
applies-to: all-domains
maturity: stable
last-reviewed: 2026-06-13
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
  cobrança distinta do billing tenant→aluno; ver §"Duas camadas de billing").
- **Provisionamento de plugins:** Mzrt é o **único** que cria/disponibiliza plugins. Nunca
  abriremos para terceiros criarem. Define o catálogo e o *range* que cada tenant pode ativar.
- Vê e gerencia qualquer tenant; único que edita permissions e `UserType`.

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

## URLs

**Área-first**, domínio implícito no recurso (o domínio é organização de código, não da URL):

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

## Área × RBAC

A área restringe **qual superfície** o usuário alcança; o RBAC restringe **o que ele faz** lá.
Cada grupo de rota de área aplica um guard que valida `UserType × área`:

| Área | `UserType` permitido | Auth |
|------|----------------------|------|
| `mzrt` | `developer` | obrigatória |
| `admin` | `admin` (+ `developer` por hierarquia) | obrigatória |
| `instructor` | `instructor` (+ acima) | obrigatória |
| `student` | `student` (+ acima) | obrigatória |
| `home` | qualquer / anônimo | opcional |

Dentro da área, a permission (`<domain>.<resource>.<action>` / `<plugin>.<resource>.<action>`)
decide a ação. Hierarquia de `UserType` (`rbac.md` §1) ainda vale: um `developer` pode entrar em
áreas abaixo.

> **Sub-decisão (B) — FECHADA:** middleware `area.guard:{area}` dedicado (legível, testável por
> invariante), não reuso de `tenant.required.unless.developer` + checagem ad-hoc de `UserType`.

## Três camadas de billing

| Camada | Quem cobra/paga quem | Área | Domínio |
|--------|----------------------|------|---------|
| **Plataforma** | Mzrt → tenant (assinatura SaaS + plugins) | Mzrt | `50-ecosystem-plugins` / `40-financial` |
| **Venda** | tenant → aluno (curso avulso / assinatura) | Admin / Student / Home | `40-financial` |
| **Comissão** | tenant/plataforma → instrutor (repasse, `commission_rate`) | Admin / Instructor | `40-financial` |

São fluxos distintos e não devem se misturar no payload nem no controller. A 3ª camada
(comissão de instrutor) vem do modelo do `../eadIA` (`Commission` + `commission_rate`) e ainda
**não existe** no ead2026 — entra como domínio do Financial (ver `tasks.md` do domínio).

## Impacto e migração (não é rewrite imediato)

Os endpoints atuais são **domínio-first** e **persona-borrados** (ex.: `GET /v1/learning/courses/{id}`
usa `learning.courses.view`, permission ampla que as 4 personas têm). Sob este modelo:

- **Novos endpoints** nascem área-first.
- **Existentes** migram incrementalmente (slice a slice), não de uma vez. Mapa de migração e
  ordem ficam no `tasks.md` de cada domínio + `ROADMAP.md`.
- **Home é a última área** a implementar (decisão de produto).
- O slice recém-entregue `GET /v1/learning/courses/{id}` será re-slotado em `admin` e/ou
  `instructor`/`student` com Resources distintos (hoje serve as 4 personas no mesmo payload — o
  sintoma que motivou esta spec).

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
