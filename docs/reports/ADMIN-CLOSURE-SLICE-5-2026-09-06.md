# Admin Closure — Slice 5 — Assessment básico

**Data:** 2026-09-06
**Escopo:** Questionnaires e Questions básicos na área Admin; nenhum endpoint de Instructor/Student
foi aberto ou alterado.
**Status:** `ASSESSMENT_ADMIN_BASIC_CONVERGED`

## Contrato fechado

- Admin opera Assessment tenant-wide em `/api/v1/admin/questionnaires` e
  `/api/v1/admin/questions`.
- `tenant_id` vem exclusivamente do contexto resolvido; payload não escolhe tenant.
- Criação Admin grava `instructor_id = null`; Admin não é convertido em Instructor.
- Update Admin reutiliza a leitura tenant-scoped e preserva qualquer `instructor_id` já existente.
- Parents de Questionnaires e categories de Questions são validados via Contract público de Learning;
  nenhum Model interno de Learning foi importado pela Action de Assessment.
- Instructor e Developer recebem `area_forbidden` na superfície Admin; registro de outro tenant
  retorna 404 defensivo.

## RED real

`AdminAssessmentApiTest.php` começou com **4 falhas** porque as rotas Admin não existiam:

- POST de Questionnaire e Question retornava 404.
- Listagem e updates Admin retornavam 404.
- A tentativa de validar o guard de área também encontrava 404, pois não havia superfície.

## Implementação

- `Routes/admin.php` com a stack canônica Admin e guard exato `area.guard:admin`.
- Controllers Admin finos para Questionnaire e Question.
- `StoreAdminQuestionnaireAction` e `StoreAdminQuestionAction` com ownership administrativo nulo.
- `Learning\Contracts\AssessmentCatalog` + resolver para validar parent/category sem cruzar a
  fronteira de módulo.
- Rotas Admin registradas no `AssessmentServiceProvider`.
- Spec/tasks, Scribe e E2E declarativo atualizados.

## Evidência

- Focal Admin: **7 passed (59 assertions)**.
- Regressão Assessment completa: **46 passed (265 assertions)**.
- E2E HTTP real: **7 passed, 0 failed** em `assessment/admin-basic`; o runner confirmou
  `questionnaires=0` e `questions=0` após cleanup.
- `scripts/ai/verify-changes.sh`: **11 arquivos de Architecture verdes**.
- PHPStan: sem erros; Pint: verde.
- Scribe: geração concluída e listou as cinco rotas de Questionnaire e quatro de Question Admin;
  apenas warnings preexistentes de `bodyParameters()` em Requests.

## Pendências fora deste slice

- `DELETE /questions`, anexar/listar questões de um Questionnaire, eventos de tentativa, PDF e
  revoke de certificado permanecem tasks independentes.
- Atribuição/transferência de Instructor permanece operação futura explícita e auditável.
- Quiz avançado, MediaProvider, matrícula externa e lifecycle de plugins seguem adiados.
