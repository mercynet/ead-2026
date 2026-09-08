# Commercial Capability Gate — Quiz e Certificate — 2026-09-08

## 1. Finding

O finding HIGH era que um Course podia chegar à publicação comercial com uma configuração que
dependia de Student Assessment e/ou certificate, embora essas capabilities não estejam liberadas na
v0.1. O RED confirmou o problema: os dois Courses incompatíveis retornavam HTTP 200 e ficavam
`published`.

O gate agora fecha a promoção comercial e a reconfiguração de um Course já publicado/ativo. Não há
implementação nova de Student Assessment ou certificates.

## 2. Existing Semantics

`courses` já possui `certificate_enabled`, `certificate_min_progress`, `certificate_requires_quiz`
e `certificate_min_score` (`2026_02_22_144803_add_certificate_fields_to_courses_table.php`).
`certificate_enabled` declara a emissão automática; `certificate_requires_quiz` torna Assessment uma
condição para essa promessa. Não existe um campo geral `assessment_required` nem outro contrato
separado de requisito de completion.

`Questionnaire` pode ser ligado por morph a Course, Lesson ou ser standalone. Essa existência é
incidental: não há hoje uma regra de conclusão que trate a associação como requisito. O
`Lesson.content_type=quiz` também é conteúdo, não uma transição de readiness. Por segurança, o
marcador existente `certificate_requires_quiz` é tratado como requisito quando verdadeiro; não se
infere que um Questionnaire isolado seja obrigatório.

As regras prévias de publicação permanecem: Course ativo, não archived, com módulo e com pelo menos
uma Lesson publicada e ativa. Instructor, preço, mídia, quiz opcional e certificate opcional não
são requisitos estruturais.

## 3. RED

O teste focal foi ampliado em `tests/Feature/Api/Learning/AdminPublicationReadinessApiTest.php`.
Antes do fix:

- Course com `certificate_requires_quiz=true`: esperado 422, obtido 200.
- Course com `certificate_enabled=true`: esperado 422, obtido 200.
- Resultado RED: **2 falhas, 9 casos anteriores/semânticos passantes** (`RED_CONFIRMED`).

Também foi coberta a tentativa de configurar um Course já publicado e o bypass por PATCH com
`status=published`.

## 4. Gate Design

O boundary é `app/Modules/Learning/Services/CourseCommercialReadiness.php`, chamado pela Action de
transição `app/Modules/Learning/Actions/Course/PublishCourseAction.php`.

- `assertPublishable()` rejeita `certificate_requires_quiz` e `certificate_enabled`.
- `assertUpdateable()` avalia o estado efetivo do PATCH e rejeita a ativação/publicação inválida.
- A validação acontece antes de qualquer `fill()`/`save()` relevante.
- O erro usa `ValidationException`; o render central entrega `{data:null, errors:[{code,message}]}`
  com HTTP 422 e `validation_error`.
- O serviço não importa Models de Assessment/Certificate e não depende de actor/persona.

## 5. Assessment Constraint

Enquanto Student Assessment estiver `CONDITIONAL_CAPABILITY / NOT RELEASED`, qualquer Course com
`certificate_requires_quiz=true` falha fechado no publish com mensagem semântica de capability não
disponível. Um Course já publicado/ativo não pode receber essa configuração via update.

Isso não prova nem implementa Student Assessment, não reabre a superfície legacy e não depende de
H-002 estar seguro. H-002 permanece `NOT_PROVEN` como RBAC da capability futura.

## 6. Certificate Constraint

Enquanto Certificate for `NOT_PROMISED_IN_V0_1`, qualquer Course com `certificate_enabled=true`
falha fechado no publish. A mesma declaração não pode ser adicionada a Course já publicado/ativo.
Os limites `certificate_min_*` permanecem metadados inertes quando não há promessa; não há emissão,
download ou revogação nova nesta task.

## 7. Publication/Readiness Integration

O gate foi inserido depois das validações estruturais existentes de archived, active, module e
Lesson publicada/ativa. Assim, a ordem e o contrato Admin readiness anterior permanecem intactos.
A publicação continua uma transição dedicada; CRUD genérico não publica Course.

Falha de readiness não grava `status` nem `published_at`. O Course base, sem as flags incompatíveis,
continua publicável e consumível pelo Student.

