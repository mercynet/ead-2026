# ADMIN Closure — Slice 2 — 2026-09-06

## 1. ADM-02 Target

**Alvo:** `ADM-02 — controle de curso e conteúdo`.

O targeting define o blocker concreto como a ausência de uma superfície Admin canônica completa:
as operações legacy de curso, módulos, aulas, materiais e mídia estavam em `/api/v1/learning`,
misturadas entre Admin/Instructor e sem o guard de área Admin exigido para closure. O mínimo deste
slice foi expor a jornada de gestão tenant-wide em `/api/v1/admin`, incluindo CRUD/reorder de curso,
módulo e aula, metadados de material/mídia, preservando categorias e publish Admin já existentes.

Não foi implementada matrícula/cash/manual (`ADM-03`) nem iniciado um slice de evidência separado
(`ADM-04`). O E2E foi executado apenas como validação necessária do comportamento ADM-02.

## 2. Admin/Instructor Boundary

Admin opera todo o conteúdo do próprio tenant; Instructor mantém autoria/ownership próprio na
superfície legacy de authoring. Nenhuma rota `v1/instructor` foi criada.

Um curso criado por Admin nasce com `instructor_id = null`, assim como o material criado por Admin.
Isso evita atribuir ownership pedagógico por conveniência e deixa explícita a dívida de uma futura
operação de atribuição de autor. O Admin não ganhou operações de consumo, progresso, download,
tracking de aluno ou privilégio MZRT.

## 3. Baseline / RED

Foi criado antes da implementação o teste discriminante
`tests/Feature/Api/Learning/AdminContentOperationsApiTest.php`, usando o contrato HTTP real,
envelope e efeitos no banco como oracle.

Com as rotas Admin de conteúdo ainda ausentes, a execução baseline foi:

```text
docker exec ead2026-laravel.test-1 php artisan test --compact \
  tests/Feature/Api/Learning/AdminContentOperationsApiTest.php
4 failed, 1 passed (36 assertions)
```

As falhas eram 404 nas operações que deveriam existir; a negativa de persona já existente foi o
único caso verde. Depois do fix, o teste passou a cobrir 6 casos e 135 assertions.

## 4. Implementation

- `app/Modules/Learning/Routes/admin.php` recebeu a superfície Admin area-first para cursos,
  módulos, aulas, materiais e mídia. Todas usam a stack
  `resolve.tenant.optional`, `api.context`, `auth:sanctum`, `area.guard:admin`,
  `tenant.required.unless.developer`, `tenant.access`.
- Foram adicionados controllers Admin dedicados para módulo, aula, material e mídia; o controller
  Admin de curso passou a listar/criar/atualizar/remover, mantendo show, publish/unpublish e
  categorias existentes.
- Foram adicionados Actions de listagem, detalhe administrativo de aula e CRUD de material; listas
  usam `cursorPaginate`.
- Foram adicionados FormRequests Admin que proíbem `tenant_id`, `instructor_id`, `course_id`/
  `course_module_id` quando derivados da URL ou da relação, além de `status`, `slug` e ordenação
  quando derivados pela regra. O path de material continua tenant-bound e anti-traversal.
- O detalhe Admin de aula usa `GetAdminLessonAction` e não chama tracking de visualização,
  progresso ou resolução de URL de consumo.
- `StoreCourseAction` e `StoreCourseMaterialAction` passaram a receber ownership explicitamente;
  os controllers legacy continuam passando o ator atual, preservando compatibilidade Instructor.
- Policies/Gates de listagem de curso/módulo/aula foram ligados às permissions canônicas e mantêm
  a regra `own` do Instructor na superfície legacy.
- Scribe foi regenerado e as specs Learning de curso/conteúdo e mídia foram alinhadas.

## 5. Tests

### Feature

Teste novo focado:

```text
tests/Feature/Api/Learning/AdminContentOperationsApiTest.php
6 passed (135 assertions)
```

Ele cobre criação Admin sem ownership pedagógico, listagem, update/delete, CRUD/reorder de
módulos/aulas, material/mídia, estados draft, scope spoofing, 401, area guard, cross-tenant 404 e
Instructor mantendo authoring próprio no legacy.

Regressão do Learning/RBAC:

```text
146 passed (700 assertions)
```

Incluiu o teste novo, `InstructorOwnershipTest`, CRUD de curso/material, módulo, aula e mídia.

### Architecture e estática

```text
Architecture: 22 passed (709 assertions)
Pint: pass
PHPStan --memory-limit=1G: No errors
Scribe: geração concluída; rotas Admin novas enumeradas
git diff --check: pass
```

O Scribe manteve apenas warnings conhecidos de `bodyParameters()` ausentes em requests existentes
ou requests derivadas, sem falha de geração.

