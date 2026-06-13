---
name: endpoint-e2e
description: Valida um endpoint REST de ponta a ponta contra o app RODANDO — HTTP real + asserts de side effect no banco. Ativa ao terminar uma task/endpoint para validação independente (humano ou outra IA), ou quando o usuário pedir "roda e2e do <recurso>.<ação>", "valida esse endpoint", "checa as regras/side effects no app". Não substitui Feature tests (in-process); é a camada de validação externa contra o servidor real.
---

# Endpoint E2E

Validação independente de um endpoint contra o **app rodando**: bate HTTP real, confere status +
corpo, e assere **side effects no banco** (colunas setadas pelo contexto, derivadas, timestamps,
eventos). Pega o que um Feature test "verde por engano" mascara.

Runner: `php artisan e2e:run <spec>` (`app/Console/Commands/E2eRunCommand.php`).
Specs declarativos: `tests/e2e-http/<domínio>/<recurso>-<ação>.php`.

> **Não confundir com `tests/E2E/`** (testsuite Pest, in-process, fluxos cross-module da pirâmide
> em `docs/specs/00-architecture/testing-strategy.md`). Esta skill é **HTTP ao vivo** contra o
> servidor real — camada externa, fora do `php artisan test`.

## Quando usar

- Ao **fechar uma task pequena** (1 endpoint) — gerar/rodar o spec E2E como handoff de validação.
- Quando o usuário pedir validação de regra/side effect de um endpoint já existente.
- **Não** use para lógica pura/unitária (isso é Pest unit) nem como substituto dos Feature tests.

## Pré-requisitos

1. **App rodando** e acessível em `config('app.url')` (`.env` `APP_URL`, ex.: `http://localhost:8099`).
   Subir a stack (`sail up -d` / docker) antes. Override pontual: `--base=http://host:porta`.
2. Banco compartilhado entre runner e servidor (mesma conexão de DB).
3. Roles/permissions: o runner seeda `PermissionsSeeder`+`RolesSeeder` se faltarem (idempotente).

## Uso (prompt mínimo)

```
roda e2e do courses.store
```
→ mapeia para `tests/e2e-http/learning/courses-store.php`:
```bash
php artisan e2e:run learning/courses-store
```
Flags: `--keep` (não limpa fixtures, p/ debug) · `--base=` (URL alternativa).

## O runner faz sozinho

- Cria fixtures **efêmeras**: 2 tenants + users por papel (`admin`, `instructor`, `student`,
  `developer`, `otherAdmin`) + tokens Sanctum. Limpa tudo no fim (salvo `--keep`).
- Roda cada caso via HTTP real, compara `status` + paths JSON, e roda os asserts de DB.
- Sai com código ≠0 se algum caso falhar (CI-friendly). Recusa rodar em produção.

## Autorar um spec novo

Para um endpoint sem spec ainda: copie a estrutura de `tests/e2e-http/learning/courses-store.php`
(template canônico) e ajuste. Leia antes a rota, a Action e a `spec.md`/subspec do domínio para
saber **quais regras e side effects** cobrir — não invente; derive do código + contrato.

Spec = `array` PHP com:

```php
return [
    'endpoint' => 'POST /api/v1/learning/courses',
    'setup'   => fn (array $ctx) => [...],   // opcional: cria fixtures de domínio, retorna $ctx['fixtures']
    'cases'   => [ /* ver abaixo */ ],
    'cleanup' => fn (array $ctx) => null,    // opcional: apaga linhas de domínio criadas
];
```

Cada **caso**:

| chave | função |
|-------|--------|
| `name` | rótulo no relatório |
| `as` | papel autenticado (`admin`/`instructor`/`student`/`developer`/`otherAdmin`); omitir = sem auth → 401 |
| `tenant` | `'primary'` (default) ou `'other'` (isolamento) — escolhe o `X-Tenant-ID` |
| `path` | `fn($ctx) => '/...'` — opcional, para rotas com `{id}` (use `$ctx['fixtures']`) |
| `body` | payload JSON |
| `headers` | headers extras (opcional) |
| `expect` | `['status' => int, 'json' => ['data.campo' => esperado]]` |
| `db` | `fn($ctx) => ['rótulo' => [esperado, obtido]]` — asserts de side effect via Eloquent |

Contexto nas closures: `$ctx['tenant']`, `$ctx['otherTenant']`, `$ctx['users'][papel]`,
`$ctx['fixtures']`, `$ctx['response']` (última `Illuminate\Http\Client\Response`).

## Cobertura mínima por endpoint (checklist)

- Happy path (status + corpo).
- **Side effects**: campos setados pelo `ApiContext` (tenant_id/owner) — confirmar que **body não
  sobrescreve** (não-spoofável); colunas derivadas; timestamps condicionais (ex.: `published_at`);
  eventos disparados.
- **Defaults** quando campos opcionais são omitidos.
- AuthZ: `401` sem auth, `403` papel sem permissão.
- `422` validação (campo obrigatório ausente).
- Isolamento de tenant (quando o endpoint lê/escreve recurso existente).

## Fechar

1. App de pé → `php artisan e2e:run <spec>`.
2. Relatório verde = handoff validado. Vermelho = reportar regra/side effect divergente (pode ser
   bug no endpoint **ou** no spec — investigar qual reflete o contrato).
