---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# Convenções de API

Regras escritas **uma vez** aqui. As specs de domínio devem **linkar** para esta página em vez
de repetir estes padrões.

## Formato REST

- JSON padrão, endpoints pragmáticos e semânticos (`index`, `show`, `store`, `update`, `destroy`).
- Sem `__invoke` como padrão de domínio — controllers têm métodos explícitos.
- Versionamento por prefixo: `api/v1/<domain>`.

## Envelope de Erro

Erros usam formato unificado, com render centralizado em `bootstrap/app.php`:

```json
{ "data": null, "errors": [ { "code": "tenant_not_resolved", "message": "..." } ] }
```

- `tenant_not_resolved` deve ser emitido por exceção de domínio (`TenantContextRequiredException`),
  nunca construído manualmente em múltiplos lugares no controller.
- Exceções de gateway/integração externa devem ser capturadas e traduzidas para PT-BR — nunca
  repassar exception crua em inglês para o cliente.

## Paginação por Cursor

Toda listagem (`index()`) usa `cursorPaginate` e retorna uma Resource Collection diretamente.

## ApiContext Pattern (obrigatório)

Value Object injetado via middleware encapsulando `$user` e `$tenant`. Controllers e Actions
recebem `ApiContext` como parâmetro — **nunca** acessam request/tenant manualmente.

```php
// app/Http/Context/ApiContext.php
final readonly class ApiContext
{
    public function __construct(
        public ?User $user,
        public ?Tenant $tenant,
    ) {}

    public function hasUser(): bool { return $this->user !== null; }
    public function hasTenant(): bool { return $this->tenant !== null; }
    public function requiredUser(): User { /* ... */ }
    public function requiredTenant(): Tenant { /* ... */ }
}

// Controller — injetar ApiContext no método
public function index(ApiContext $context): JsonResponse
{
    Gate::forUser($context->user)->authorize('core.users.list', [$context->tenant]);
    $result = $this->action->handle($context);

    return UserResource::collection($result)->toResponse(request());
}

// Action — receber ApiContext
public function handle(ApiContext $context): CursorPaginator
{
    if ($context->user->isDeveloper()) { /* ... */ }
}
```

## Response Pattern (obrigatório)

```php
return UserResource::collection($paginator);                              // coleção paginada
return UserResource::make($model);                                        // recurso único
return UserResource::make($model)->toResponse(request())->setStatusCode(201); // status custom
return new JsonResponse(['data' => $payload]);                            // payload manual (login etc.)
```

Tipos de retorno recomendados:

- `Resource::collection()` → `AnonymousResourceCollection`
- `Resource::make()` → o tipo do Resource
- payload manual → `JsonResponse`

## FormRequest para Filtros (obrigatório em listagens)

Todo `index()` usa um FormRequest para validar os filtros de query string, com `queryParameters()`
para documentação Scribe.

```php
class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:admin,instructor,student'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'search' => ['description' => 'Filtrar por nome ou email', 'example' => 'john'],
            'role' => ['description' => 'Filtrar por role', 'example' => 'student'],
        ];
    }
}
```

## Controller Lean / Action Layer (guardrails)

- Controllers só orquestram: `ApiContext` + `Gate/Policy` + Action + Resource.
- Regra de negócio em `app/Actions/<Domain>/<Resource>/...`, separando leitura (Query) de escrita (Command).
- Decisão condicional de autorização (ex.: `is_system`) fica em **Policy**, não em `if` de controller.
- Não repetir checks de contexto/infra (tenant, auth, payload de erro) no método — isso vive em
  middleware, FormRequest e exceções centralizadas.

## API-DX (limpeza de payload)

- Não retornar `meta` vazio (`'meta' => []` é proibido). `meta` só existe quando houver metadados reais.
- Evitar wrappers redundantes (`data.user`, `data.course`, `data.category`) em respostas manuais.
- Não usar `->resolve()` em Resources — usar `->toResponse(request())`.
- Não retornar `response(Resource::collection(...)->response()->getData(true))` — usar `->toResponse(request())` direto.

## Valores Monetários

Valores monetários transitam em **inteiros de centavos** (`price_cents`) para evitar problemas de
ponto flutuante. Quando for inevitável usar base decimal legada, usar tipos decimais estritos com
precisão `X.YY`. Ver `40-financial/spec.md`.
