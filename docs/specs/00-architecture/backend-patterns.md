---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

> **Estado reconciliado em 2026-09-05:** o layout canônico atual é `app/Modules/{Core,Learning,Assessment,Financial,Ecosystem}`
> com `app/Shared`. `app/Plugins/` e `app/Support/Ports/` são históricos/superseded e não devem
> receber trabalho novo. A dívida Eloquent cross-module existente continua congelada na allowlist;
> não foi refatorada nem resolvida por esta reconciliação. A decisão de tenant vigente é a do ADR-004
> e do `TenantScopingTest`.

# Padrões de Backend

Como o código é organizado e por quê. Princípio-guia: **YAGNI + SOLID juntos** — estrutura
suficiente para manter cada domínio isolado e testável, sem abstração especulativa.

## Arquitetura: Modular Monolith por Bounded Context

A aplicação é um monólito modular. Cada domínio é um **módulo** dono do seu próprio código.
Não é DDD tático completo, nem Clean/Hexagonal em tudo — Laravel idiomático **dentro** do módulo,
fronteiras explícitas **entre** módulos.

```
app/
  Modules/
    Core/         # 10-core-identity: auth, users, tenants
    Learning/     # 20-catalog-learning
    Assessment/   # 30-assessment
    Financial/    # 40-financial
    Ecosystem/    # 50-ecosystem-plugins
  Shared/         # ApiContext, base Controller/Action, Contracts cross-module, exceptions
  Support/        # somente suporte transversal já existente, como DependencyAudit
```

Cada módulo contém o que precisa, espelhando o spec do domínio:

```
Modules/<Module>/
  Models/         Actions/<Resource>/<Verb>Action.php
  Http/Controllers/  Http/Requests/  Http/Resources/
  Policies/       Routes/api.php     Events/  Listeners/
  Database/Migrations/                    # migrations do domínio (loadMigrationsFrom no provider)
  Providers/<Module>ServiceProvider.php   # registra rotas/gates/migrations do módulo
```

Migrations **sem dono de domínio** (framework: cache/jobs; pacotes: sanctum, spatie permission /
activitylog / medialibrary) ficam em `database/migrations/` — idem as tabelas de plugins até o
módulo `Ecosystem` existir. `php artisan make:migration` para tabela de módulo exige
`--path=app/Modules/<M>/Database/Migrations`.

PSR-4 já cobre (`App\` → `app/`), então `App\Modules\Core\...` funciona sem mudança no composer.
As rotas de cada módulo são carregadas por um service provider fino (do módulo ou um
`ModulesServiceProvider` que itera os módulos) — sem registrar tudo num `routes/api.php` gigante.

## Fronteira de Módulo (a regra que vira invariante)

> Um módulo **nunca** importa Model/Action/interno de outro módulo. Comunicação cross-module
> só por **Domain Event** ou **Contract** (interface em `Shared/Contracts` ou exposta pelo módulo).

**Exceção — shared kernel:** `Core\Models` e `Core\Enums` (identidade/tenancy: `User`, `Tenant`,
`UserType`) podem ser importados por qualquer módulo — todo agregado é tenant-scoped e referencia
usuário; abstrair isso atrás de contract seria cerimônia sem ganho. A contrapartida: **Core não
importa nada de módulo nenhum** (é a base do grafo).

Exemplo canônico (spec Financial × Learning):
- `Financial` despacha `OrderPaidEvent`.
- `Learning` escuta e matricula via seu próprio serviço.
- `Financial` **não** conhece `Learning\Models\Enrollment`.

Isso mantém os módulos desacoplados e o grafo de dependência acíclico. Enforçado por
`ModuleBoundaryTest` (ver `testing-strategy.md`), que também congela a **dívida herdada** do
código flat (relações Eloquent cross-module, ex.: `Certificate → Course`,
`User → QuizAttempt`) numa allowlist que não pode crescer — o alvo é convertê-la em
Events/Contracts.

## Costuras plugáveis — apenas onde paga (3 costuras)

Abstração via interface **só** onde há (ou haverá comprovadamente) mais de uma implementação:

| Costura | Local canônico atual | Estado |
|---------|-----------------------|--------|
| `PaymentGateway` | `app/Modules/Financial/Gateways/{Contracts,Adapters,Data}` | manager e resolvers existentes; adapters adicionais são futuros |
| `MediaProvider` | módulo dono da mídia, conforme o slice | alvo a construir; não criar `app/Support/Ports` |
| `Plugin` | `app/Modules/Ecosystem` | catálogo, activation, config e contracts existentes |

**Em qualquer outro lugar: Eloquent direto.** Sem repositório sobre Eloquent (o Eloquent já é a
camada de dados), sem interface para implementação única, sem CQRS/event-sourcing.

## Camadas dentro do módulo

`Route → Controller (fino) → Action → Model → Resource`. Detalhe do contrato HTTP em
[`api-conventions.md`](api-conventions.md). Resumo das regras não-negociáveis:

- **Controller fino**: injeta `ApiContext`, autoriza via Gate/Policy, chama a Action, devolve
  Resource. Sem query, sem `where('tenant_id')`, sem regra de negócio, sem `try/catch` morto.
- **Action**: um `handle()`, responsabilidade única. Usa `fill()` + `$fillable` (não whitelist
  manual). Regra de domínio (ex.: transição `published_at`) tem teste nomeado.
- **Autorização**: um único estilo — `Gate::forUser($ctx->requiredUser())->authorize($ability, ...)`.

## YAGNI × SOLID — como decidir

**SOLID onde paga:**
- **SRP** — Actions de uma responsabilidade; controllers só orquestram.
- **DIP** — dependa de `Contract`/`Port` **só** nas 3 costuras acima; injete pelo construtor.
- **OCP** — adicionar um gateway/provider = novo adapter, sem tocar no core.

**YAGNI em todo o resto:**
- Sem repositório, sem interface de implementação única, sem value object especulativo,
  sem camada de "use case" separada da Action.
- Adicione abstração quando o segundo caso real aparecer — não antes.

**Testabilidade (inegociável):**
- Dependências injetadas no construtor (promotion) — nada de `new` escondido em regra.
- Sem facade estática dentro de regra de negócio; injete (clock, ids, gateways) para permitir
  **unit test sem DB** onde a lógica é pura.
- Actions puras (entrada → saída determinística) são o alvo: testar regra sem subir HTTP/DB.

## Eventos de domínio

Nomes e payloads vivem na `spec.md` de cada domínio (seção *Events*). Despachados pela Action;
processados de forma assíncrona via fila (RabbitMQ) — mecânica em
[`performance-scalability.md`](performance-scalability.md). Eventos são também a fronteira
cross-module (ver acima).

## Migração do código atual

**O layout atual é o de cinco módulos:** `Core`, `Learning`, `Assessment`, `Financial` e
`Ecosystem`, além de `app/Shared`. Cada módulo registra gates, migrations e rotas no seu
`Providers/<M>ServiceProvider` (ver `bootstrap/providers.php`); não existe `routes/api.php` global.
Os invariantes de fronteira de módulo (`ModuleBoundaryTest`) e controller-lean
(`ControllerLeannessTest`) permanecem ativos, sem skip. A dívida Eloquent cross-module herdada
continua explicitamente na allowlist e deve ser tratada no WS3, não maquiada nesta reconciliação.