## 8. Existing Data

Inspeção read-only no banco de desenvolvimento em 2026-09-08 encontrou:

- Courses (incluindo soft-deleted): `0`;
- `certificate_enabled=true`: `0`;
- `certificate_requires_quiz=true`: `0`;
- Courses `published + active` com qualquer flag incompatível: `0`;
- Questionnaires ligados a Course: `0`.

Classificação: **zero cases**. Não houve migration destrutiva, backfill ou alteração silenciosa de
dados existentes.

## 9. Runtime E2E

`tests/e2e-http/learning/commercial-capability-gate.php` foi executado via HTTP real, com servidor e
runner no mesmo container e DB descartável `ead2026_e2e`, após `migrate:fresh`.

Resultado: **19 passou, 0 falhou**. O fluxo comprovou:

- Admin cria Course base, Module e Lesson via API;
- Lesson e Course base são publicados;
- Admin matricula o Student e o Student lista/acessa o Course base;
- Student não recebe campos `assessment`/`certificate` na superfície comercial;
- PATCH não adiciona Assessment ao Course já publicado;
- Courses Assessment-required e certificate-required retornam 422;
- ambos permanecem `draft` com `published_at=null`;
- cleanup pós-run: `tenants=0 users=0 tokens=0 courses=0 enrollments=0`.

## 10. Architecture

`tests/Architecture/CommercialCapabilityGateTest.php` prova que o gate está em Learning Services,
é chamado pela Publish Action, não importa Models de Assessment e não está implementado no
controller/UI. Architecture geral: **37 testes, 1.338 assertions, 0 falhas**.

## 11. Regression

- Publication/readiness focal: **12 testes, 156 assertions**.
- Learning completo: **328 testes, 2.206 assertions**.
- Student/Instructor relevante incluído no focal Learning: passante.
- Assessment boundary Student, attempts e certificate issuance: **29 testes, 173 assertions**.
- RBAC/Permission: **37 testes, 809 assertions**.
- PHPStan (`sail bin phpstan analyse --memory-limit=1G`): **sem erros**.
- Pint (`sail pint --dirty --format agent`): **pass**.
- Scribe (`sail composer docs`): **exit 0**; gerou OpenAPI/HTML/Postman. Permaneceram apenas
  warnings preexistentes de Requests sem `bodyParameters()`.
- `git diff --check`: executado sem saída de erro antes do fechamento final.

## 12. Remaining Risks

Student Assessment continua deliberadamente não liberado; seu RBAC canônico segue `H-002 =
NOT_PROVEN`. Isso é isolamento comercial, não resolução da capability.

Scribe ainda documenta superfícies legacy `/api/v1/assessment/*` e os campos de configuração
`certificate_*` como parte do contrato técnico. Isso não promove Student Assessment nem promete
certificate no Student Resource, mas a publicação consumer-facing deve manter a qualificação legacy
e condicional.

Operações de Paid Pilot — especialmente backup/restore, observabilidade e demais controles
operacionais já apontados no estado anterior — continuam fora deste gate.

## 13. Product Closure Impact

O HIGH de configuração comercial incompatível foi removido para a superfície de publicação/update:
Course base continua vendável; Course que dependa de Assessment/certificate não é comercialmente
disponibilizado. A closure funcional v0.1 suportada, sem Assessment Student e sem certificate
prometido, pode ser declarada como `PRODUCT_FUNCTIONAL_CLOSURE_CONFIRMED`, separada de readiness
operacional do piloto pago.

`H-002` não foi promovido nem resolvido; permanece não provado e comercialmente isolado.

## 14. Verdict

**`COMMERCIAL_CAPABILITY_GATE_CLOSED`**

Assessment-required Course: rejeitado no publish/readiness com 422 `validation_error`, sem mudança
de lifecycle.

Certificate-required Course: rejeitado no publish/readiness com 422 `validation_error`, sem mudança
de lifecycle.

Course normal: continua publishable quando ativo, não archived, com módulo e Lesson publicada/ativa.

Proveniência: working tree compartilhado e dirty, sem commit/push desta task; implementação em
Learning, testes Feature/Architecture, E2E HTTP real em DB isolado, regressão e ferramentas acima.
