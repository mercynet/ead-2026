# ADMIN Closure — Slice 3 — Publication Readiness — 2026-09-06

## 1. Escopo

Fechamento do delta canônico de publicação Admin em Learning:

- transição explícita de Lesson em `/api/v1/admin/lessons/{id}/publish` e `unpublish`;
- readiness mínimo de Course antes de `publish`;
- preservação da fronteira Admin operacional versus Instructor pedagógico;
- validação Feature, Architecture, PHPStan, Pint, Scribe e HTTP real em banco E2E descartável.

Categorias, Assessment, Instructor/Student, MediaProvider e workstreams de harness continuam fora
deste slice.

## 2. RED → GREEN

O RED foi real e discriminante no novo teste
`tests/Feature/Api/Learning/AdminPublicationReadinessApiTest.php`:

- curso vazio publicava com `200` quando deveria retornar `422`;
- `POST /api/v1/admin/lessons/{id}/publish` e `unpublish` retornavam `404` porque não existiam;
- a negativa de Instructor não alcançava o guard de área por ausência da rota.

Após a implementação: **6 testes passaram, 94 assertions**.

## 3. Implementação

- `PublishCourseAction` agora exige `is_active=true`, status diferente de `archived`, pelo menos um
  módulo do mesmo tenant e uma Lesson não deletada com `status=published` e `is_active=true` no
  mesmo curso. Falhas são `422` e ocorrem antes de qualquer mutação.
- `PublishLessonAction` e `UnpublishLessonAction` executam transições dedicadas; a primeira publicação
  preenche `published_at` e o unpublish preserva esse timestamp.
- `LessonPolicy`/Gate usam o teto existente `learning.lessons.update`; a área Admin continua
  sendo a barreira de superfície e o Instructor não recebe essa rota.
- A criação/atualização genérica de Lesson continua proibindo `status`; não há publicação implícita.
- Spec/tasks, Scribe e E2E foram atualizados para refletir o contrato.

## 4. Testes e checks

- Feature focal: `6 passed (94 assertions)`.
- Regressão Feature Learning antes do último caso de permissão: `277 passed (1367 assertions)`.
- Regressão focal de Course publish: `5 passed (19 assertions)`.
- Architecture focal: `4 passed (7 assertions)`.
- Architecture completa: `22 passed (709 assertions)`.
- PHPStan (`--memory-limit=1G`): `No errors`.
- Pint: `pass`.
- `git diff --check`: passou.
- Scribe: geração concluída; warnings apenas em FormRequests preexistentes sem `bodyParameters()`.
  As rotas novas de Lesson publish/unpublish foram enumeradas no contrato.
- `scripts/ai/verify-changes.sh`: receipt verde — invariantes do diff em 10 arquivos de
  `tests/Architecture`.

## 5. E2E HTTP real

Snapshot do código: `HEAD 495e35c054a10dfff61f57dbe4e701ac0ecbb736`; alterações continuam no
working tree, sem commit/push.

Ambiente: Compose `ead2026-e2e`, `APP_ENV=e2e`, banco `ead2026_e2e`, instância HTTP temporária na
porta interna `8083`, base usada pelo runner `http://localhost:8083`.

- `learning/admin-content`: **18 passed, 0 failed** — curso/módulo/Lesson, publish/unpublish
  explícito de Lesson, `published_at`, metadata, tenant negativo, Instructor 403 e cleanup.
- `learning/courses-publish`: **7 passed, 0 failed** — readiness com módulo/Lesson publicada,
  archived, persona, tenant e auth.
- `learning/courses-unpublish`: **5 passed, 0 failed**.
- Total: **30 casos passados, 0 falhas**.
- Confirmação pós-cleanup no banco: `tenants=0 users=0 courses=0 modules=0 lessons=0`.
- A instância HTTP e a stack Compose foram encerradas/removidas ao final.

## 6. Segurança / tenancy / RBAC

As rotas usam exatamente `resolve.tenant.optional`, `api.context`, `auth:sanctum`,
`area.guard:admin`, `tenant.required.unless.developer` e `tenant.access`. O carregamento Admin de
Lesson é tenant-scoped antes da Action; o readiness restringe CourseModule e Lesson pelo tenant e
parent Course. Payload genérico não altera status. Feature/E2E comprovam 401, 403 `access_denied`,
403 `area_forbidden`, 404 defensivo cross-tenant e 422 sem side effect.

Não há novo PII, log, upload, redirect ou fluxo financeiro neste slice.

## 7. Receipt / divergências

O wrapper `./vendor/bin/sail vendor/bin/pint` continua quebrado neste ambiente por interpretar o
subcomando como `docker compose vendor/bin/pint`; o equivalente auditável
`docker exec ead2026-laravel.test-1 vendor/bin/pint --dirty --format agent` passou. O receipt
`verify-changes.sh` foi executado diretamente e passou.

## 8. Verdict

`ADMIN_PUBLICATION_READINESS_COMPLETE`

O delta A/B está funcionalmente fechado e runtime-verificado nesta execução. A jornada Admin global
ainda não está completa: categorias System/Custom, operação Admin de Assessment e os MUST de
matrícula/cash/manual/evidência consolidada permanecem conforme o escopo maior.
