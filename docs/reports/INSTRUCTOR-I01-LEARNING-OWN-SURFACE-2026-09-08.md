# I-01 — Instructor Learning own surface — 2026-09-08

## 1. Baseline

- HEAD no início: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`.
- Working tree inicial: continha apenas o relatório de targeting fornecido pelo usuário como
  arquivo não rastreado; ele foi preservado.
- O trabalho desta entrega permanece no working tree, sem commit, stage ou push.
- Contrato preservado: Admin continua tenant-wide; Instructor opera somente conteúdo próprio; as
  rotas legacy `/api/v1/learning` não foram removidas nem convertidas em superfície canônica.

## 2. RED Evidence

`RED_CONFIRMED`: antes da implementação, os dois testes discriminantes de `GET
/api/v1/instructor/courses` e `GET /api/v1/instructor/courses/{course}` foram executados e
falharam com `404` no envelope `{"data":null,"errors":[{"code":"not_found",...}]}`, pois a
superfície ainda não existia.

Após a implementação mínima do read surface, os dois testes passaram (`2 passed, 41 assertions`).

## 3. Canonical Routes

A nova superfície está em `app/Modules/Learning/Routes/instructor.php`, registrada pelo
`LearningServiceProvider`, com 18 rotas canônicas:

- Courses: `GET/POST /api/v1/instructor/courses`, `GET /courses/{id}`, `GET /courses/{id}/preview`,
  `PATCH /courses/{id}`, `DELETE /courses/{id}`.
- Modules: `GET /courses/{courseId}/modules`, `POST /modules`, `GET/PATCH/DELETE /modules/{id}` e
  `PATCH /modules/reorder`.
- Lessons: `GET /modules/{moduleId}/lessons`, `POST /lessons`, `GET/PATCH/DELETE /lessons/{id}` e
  `PATCH /lessons/reorder`.

Não foram criadas rotas Instructor de publish, unpublish ou archive.

Todas usam `resolve.tenant.optional`, `api.context`, `auth:sanctum`, `area.guard:instructor`,
`tenant.required.unless.developer` e `tenant.access`. O teste de Architecture confirma prefixo,
stack e ausência de leakage para Controllers Admin.

## 4. Ownership Model

- Course: `tenant_id = tenant atual` e `instructor_id = usuário autenticado`.
- Module: ownership transitivo por `Module → Course → instructor_id` no tenant atual.
- Lesson: ownership transitivo por `Lesson → Module → Course → instructor_id` no tenant atual.
- Course com `instructor_id = null` permanece tenant/Admin-owned e não entra no scope Instructor.
- Payloads não podem redefinir tenant, instructor, owner ou parent. Update preserva ownership e
  lifecycle.
- Listagens Instructor não aceitam filtros consumer `status`/`is_active`, portanto drafts e inativos
  próprios continuam visíveis para authoring.

## 5. Course

Implementados list/show own, create, update, delete e preview próprio. A criação deriva tenant,
Instructor e `status=draft`; status arbitrário, `tenant_id`, `instructor_id`, owner, slug e campos de
publicação são rejeitados. O update mantém draft/ownership e não cria transição de lifecycle.

O `CourseResource` de authoring expõe os metadados necessários, status, active state, ownership
pedagógico e categorias já carregadas, sem conceder escrita de categoria.

## 6. Modules

Implementados list/show, create, update, delete e reorder no scope transitivo do Course próprio.
Reorder exige conjunto fechado dos módulos do Course e rejeita membro de Course de outro owner,
parent spoof e payload incompleto.

## 7. Lessons

Implementados list/show authoring, create, update, delete e reorder no scope transitivo
`Lesson → Module → Course → Instructor`. Parent foreign, cross-tenant, parent spoof e reorder
cross-parent são rejeitados. O status existente é preservado; não há publish/unpublish novo.

## 8. Security

Feature e E2E cobrem 401 sem autenticação, 403 por área/permissão, 404 defensivo para outro owner,
404 para null-owner, isolamento cross-tenant, Instructor A versus B no mesmo tenant, parent spoof,
tenant spoof, `instructor_id` spoof e lifecycle spoof.

A regra central foi provada: possuir permission nominal de Instructor não permite operar Course,
Module ou Lesson de outro Instructor. No cross-tenant HTTP real, `tenant.access` bloqueou antes da
consulta com `403 access_denied`; no mesmo tenant, o scope próprio produz 404 defensivo.

Categorias continuam somente leitura pela superfície de catálogo existente; não existe rota de
write de categorias em `/api/v1/instructor`.

## 9. Feature / Architecture

- Feature I-01: `12 passed (229 assertions)`.
- Regressão Feature Learning relevante, incluindo ownership e Course/Module/Lesson existentes:
  `110 passed (603 assertions)`.
- Architecture completa: `25 passed (794 assertions)`.
- `scripts/ai/verify-changes.sh`: verde, 8 arquivos de Architecture selecionados.
- Pint equivalente dentro do container: `pass`.
- PHPStan `analyse --memory-limit=1G`: `[OK] No errors`.
- `git diff --check`: verde.

O wrapper canônico `./vendor/bin/sail vendor/bin/pint --dirty --format agent` foi reproduzido e
falhou por composição incorreta do comando (`unknown docker command: "compose vendor/bin/pint"`). O
mesmo check foi executado diretamente no container Laravel e passou. Não é finding WS2.

## 10. Scribe

`php artisan scribe:generate` foi executado no ambiente E2E e terminou com exit 0. A documentação
gerada inclui somente os grupos novos `Instructor · Cursos`, `Instructor · Módulos` e `Instructor ·
Aulas`, com middleware/auth coerentes e sem rota Instructor inexistente. Os body parameters de
update foram documentados; os warnings remanescentes são de endpoints Assessment antigos, fora do
I-01.

## 11. E2E

- Runner: `php artisan e2e:run instructor/learning-own-surface --base=http://localhost:8083 --fresh`.
- Base URL: `http://localhost:8083` dentro do container E2E.
- HEAD usado: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7` mais o working tree do I-01.
- Banco isolado: `ead2026_e2e`.
- Runner exit: `0`; resultado: `13 passed, 0 failed`.
- Jornada: Instructor A criou Course → Module → Lesson; list/show próprios passaram; Instructor B
  acessou apenas seu Course; A não acessou Course/Module/Lesson de B; null-owner retornou 404;
  cross-tenant foi bloqueado por `tenant.access`; autenticação ausente retornou 401; publish
  inexistente retornou 404.
- Side effects confirmados no banco para Course, Module e Lesson, incluindo tenant, parent,
  Instructor e draft derivados.
- Cleanup auditável após o runner: `courses=0 modules=0 lessons=0 e2e_users=0`.

## 12. Regression

Passaram a regressão Feature Learning relevante, Architecture completa, Pint, PHPStan, Scribe,
`verify-changes.sh`, `git diff --check` e E2E HTTP real. Uma tentativa acidental de rodar testes
PHP no host falhou por ausência de conexão ao MySQL do host; essa execução foi descartada como
inválida e todos os resultados usados neste relatório vieram do container Sail/E2E correto.

## 13. Deferred Decisions

Permanecem fora do slice: publish/unpublish/archive Instructor, assignment/reassignment, alteração
de pivot de categorias, PII/progress, enrollment externo, composição Assessment, MediaProvider e
uploads. Não houve alteração de Student, Financial, plugins, I-02, I-03, I-04, WS2 ou WS3.

## 14. Remaining Instructor MUST

Os próximos MUST do gap Instructor permanecem deliberadamente não iniciados: I-02 e I-03, além de
decisões/implementações de assignment, lifecycle de publicação Instructor, roster/progress,
enrollment e demais superfícies não autorizadas. Este slice fecha apenas a superfície própria de
authoring Learning.

## 15. Verdict

`I01_COMPLETE`

O slice cumpre a superfície canônica `/api/v1/instructor` para Course, Module e Lesson próprios,
ownership transitivo, isolamento A/B e cross-tenant, null-owner isolation, proteção de payload,
RBAC, area guard, Feature, Architecture, Scribe, E2E HTTP real, cleanup e regressão verde.
