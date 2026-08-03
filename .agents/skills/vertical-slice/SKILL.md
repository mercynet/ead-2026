---
name: vertical-slice
description: Implementar uma fatia vertical completa (endpoint/feature API REST) do zero. Ative ao criar CRUD, novo recurso, novo domínio, ou feature REST que atravesse a stack completa — do banco ao controller — passando por Feature tests, FormRequest, Action, Resource e permissões.
---

# Vertical Slice

Disciplina para implementar uma feature API REST de ponta a ponta, respeitando o contrato em
`AGENTS.md` (invariantes não-negociáveis) e padrões documentados em `docs/specs/00-architecture/`.

## Quando usar

- Implementar um **novo endpoint/CRUD** (POST/GET/PATCH/DELETE).
- **Novo domínio ou recurso** que precisar de múltiplas ações (list, store, show, update, destroy).
- Nova **feature REST** que atravesse toda a stack (rota → controller → action → model → resource).

## Procedimento (Ordem TDD)

### 1. Permissões (Arquivo único de verdade)

Adicione em `config/permissions.php` (formato `domain.resource.action`; `developer` é **obrigatório**
em `user_types`):

```php
'assessment.questionnaires.list' => ['label' => 'Listar questionários', 'user_types' => ['developer', 'instructor']],
'assessment.questionnaires.create' => ['label' => 'Criar questionário', 'user_types' => ['developer', 'instructor']],
'assessment.questionnaires.view' => ['label' => 'Visualizar questionário', 'user_types' => ['developer', 'instructor']],
'assessment.questionnaires.update' => ['label' => 'Atualizar questionário', 'user_types' => ['developer', 'instructor']],
'assessment.questionnaires.delete' => ['label' => 'Deletar questionário', 'user_types' => ['developer', 'instructor']],
```

`RolesSeeder` **deriva** as roles de `user_types` no config (nada a editar no seeder).

> Policy, `Gate::define` no provider do módulo, teto efetivo por `UserType` e o debug de 403:
> use a skill **`rbac-permission-wiring`** — não repita a mecânica aqui.

### 2. Feature Tests ANTES (TDD)

Crie com: `php artisan make:test --pest Feature/Api/Assessment/QuestionnaireApiTest --no-interaction`
(convenção real: `tests/Feature/Api/<Módulo>/<Recurso>ApiTest.php` — sem `V1` no path).

Teste **happy path + erros**:
- ✅ 200 POST/GET/PATCH/DELETE com dados válidos.
- ✅ 401 sem auth.
- ✅ 403 sem permissão (Gate verifica).
- ✅ 422 validação (FormRequest falha).
- ✅ Tenant isolation (user A não vê recurso do tenant B).

Use a skill **`pest-api-tests`** — helpers de `tests/Pest.php` (`actingAsUserType()`,
`tenantHeaders()`, `assertApiErrorEnvelope()`, `assertTenantIsolation()`).

### 3. Rota no arquivo de área do módulo

**Não existe `routes/api.php` global**: a rota vive em `app/Modules/<M>/Routes/{api,admin,mzrt}.php`,
carregada pelo `Providers/<M>ServiceProvider`. Endpoint novo de produto nasce **área-first**
(`v1/admin`, `v1/student`, …) com o guard exato da área — escolha da área, stacks canônicas e
superfície pública estão na skill **`api-area-routing`**.

Exemplo abaixo = prefixo **legado** domínio-first (`v1/assessment`, sem guard de área), mantido só
como referência de estilo de grupo:

```php
Route::prefix('v1/assessment')
    ->middleware(['resolve.tenant.optional', 'api.context'])  // contexto sempre
    ->group(function (): void {
        Route::prefix('questionnaires')
            ->controller(QuestionnaireController::class)
            ->middleware([
                'tenant.required.unless.developer',  // tenant obrigatório exceto dev
                'auth:sanctum',                      // autenticação via token
                'tenant.access',                     // verifica acesso ao tenant
            ])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });
```

### 4. FormRequest para Validação

Crie: `php artisan make:request Assessment/StoreQuestionnaireRequest --no-interaction`

**Nunca inline no controller.** Defina `rules()`, `messages()`, e `bodyParameters()` (para Scribe):

```php
public function rules(): array {
    return [
        'title' => ['required', 'string', 'max:255'],
        'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];
}
```

### 5. Action com Regra de Negócio

Crie: `php artisan make:class Actions/Assessment/Questionnaire/StoreQuestionnaireAction --no-interaction`

```php
public function handle(StoreQuestionnaireRequest $request, ApiContext $context): Questionnaire
{
    $data = $request->validated();
    $data['tenant_id'] = $context->tenant?->id;
    $data['instructor_id'] = $context->user->id;

    return Questionnaire::query()->create($data);
}
```

