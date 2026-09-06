# ADMIN Closure — Slice 1 — 2026-09-06

## 1. Slice Target

**Slice:** `Admin Identity & Onboarding Surface`.

**Escopo executado:** entregar a superfície canônica Admin mínima para identidade/onboarding dentro do tenant:

- `GET /api/v1/admin/users`;
- `GET /api/v1/admin/users/{user}`;
- `POST /api/v1/admin/invitations`.

As operações `PATCH`/`DELETE /api/v1/admin/users/{user}` já existentes foram preservadas e incluídas na prova da jornada. `POST /api/v1/core/invitations/accept` continua público/neutro. As URLs legacy em `/api/v1/core` continuam disponíveis para compatibilidade durante a v1.

Não foram iniciados conteúdo, matrícula, financeiro, Instructor, Student, MZRT, SHOULD/LATER ou WS2/WS3.

## 2. MUST Coverage

| MUST | Resultado no Slice 1 |
|---|---|
| ADM-01 | **Coberto no escopo:** list/show/invite passaram a ter superfície Admin canônica, com update/delete preservados. Tenant isolation, RBAC, area guard, envelopes e compatibilidade legacy foram verificados. |
| ADM-02 | **Remanescente:** controle de curso/conteúdo pertence ao Slice 2. Nenhuma rota Learning foi alterada. |
| ADM-03 | **Remanescente:** matrícula/cash/manual pertence ao Slice 2. Nenhuma rota Learning/Financial foi alterada. |
| ADM-04 | **Remanescente para closure:** a execução runtime do Slice 1 existe, mas o receipt/enforcement final do conjunto Admin continua pertencendo ao Slice 3. |

ADM-05 permanece `INVALID_GAP` e foi absorvido pelos critérios de teste. ADM-12 permanece `LATER` fora do boundary Admin.

## 3. Baseline / RED

Foram adicionados testes discriminantes antes da implementação, em:

- `tests/Feature/Api/Core/Users/AdminUserManagementApiTest.php`;
- `tests/Feature/Api/Core/Invitations/InvitationApiTest.php`.

Antes do fix, os comportamentos canônicos novos falharam por rota ausente/incompatível: listagem retornava 404, detalhe colidia com a superfície existente e a emissão de convite não tinha a rota Admin. O teste de Instructor também não recebia `area_forbidden` porque a rota Admin ainda não existia.

A execução RED também expôs uma limitação ambiental preexistente: respostas 401/403 tentavam escrever em `storage/logs/laravel-2026-09-06.log`, cujo arquivo não era gravável pelo usuário do container. Isso foi tratado como evidência de ambiente, não por alteração de logging, hook ou configuração.

## 4. Implementation

### Rotas e controller

`app/Modules/Core/Routes/admin.php` agora registra `GET /users`, `GET /users/{user}` e `POST /invitations` sob a stack exata:

`resolve.tenant.optional` → `api.context` → `auth:sanctum` → `area.guard:admin` → `tenant.required.unless.developer` → `tenant.access`.

`app/Modules/Core/Http/Controllers/Admin/UserController.php` foi estendido com `index()` e `show()` enxutos:

- `index()` autoriza `core.users.list`, delega a `ListUsersAction` e retorna `UserResource::collection()`;
- `show()` autoriza a policy de detalhe e retorna `UserResource`;
- nenhuma query ou regra de tenant foi colocada no controller.

O convite reutiliza `InvitationController` e `CreateInvitationAction`; tenant, inviter, token opaco, validação e side effect existentes não foram duplicados.

### Autorização e isolamento

Foi adicionado `core.users.view-check` em `CoreServiceProvider`, apontando para `UserPolicy::show`, e usado no detalhe Admin e no detalhe legacy. A permission `core.users.view` não foi duplicada nem alterada.

Essa variante é necessária porque a ability com nome idêntico à permission pode ser short-circuitada pelo `Gate::before` do Spatie, pulando a policy de instância. O caminho `view-check` mantém a checagem de pertencimento ao tenant e preserva 404 defensivo para alvo cross-tenant/developer.

### Compatibilidade e documentação

As rotas `/api/v1/core/users*` e `/api/v1/core/invitations` não foram removidas. A spec de usuários, o Roadmap e `docs/STATE.md` foram atualizados para marcar a superfície Admin como canônica e o legacy como compatibilidade.

## 5. Tests

### Feature

Executado:

```text
docker exec ead2026-laravel.test-1 php artisan test --compact \
  tests/Feature/Api/Core/Users/AdminUserManagementApiTest.php \
  tests/Feature/Api/Core/Invitations/InvitationApiTest.php
```

Resultado: **41 passed, 235 assertions**.

Cobertura discriminante: list/show Admin, convite, 401/403/404/422, area guard, RBAC, cross-tenant, payload proibido, soft delete e compatibilidade do aceite.

