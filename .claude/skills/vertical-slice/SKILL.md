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

Adicione em `config/permissions.php` (formato `domain.resource.action`):

```php
'assessment.questionnaires.list' => ['label' => 'Listar questionários', 'user_types' => ['instructor']],
'assessment.questionnaires.create' => ['label' => 'Criar questionário', 'user_types' => ['instructor']],
'assessment.questionnaires.view' => ['label' => 'Visualizar questionário', 'user_types' => ['instructor']],
'assessment.questionnaires.update' => ['label' => 'Atualizar questionário', 'user_types' => ['instructor']],
'assessment.questionnaires.delete' => ['label' => 'Deletar questionário', 'user_types' => ['instructor']],
```

`PermissionDriftTest` guarda que config + seeder + gates combinam.

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

### 3. Rota em `routes/api.php`

Copie a pilha de middleware de grupo existente (Assessment exemplo):

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
    Gate::forUser($context->user)->authorize('assessment.questionnaires.create', [$context->tenant]);
    
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

- **Dinheiro**: inteiros em centavos (`*_cents`). `MoneyNeverFloatTest` guarda.
- **Listagens**: `cursorPaginate()` (não `paginate()`), eager-load relações → N+1.
- **Scribe**: `@unauthenticated` em PHPDoc bate com middleware real.

## Anatomia da Fatia — Mapa de Arquivos

Exemplo canônico **Assessment / Questionnaire**:

```
routes/api.php                                                      # Rota + middleware stack
config/permissions.php                                              # Permissions canônicas (domain.resource.action)
app/Http/Controllers/Api/V1/Assessment/QuestionnaireController.php # Controller lean (authorize → action → resource)
app/Http/Requests/Assessment/StoreQuestionnaireRequest.php         # Validação + bodyParameters() para Scribe
app/Actions/Assessment/Questionnaire/StoreQuestionnaireAction.php  # Regra de negócio (handle + tenant scoping)
app/Http/Resources/Assessment/QuestionnaireResource.php            # Eloquent API Resource (toArray)
app/Models/Questionnaire.php                                        # Model (fillable, relations, casts)
tests/Feature/Api/Assessment/QuestionnaireApiTest.php               # Feature tests (happy + 401/403/422)
```

## Fechar

1. `vendor/bin/pint --dirty --format agent` — formato canônico.
2. Rodar Feature focado: `docker exec ead2026-laravel.test-1 php artisan test --compact --filter=QuestionnaireApiTest`.
3. Suite Architecture (invariantes): `docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact`.

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