**Regras invariantes:**
- Um `handle()` — responsabilidade única.
- Nenhuma query no controller — **toda regra aqui**.
- Tenant scoping: `$context->tenant->id` (nunca `where('tenant_id')`).
- Sem `abort()`, `try/catch` — exceptions custom em `app/Exceptions` renderizam centralmente.
- Injete dependências (clock, gateways) no construtor.

### 6. Controller LEAN (Orquestrador fino)

```php
public function __construct(
    private readonly StoreQuestionnaireAction $storeAction,
) {}

public function store(StoreQuestionnaireRequest $request, ApiContext $context): QuestionnaireResource {
    Gate::forUser($context->requiredUser())->authorize('assessment.questionnaires.create', [$context->requiredTenant()]);
    
    $questionnaire = $this->storeAction->handle($request, $context);
    
    return QuestionnaireResource::make($questionnaire);
}
```

**Proibido no controller** (`ControllerLeannessTest` guarda):
- `::query()`, `->where('tenant_id')`, `->paginate()` — query é papel da Action.
- `abort()`, `try/catch` — exceptions custom renderizam centralmente.

**Obrigatório:** constructor promotion das Actions (`private readonly`).

### 7. Eloquent API Resource

Crie: `php artisan make:resource Assessment/QuestionnaireResource --no-interaction`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'instructor' => [
            'id' => $this->instructor?->id,
            'name' => $this->instructor?->name,
        ],
        'created_at' => $this->created_at?->toIso8601String(),
    ];
}
```

Sucesso = Resource padrão Laravel (`{data: ...}`). Erro = `{data: null, errors: [{code, message}]}`
renderizado centralmente em `bootstrap/app.php` via exceptions custom — nunca montado no controller.

### 8. Padrões Especiais

- **Dinheiro**: inteiros em centavos (`*_cents`). `MoneyNeverFloatTest` guarda. Se a fatia toca
  order/payment/gateway/ledger, use a skill **`financial-money-flows`** antes de escrever a Action.
- **Listagens**: `cursorPaginate()` (não `paginate()`), eager-load relações → N+1.
- **Scribe**: `@unauthenticated` em PHPDoc bate com middleware real.
- **Side effect em outro módulo**: Domain Event (+ outbox quando o efeito é financeiro), nunca
  Eloquent cross-module.

## Anatomia da Fatia — Mapa de Arquivos

Exemplo canônico **Assessment / Questionnaire** (layout modular real):

```
app/Modules/Assessment/Routes/api.php                                        # Rota + stack (área: admin.php / mzrt.php)
app/Modules/Assessment/Providers/AssessmentServiceProvider.php               # Gate::define + registerRoutes + migrations
config/permissions.php                                                       # Permissions canônicas (domain.resource.action)
app/Modules/Assessment/Http/Controllers/QuestionnaireController.php          # Controller lean (authorize → action → resource)
app/Modules/Assessment/Http/Requests/StoreQuestionnaireRequest.php           # Validação + bodyParameters() para Scribe
app/Modules/Assessment/Actions/Questionnaire/StoreQuestionnaireAction.php    # Regra de negócio (handle + tenant scoping)
app/Modules/Assessment/Http/Resources/QuestionnaireResource.php              # Eloquent API Resource (toArray)
app/Modules/Assessment/Models/Questionnaire.php                              # Model (fillable, relations, casts)
app/Modules/Assessment/Policies/QuestionnairePolicy.php                      # Regra de instância
app/Modules/Assessment/Database/Migrations/*.php                             # Migration do módulo
tests/Feature/Api/Assessment/QuestionnaireApiTest.php                        # Feature tests (happy + 401/403/422)
```

Confira os nomes exatos num módulo vizinho antes de criar — a estrutura interna varia um pouco por módulo.

## Fechar

1. `./vendor/bin/sail vendor/bin/pint --dirty --format agent` — formato canônico.
2. Feature focado: `./vendor/bin/sail artisan test --compact --filter=QuestionnaireApiTest`.
3. Invariantes: `./vendor/bin/sail artisan test --compact --testsuite=Architecture`.
4. Fatia que fecha task/endpoint: E2E HTTP real (skill **`endpoint-e2e`**).

## Regras

- **Economia de modelo** (ver `AGENTS.md`): os passos mecânicos da fatia (FormRequest, Resource,
  factory, seeder, casos repetitivos de teste) podem ser rascunhados por subagente de modelo
  barato (ex.: Haiku) com prompt apontando os arquivos-exemplo desta skill. Action, controller,
  rota/middleware e a revisão final ficam no modelo principal. Revisar sempre antes de commitar.
- **Código vence prosa**: se conflitar com spec, corrija a spec e cite no commit.
- **Sem repositório sobre Eloquent** — Eloquent já é a camada.
- **Sem interface de implementação única** — abstração só nas 3 costuras (PaymentGateway, MediaProvider, Plugin).
- **Tenant isolation**: sempre usar `$context->tenant` na Action.
- **Sem facade estática em regra** — injete para testabilidade.