### Architecture

Resultado: **22 passed, 709 assertions**.

Inclui `AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `ScribeAuthAnnotationMatchesMiddlewareTest`, envelopes, tenant isolation/scoping, permissions, controller leanness, PII/LGPD e module boundary.

### E2E HTTP

O spec existente `tests/e2e-http/core/admin-users.php` foi estendido para listagem, detalhe, isolamento, guard de Instructor e convite, com assert de persistência do convite. Também foi corrigido um assert legado para o envelope atual (`data.message`).

Resultado na stack descartável `ead2026_e2e`: **16 passed, 0 failed**.

O banco, containers, rede e volumes E2E foram removidos após a execução.

## 6. Validation

| Check | Resultado |
|---|---|
| Pint | `pass` |
| PHPStan (`--memory-limit=1G`) | `No errors` |
| Scribe | geração concluída; novas rotas Admin foram enumeradas e documentadas. Warnings existentes de `bodyParameters()` permanecem fora do Slice 1. |
| Route inspection | rotas novas presentes com middleware Admin exato; confirmado por `route:list --path=api/v1/admin/users --json` e Architecture. |
| `git diff --check` | passou |
| `scripts/ai/verify-changes.sh` | **não passou por limitação ambiental:** o script invoca o wrapper Sail, que reportou `Docker is not running`; o conjunto Architecture prescrito pelo script passou via `docker exec`. |

Não foi executado `--force-db`. A prova E2E usou banco descartável nomeado `ead2026_e2e`.

## 7. Admin Boundary

Incluído:

- Admin lista e consulta usuários do próprio tenant;
- Admin emite convite tenant-bound para `student`/`instructor`;
- Admin atualiza/remove usuários administráveis pelas rotas já existentes;
- Developer, Instructor e Student não alcançam a superfície Admin;
- cross-tenant e developer são ocultados por 404 quando a policy de instância se aplica;
- aceite do convite segue público/neutro e fora do guard Admin.

Excluído intencionalmente:

- ownership e criação de conteúdo do Instructor;
- consumo/progresso/checkout do Student;
- tenant/platforma MZRT;
- curso, módulo, aula, matrícula, cash/manual e webhook.

## 8. Cross-cutting Dependencies

- **Auth:** Sanctum e `requiredUser()` no controller Admin.
- **Tenant:** `ApiContext`, resolução por `X-Tenant-ID`, `tenant.access` e `ListUsersAction` escopado.
- **Area:** exatamente `area.guard:admin`, com `EnsureAreaAccess` antes de binding.
- **RBAC:** permissions existentes `core.users.list`, `core.users.view`, `core.invitations.create`; nenhuma permission nova foi criada.
- **API contract:** Resources e envelope central de erro; Scribe sem `@unauthenticated` nas rotas protegidas.
- **Security/LGPD:** nenhum PII novo; `User` já usa `LogsActivity` conforme o inventário LGPD. O detalhe cross-tenant foi protegido contra o short-circuit de Gate descrito na implementação.
- **Arquitetura:** Core permanece dono de User/Invitation; não houve importação entre módulos nem refatoração de módulos.

## 9. Evidence Pending

O comportamento do Slice 1 tem evidência atual `RUNTIME_VERIFIED`: E2E HTTP 16/16 contra servidor temporário e banco `ead2026_e2e`, com cleanup confirmado.

Permanece uma pendência de enforcement do harness:

- `scripts/ai/verify-changes.sh` não conseguiu emitir seu receipt porque o wrapper Sail reportou `Docker is not running`, embora `docker exec` tenha permitido executar Feature, Architecture, PHPStan, Scribe e o servidor E2E.

Isso não bloqueou a prova funcional do Slice 1, mas impede declarar que o guardrail automático do diff foi validado nesta sessão. O gap é registrado, sem abrir WS2 e sem alterar hooks/scripts.

## 10. Remaining MUST

Para `ADMIN_COMPLETE`, permanecem:

- **ADM-02 — BLOCKER:** superfície Admin canônica de curso/conteúdo;
- **ADM-03 — HIGH:** matrícula Admin e jornada cash/manual integrada;
- **ADM-04 — closure gate:** receipt final dos Slices Admin, Scribe e enforcement/evidence atual.

ADM-01 está fechado no escopo deste slice. ADM-05 continua inválido como MUST independente. ADM-12 continua `LATER`.

## 11. Verdict

**`ADMIN_SLICE_1_COMPLETE_WITH_EVIDENCE_PENDING`**

O comportamento do Slice 1 foi entregue, testado e validado por HTTP real. A única pendência é o receipt automático de `verify-changes.sh` devido ao wrapper Sail indisponível, não uma falha conhecida de produto. `ADMIN_COMPLETE` não foi declarado. Slices 2 e 3 permanecem não iniciados.