## 6. Runtime/E2E

Snapshot da execução: HEAD `495e35c054a10dfff61f57dbe4e701ac0ecbb736` (`main` alinhado a
`origin/main` no início/fim; nenhuma alteração foi commitada por este trabalho).

Ambiente isolado: stack Compose `ead2026-e2e`, `APP_ENV=e2e`, banco descartável
`ead2026_e2e`. Como o `--env-file` do Compose não propagou as variáveis ao processo HTTP montado,
foi iniciada uma instância e2e explícita em `http://localhost:8083` dentro do container, e o runner
e2e também recebeu as mesmas variáveis. A stack foi desmontada ao final.

Resultados HTTP reais, com side effects e cleanup:

- `learning/admin-content`: **16 passed, 0 failed** — curso → módulos → aulas → detalhe Admin →
  material/mídia → remoções; Instructor 403; cross-tenant 404; side effects persistidos e cleanup.
- `learning/admin-course-categories`: **8 passed, 0 failed** — categorias, ordem, limpeza,
  negativas de persona/tenant/auth.
- `learning/courses-publish`: **7 passed, 0 failed** — publish/unpublish boundary, archived 422,
  negativas de persona/tenant/auth e side effects.

Total runtime desta validação: **31 casos passados, 0 falhas**. Não foi usado `--force-db`; nenhum
fixture, container ou rede e2e permaneceu após o cleanup.

## 7. Authorization / Tenant Evidence

- `route:list --path=api/v1/admin --json` confirmou as novas rotas com exatamente
  `area.guard:admin` e a stack tenant-scoped; não há guard Instructor adicionado.
- Feature e E2E confirmaram Admin no próprio tenant, `tenant_id`/ownership não spoofáveis e 404
  defensivo para recurso de outro tenant.
- Instructor e Student não alcançam `/api/v1/admin`; Developer também não usa Admin para obter
  privilégio de plataforma. O teste existente `InstructorOwnershipTest` continua verde para
  Instructor operar somente conteúdo próprio em `/api/v1/learning`.
- Permissions continuam derivadas de `config/permissions.php`; nenhuma permission nova ad hoc foi
  criada. Gate, Policy e FormRequest cobrem o teto de persona, ownership e escopo.
- Nenhuma operação Admin de conteúdo chama consumo de aula ou grava `lesson_views`.

## 8. Harness / Receipt Status

O caminho canônico `scripts/ai/verify-changes.sh` foi tentado. Ele invocou o wrapper Sail e falhou
com:

```text
Docker is not running.
```

Isso não corresponde ao estado real dos containers acessíveis por `docker exec`. A bateria
Architecture prescrita pelo script foi executada diretamente no container e passou 22/22; Feature,
PHPStan, Pint, Scribe e E2E também foram executados com seus comandos equivalentes e resultados
registrados acima.

Portanto, a limitação é de receipt/enforcement automático do wrapper Sail, herdada da pendência do
Slice 1. O harness Codex não é declarado fechado e nenhuma alteração de WS2 foi feita.

## 9. Remaining Risks

- Cursos criados por Admin ficam sem `instructor_id` até existir uma decisão/operação explícita de
  atribuição; criar essa operação agora seria iniciar ownership adicional, fora do mínimo ADM-02.
- Upload real, MediaProvider e integração media library continuam pending; este slice administra
  metadata/path seguro, não binário.
- A superfície legacy `/api/v1/learning` permanece durante v1 por compatibilidade; sua remoção ou
  migração exige inventário de consumidores e não foi feita aqui.
- O receipt automático continua ausente enquanto o wrapper Sail falhar, embora a evidência manual
  equivalente esteja verde.

## 10. Remaining MUST

- `ADM-02`: materialmente fechado e runtime verificado no escopo de conteúdo Admin.
- `ADM-03`: permanece aberto — superfície Admin de matrícula e jornada cash/manual idempotente não
  foram iniciadas.
- `ADM-04`: permanece como gate final de evidência/closure; o receipt automático do harness ainda
  está pendente e não foi promovido para um slice separado.
- `ADM-01`: permanece fechado conforme o Slice 1 anterior.
- `ADM-05`: continua absorvido pelos critérios de teste, sem MUST independente.

## 11. Verdict

**`ADMIN_SLICE_2_COMPLETE_WITH_EVIDENCE_PENDING`**

ADM-02 foi materialmente implementado e comprovado por Feature, Architecture, PHPStan, Scribe e
HTTP real isolado, com boundary Admin/Instructor preservado e sem abertura das áreas seguintes. A
classificação `WITH_EVIDENCE_PENDING` existe exclusivamente porque o receipt automático de
`verify-changes.sh` não foi produzido pelo wrapper Sail; os checks equivalentes e a prova runtime
real passaram.
