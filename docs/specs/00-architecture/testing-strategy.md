---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# Estratégia de Testes

**TDD desde a raiz**: o teste vem antes da implementação. Toda task em `tasks.md` mapeia para
pelo menos um teste; o critério de aceite da task é o teste passando.

## Pirâmide

```
        ┌───────────────┐
        │  E2E (poucos) │  fluxos cross-module ponta a ponta
        ├───────────────┤
        │  Feature      │  endpoints HTTP + DB (o grosso da cobertura de API)
        ├───────────────┤
        │  Unit (muitos)│  lógica pura, sem DB/HTTP — rápido
        └───────────────┘
   Architecture (transversal): invariantes executáveis que guardam o contrato
```

## Níveis

### Unit — `tests/Unit`
Lógica **pura**, sem banco nem HTTP. Roda em milissegundos. Alvos típicos:
- cálculo de score, normalização de nome de categoria, aritmética de dinheiro (cents),
  snapshot de attempt, `ProgressStrategy`, resolução do registry `config/permissions.php`.
- Pré-condição: a Action/serviço recebe dependências por injeção (ver `backend-patterns.md`),
  para a regra ser testável isolada.

### Feature — `tests/Feature/Api/<Module>`
Endpoint real: HTTP + DB. O grosso da cobertura. Usa os helpers de `tests/Pest.php`
(`actingAsUserType`, `tenantHeaders`, `assertApiErrorEnvelope`, `assertTenantIsolation`).
Cada endpoint cobre, no mínimo:
- **happy path** (200/201 + shape do Resource);
- **401** não autenticado;
- **403** autenticado sem permissão;
- **422** validação (FormRequest);
- **tenant isolation** (tenant B não alcança recurso de A);
- **permission edge** (UserType que pode vs não pode).

### E2E — `tests/E2E`
Fluxos **cross-module** ponta a ponta, validando integração + eventos. Poucos, de alto valor:
- checkout → webhook de pagamento → `OrderPaidEvent` → matrícula → progresso → certificado;
- jornada completa do aluno (matrícula → consumo de aula → quiz → aprovação → certificado).

### Architecture — `tests/Architecture`
Invariantes executáveis que qualquer agente roda para validar o contrato (o árbitro neutro).
Maioria é scan de arquivo/router/migration (sem DB), barato. Política: regra nova = hard-fail;
dívida atual = `->todo()`/`->skip('debt')` com a asserção presente, vira hard-fail ao realinhar.

- `ModuleBoundaryTest` — módulo não importa interno de outro (só Events/Contracts).
- `PermissionDriftTest` — permissions referenciadas em Gates/Policies ⊆ `config/permissions.php`.
- `TenantIsolationSmokeTest` — tenant B não vê recurso de A.
- `ErrorEnvelopeShapeTest` — 401/403/404/422 no formato `{data:null,errors:[{code,message}]}`.
- `RouteSecuritySurfaceTest` — rota `api/v1` não-pública tem `auth:sanctum`.
- `ScribeAuthAnnotationMatchesMiddlewareTest` — `@unauthenticated` ⇔ rota sem auth.
- `ControllerLeannessTest` — sem query/`where('tenant_id')`/`abort(403)`/`try-catch` morto/FQCN no controller.
- `MoneyNeverFloatTest` — sem `float/double/decimal` em colunas de dinheiro.
- `PiiAuditTest` — campos PII registrados e auditados (activitylog).

## Onde cada nível cabe

| Mudança | Testes obrigatórios |
|---------|---------------------|
| Action com regra pura (score, money, normalização) | Unit + Feature |
| Endpoint CRUD | Feature (happy + 401/403/422 + tenant isolation) |
| Fluxo entre módulos | E2E |
| Nova permission | entra no `config/permissions.php` → `PermissionDriftTest` cobre |
| Nova coluna de dinheiro | `MoneyNeverFloatTest` cobre |
| Novo campo PII | registrar → `PiiAuditTest` cobre |
| Novo módulo / dependência cross-module | `ModuleBoundaryTest` cobre |

## Suites (phpunit)

`Unit`, `Feature`, `E2E`, `Architecture`. Rodar focado e barato:

```bash
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Feature --compact --filter=<nome>
```

Banco de teste: `testing` (conexão `mysql`, fixada em `phpunit.xml`). Feature/E2E usam
`RefreshDatabase` (aplicado globalmente em `tests/Pest.php`).
