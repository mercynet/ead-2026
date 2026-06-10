---
name: pest-api-tests
description: Escreve testes de API neste harness. Cobre Feature (endpoints HTTP + DB com helpers), E2E (fluxos cross-module), Architecture (invariantes canônicos), e envelope de erro padrão. Ativa em Feature tests de API, tenant isolation, testes de permissão, ou quando mencionar teste de endpoint/API.
---

# Pest API Tests

Skill específica do harness deste repo para testes de API em Pest 4.
**Não duplica** `pest-testing` (genérica) — é especializada em padrões e helpers locais.

## Quando usar

- Escrevendo **Feature tests de endpoint** (`tests/Feature/Api/<Module>`).
- Testando **tenant isolation** ou **permission edge**.
- Usando helpers do harness: `actingAsUserType()`, `tenantHeaders()`, `assertApiErrorEnvelope()`, `assertTenantIsolation()`.
- Debugando testes de API que falham.

## Estrutura

### Feature — o grosso da cobertura

Cada endpoint Feature test cobre:
1. **Happy path**: 200/201 + shape do Resource.
2. **401**: não autenticado (via `actingAsUserType()`).
3. **403**: autenticado sem permissão.
4. **422**: validação (FormRequest).
5. **Tenant isolation**: tenant B não alcança recurso de A.
6. **Permission edge**: UserType que pode vs não pode.

Não repita `uses(RefreshDatabase::class)` em Feature — já está aplicado globalmente em `tests/Pest.php`.

### E2E — fluxos cross-module

Poucos testes de alto valor em `tests/E2E/`:
- Checkout → webhook pagamento → `OrderPaidEvent` → matrícula → progresso → certificado.
- Jornada completa do aluno.

RefreshDatabase já aplicado. Use `actingAsUserType()` para setup.

### Architecture — invariantes

Em `tests/Architecture/`, cada invariante é scan barato (sem DB por padrão). 
Adicione `uses(RefreshDatabase::class)` só se o teste tocar banco.
Vide `ErrorEnvelopeShapeTest.php` e `TenantIsolationSmokeTest.php`.

## Helpers — boilerplate zero

**`tests/Pest.php`** fornece:

```php
// Semeia permissions e roles canônicas
seedRbac();

// Cria um tenant
$tenant = makeTenant(['domain' => 'tenant-a.local']);

// Monta headers tenant-scoped + Bearer token
$headers = tenantHeaders($tenant, $token);

// Cria usuário do tipo + role + token + headers em 1 linha
[$user, $headers] = actingAsUserType(UserType::Admin, $tenant);
[$user, $headers] = actingAsUserType(UserType::Developer); // Developer é global (sem tenant)

// Asserta envelope {data:null, errors:[{code,message}]}
assertApiErrorEnvelope($response, 422, 'codigo');

// Asserta acesso cross-tenant negado (403 ou 404)
assertTenantIsolation($response);
```

## Exemplo: Feature test com permissão + tenant isolation

```php
<?php
use App\Enums\UserType;
use App\Models\Questionnaire;

it('lists questionnaires as admin', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    Questionnaire::factory()->count(3)->create([
        'tenant_id' => $admin->tenant_id,
    ]);

    $response = $this->getJson('/api/v1/assessment/questionnaires', $headers);

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['*' => ['id', 'title', 'type']]]);
});

it('denies student creating questionnaires', function (): void {
    [$student, $headers] = actingAsUserType(UserType::Student);

    $response = $this->postJson('/api/v1/assessment/questionnaires', [
        'title' => 'x',
    ], $headers);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('denies admin B reaching questionnaire de admin A', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    $quiz = Questionnaire::factory()->create(['tenant_id' => $tenantA->id]);
    [$adminB, $headers] = actingAsUserType(UserType::Admin, $tenantB);

    $response = $this->getJson("/api/v1/assessment/questionnaires/{$quiz->id}", $headers);

    assertTenantIsolation($response);
});
```

## UserTypes

Vide `App\Enums\UserType`:
- `Developer` — global, sem tenant, acessa tudo.
- `Admin` — tenant-scoped, gerencia conteúdo.
- `Instructor` — tenant-scoped, cria aulas.
- `Student` — tenant-scoped, consome aulas.

## Envelope de erro — cobertura completa

401 (Sanctum), 403 (Gate), 404 (`findOrFail`/rota) e 422 (exceptions custom) retornam o envelope
canônico `{data:null,errors:[{code,message}]}` em rotas `api/*` — handlers em `bootstrap/app.php`
(atenção: miram `AccessDeniedHttpException`/`NotFoundHttpException` porque o Handler converte as
exceptions Illuminate antes dos render callbacks). Use `assertApiErrorEnvelope()` sempre.
Códigos: `unauthenticated`, `access_denied`, `not_found`, `tenant_not_resolved`, `invalid_credentials`.

## Economia de modelo

Variantes repetitivas de teste CRUD (mesmo padrão, outro recurso) podem ser rascunhadas por
subagente de modelo barato (ex.: Haiku) apontando um teste-exemplo real. Revisar contra o repo
antes de commitar — ver regra em `AGENTS.md`.

## Execução

```bash
# Teste focused (rápido)
docker exec ead2026-laravel.test-1 php artisan test --compact --filter=testName

# Arquivo inteiro
docker exec ead2026-laravel.test-1 php artisan test --compact tests/Feature/Api/Assessment/QuestionnaireApiTest.php

# Suite inteira
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Feature --compact
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact
```

Banco de teste: `testing` (MySQL via `phpunit.xml`).
