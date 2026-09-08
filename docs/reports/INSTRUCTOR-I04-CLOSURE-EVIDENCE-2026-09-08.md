# I-04 — Instructor Closure / Evidence / False-Success Gate

Data da auditoria: 2026-09-08
Escopo: somente a superfície canônica Instructor; Student, WS2 e WS3 não foram iniciados.

## 1. Executive Summary

Verdict: `INSTRUCTOR_COMPLETE_WITH_EVIDENCE_PENDING`.

O boundary mínimo de Instructor está implementado e foi verificado por Feature, Architecture e
HTTP real contra banco descartável. Ownership pedagógico A/B, isolamento de tenant, roster com PII
mínima, matrícula FREE idempotente com espelho financeiro, Assessment histórico e results least
privilege passaram a jornada consolidada.

Não foi declarado `INSTRUCTOR_COMPLETE` porque permanecem dois findings médios que afetam o gate de
closure: o Scribe ainda publica campos proibidos inferidos pelas regras de validação, e o caminho de
listagem de results faz consultas por questionnaire/curso em loop, uma preocupação de N+1 para
volume. Nenhuma decisão de produto nova foi ocultada para obter closure.

Durante a auditoria foram corrigidos dois defeitos de evidência: o cleanup do E2E I-02 deixava dois
usuários criados pelo setup, e testes traziam o rótulo histórico `RED_CONFIRMED` depois de verdes.
Ambos foram revalidados.

## 2. Claims Under Review

Foram rechecados, sem aceitar os relatórios I-01/I-02/I-03 como prova final:

- Learning próprio: Course, Module, Lesson, LessonMedia e CourseMaterial.
- Roster/progress: leitura de matrículas próprias, PII mínima, progresso e matrícula FREE própria.
- Financial mirror: Order paid zero, OrderItem, Payment free/resolved, idempotência e ausência de
  gateway/outbox indevidos.
- Assessment: Questionnaire/Question próprios, parents Course/Lesson próprios, composição,
  results de alunos e imutabilidade após attempt.
- Segurança: 401, 403 de área, negação de permission, 404 defensivo, tenant.access e 422.
- Área/RBAC, Resources/envelopes, Scribe, Architecture, regressões e qualidade dos testes.

## 3. Provenance

- HEAD: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`, branch `main`.
- Estado: working tree já estava sujo com a entrega I-01/I-02/I-03; não houve stage, commit ou
  push nesta auditoria. Os arquivos alterados por I-04 estão misturados a esse working tree e são
  identificados pelos hashes abaixo no handoff.
- App principal: container `ead2026-laravel.test-1`.
- App E2E: `ead2026-e2e-laravel.test-1`, `APP_ENV=e2e`, `APP_DEBUG=false`, servidor Laravel em
  `http://localhost:8083` dentro do container.
- Banco E2E: `ead2026_e2e`, conexão MySQL, migrations em estado `[1] Ran`.
- Runtime observado: Laravel 12.63.0, PHP 8.4.18, Composer 2.9.5.
- `route:list --path=api/v1/instructor --json`: 49 rotas; 0 rotas sem exatamente um
  `EnsureAreaAccess:instructor`.
- `composer docs`: exit 0 quando executado como root no container. A execução Sail normal falhou
  antes por ownership de `.scribe/endpoints.cache` (`nobody:nogroup`); isso é limitação operacional
  do ambiente, não sucesso presumido.
- Fingerprints do working tree desta auditoria:

  - `tests/e2e-http/instructor/i04-consolidated-closure.php` —
    `8cbbc92a6804e83a1bc6296c7259dc5ff8a1258d22aca64fdb049040197a1109`
  - `tests/e2e-http/instructor/i02-roster-progress-attachments.php` —
    `40cc84af9f08ee70db7305a2fb04fe0cfd1e4276f022862998c16adb3ed6e886`
  - `app/Modules/Learning/Http/Requests/Instructor/StoreInstructorEnrollmentRequest.php` —
    `ab81125f5d7928668d00c332b3c3d18c81c66b1b932203eb3d2f13e7b6153620`
  - `app/Modules/Learning/Http/Requests/Instructor/UpdateLessonMediaRequest.php` —
    `6bad8dbbff6ccc2577576b6544e0d06ed656c72a9629cb7f6157c14ef022c04a`
  - `tests/Feature/Api/Learning/InstructorI02ApiTest.php` —
    `e80df4e767f5db76e6dace83170678ab07df8b6dcd43d8f38e560687486296e2`
  - `tests/Feature/Api/Assessment/InstructorI03AssessmentApiTest.php` —
    `dff89bbabdce874cc23fe7a70e607d5b4908fb18d4243a0aa606d7845aa5f3be`

## 4. Ownership Adversarial Matrix

| Ator/recurso | Mesmo tenant | Tenant diferente | Admin-owned (`instructor_id=null`) | Resultado |
|---|---:|---:|---:|---|
| Instructor A → Course B | 404 defensivo | `tenant.access`/bloqueio anterior | invisível | PASS |
| A → Module B | 404 defensivo | bloqueado | n/a | PASS |
| A → Lesson B | 404 defensivo | 404 defensivo | n/a | PASS |
| A → LessonMedia B | 404 defensivo | 404 defensivo | n/a | PASS |
| A → CourseMaterial B | 404 defensivo | 404 defensivo | n/a | PASS |
| A → Enrollment do Course B | roster vazio/404 defensivo | tenant.access | n/a | PASS |
| A → progress do Course B | 404 defensivo | tenant.access | n/a | PASS |
| A → Questionnaire B | 404/422 conforme operação | tenant.access | invisível | PASS |
| A → Question B | 404 defensivo | tenant.access | n/a | PASS |
| A → result B | 404 defensivo | tenant.access | n/a | PASS |

Evidência: Feature HTTP/DB e os três runners E2E usam A, B, mesmo tenant, tenant distinto e
registro Assessment Admin-owned. O tenant permission não substituiu o filtro pedagógico.

## 5. Parent/Payload Spoofing

Module contra Course B, Lesson contra Module B, Media/Material contra parents B, Questionnaire
contra Course/Lesson B e Question B anexada ao Questionnaire A foram rejeitados sem persistência
indevida. Os cenários same-tenant e cross-tenant foram cobertos pelos Features e pelo runner
consolidado.

Payloads com `tenant_id`, `instructor_id`, `owner`, lifecycle/status, parent alternativo,
`billing_type`, valores financeiros e campos internos de scoring foram rejeitados ou ignorados
conforme o contrato; os recursos aceitos derivaram tenant/owner/status do contexto e da regra.

## 6. Roster / PII

Resposta HTTP real do roster contém somente `user.id`, `user.name` e `user.avatar`, além dos campos
pedagógicos autorizados da matrícula/progresso. Não foram observados email, documento, telefone,
tenant_id, financeiro ou metadata interna em nested relations, Resource ou resposta consolidada.

O teste Feature verifica `array_keys(user) === ['id', 'name', 'avatar']` e o E2E verifica ausência de
PII. A Architecture que inspeciona o Resource é evidência complementar, não foi usada sozinha como
prova HTTP.

Classificação: `RUNTIME_VERIFIED`.

## 7. Free Enrollment / Financial Mirror

No E2E consolidado, Course A free/published/active e Student do tenant produziram:

- 1 Enrollment corrente `active`;
- 1 Order `paid` com `total_cents=0`;
- 1 OrderItem de Course;
- 1 Payment com gateway `free`;
- 0 duplicações após replay idempotente.

Após replay foram conferidas quantitativamente Enrollment, Order, OrderItem e Payment. Não houve
gateway externo, charge externo ou outbox `order_paid` indevido no caminho FREE; a validação do
fluxo usa a rota HTTP real e as contagens do banco. A ausência de chamada foi também confirmada
estaticamente pelo caminho que resolve Payment free antes de gateway, mas não houve spy de gateway
externo no runner; essa parte específica permanece `STATIC_ONLY`.

## 8. Paid External Negative

Course pago, `billing_type=external` e payload financeiro manipulado retornaram envelope canônico
422 `validation_error`. No mesmo cenário, as contagens de Enrollment, Order, OrderItem, Payment e
outbox permaneceram zero. Nenhum gateway foi iniciado porque a request falha antes do fluxo
financeiro.

Classificação: `RUNTIME_VERIFIED` para status/envelope e zero side effects; `STATIC_ONLY` para a
não-invocação de um adapter externo não instrumentado.

## 9. Assessment Historical Integrity

Attempt real foi iniciado, respondido e finalizado. Depois disso, update/delete de Questionnaire,
attach/detach/reorder de composição e update/delete de Question foram bloqueados com 422
`validation_error`. Attempt, snapshot, score/result e respostas permaneceram preservados; não foi
observado side effect parcial.

Classificação: `RUNTIME_VERIFIED` no E2E I-03 e na jornada consolidada; `FEATURE_VERIFIED` e
`ARCHITECTURE_VERIFIED` como camadas complementares.

## 10. Results Least Privilege

Resposta HTTP real incluiu somente IDs de attempt/questionnaire, student `id/name/avatar`, score,
percentage/pass, timestamps, duração, número do attempt e respostas/pontos/feedback permitidos.
Não incluiu email, documento, tenant_id, financeiro, snapshot bruto, gabarito bruto ou internals de
scoring. Instructor B não alcançou result de Course A.

Classificação: `RUNTIME_VERIFIED`.

## 11. Security / RBAC / Area

- 401 sem token: envelope `unauthenticated`.
- 403 de área e permission denial: middleware/Gate distintos preservados.
- Owner foreign: 404 defensivo, sem diferença de corpo que denuncie existência.
- Cross-tenant: `tenant.access` bloqueia antes da operação.
- Validação: 422 envelope `validation_error`.
- As 49 rotas canônicas têm a stack tenant-scoped e exatamente `area.guard:instructor`.
- Controllers são namespaces Instructor; não há reutilização de Controllers Admin na superfície.
- Rotas legacy domínio-first não foram promovidas.

Classificação: `ARCHITECTURE_VERIFIED` e `RUNTIME_VERIFIED` nos cenários HTTP exercitados.

## 12. Architecture

Inspeção manual não encontrou query direta em Controller Instructor, derivação de tenant pelo
payload ou ownership apenas no Controller. Actions usam resolvers/scopes próprios; Assessment
atravessa Learning por `AssessmentCatalog`, não por Model interno. Resources Instructor são
específicos e não carregam a árvore de usuário genérica no roster/results.

Architecture completa: **33 passed, 1188 assertions**. `verify-changes`: verde, 10 arquivos
arbitradores. Classificação: `ARCHITECTURE_VERIFIED`.

## 13. Scribe

Convergência de superfície: 49 rotas reais e 49 entradas Instructor nos arquivos gerados
`.scribe/endpoints/19,20,21,22,23,24,31,32.yaml`; todos os endpoints reais têm documentação e a
autenticação aparece como protegida. O Scribe terminou exit 0 após correção de ownership do cache.

Finding: o extractor do Scribe ainda transforma regras `prohibited` em body parameters. Por isso o
YAML publica, por exemplo, `tenant_id`, `status`, `billing_type`, `instructor_id` e
`price_cents` em `.scribe/endpoints/24.yaml:167-235`, e `tenant_id`, `lesson_id`,
`course_module_id` e `owner` em `.scribe/endpoints/23.yaml:616-659`. As requests receberam
`bodyParameters()` para descrever os campos permitidos, mas isso não remove os campos inferidos das
regras. É um finding documental médio: não abriu escrita no runtime, porém viola o contrato de
documentar apenas o payload aceito. Os warnings restantes referem-se a requests legacy fora da
superfície Instructor.

Classificação: `ARCHITECTURE_VERIFIED` para rota/auth e `STATIC_ONLY`/pendente para exclusão de
payload proibido no artefato Scribe.

## 14. Consolidated E2E

Runner novo `tests/e2e-http/instructor/i04-consolidated-closure.php`: **27 passed, 0 failed**,
app HTTP real em `http://localhost:8083`, DB `ead2026_e2e`. A jornada integrou Course → Module →
Lesson → Media → Material → FREE enrollment → roster/progress → Questionnaire → Questions →
composition → Student attempt → Instructor result → imutabilidade → A/B/cross-tenant negatives.

Regressão E2E externa adicional, executada serialmente no mesmo app isolado:

- I-01 `learning-own-surface`: **13 passed, 0 failed**;
- I-02 `i02-roster-progress-attachments`: **14 passed, 0 failed**;
- I-03 `i03-assessment-own-results`: **20 passed, 0 failed**.

Após cada rodada, e novamente ao fim, cleanup conferiu zero em users, tenants, courses, modules,
lessons, media, materials, enrollments, orders, order_items, payments, outbox, questionnaires,
questions, composition, attempts e answers.

Classificação: `E2E_VERIFIED` e `RUNTIME_VERIFIED` para os casos exercitados.

## 15. Performance Smoke

Smoke estático e observação dos endpoints exercitados:

- Course list: cursor pagination e eager load de categories — PASS.
- Roster: cursor pagination e user/progress eager loads — PASS funcional, com carga de árvore
  Course/modules/lessons no show de enrollment — CONCERN de volume.
- Progress: consulta funcional e agregação sem árvore de resposta indevida — PASS.
- Questionnaire list: cursor pagination e parent IDs próprios — PASS.
- Results list: cursor pagination e eager loads de questionnaire/user/answers — PASS funcional.

CONCERN médio: `InstructorResultScope::questionnaireCourseMap()` e `attempts()` consultam
`AssessmentCatalog` dentro de loops (`app/Modules/Assessment/Services/InstructorResultScope.php:27-55`),
com potencial N+1 por número de questionnaires; `GetInstructorEnrollmentAction.php:18-26` também
carrega a árvore completa do Course no detalhe. Não é blocker nesta task e não foi feita otimização
sem benchmark/requisito, mas impede declarar ausência total de regressão de performance.

## 16. False-Success Audit

Findings confirmados e tratados:

- I-02 criava `E2E Student Two` e `E2E Instructor B`, mas seu cleanup não os removia. O runner
  retornava 0 apesar dos resíduos. O cleanup foi corrigido em
  `tests/e2e-http/instructor/i02-roster-progress-attachments.php:209-218`; os órfãos históricos
  IDs 30/31 foram removidos de forma pontual e o banco terminou zerado.
- I-02 validava Instructor B com status 200 e uma consulta DB que não provava o corpo do roster.
  O caso agora exige `data=[]`.
- I-01/I-02/I-03 tinham testes com `assertSuccessful()` sem sempre uma asserção de conteúdo; os
  casos críticos têm asserts discriminantes, e a jornada consolidada cobre os efeitos. O restante
  é risco baixo de manutenção, não uma prova exclusiva de closure.
- Rótulos `RED_CONFIRMED` stale foram removidos de I-02/I-03; não havia RED atual escondido.
- Architecture PII tests são scans estáticos de Resource e não prova HTTP; isso foi compensado pela
  validação E2E real.
- Uma tentativa de rodar Feature e Architecture em paralelo falhou por corrida no banco `testing`
  (migrations/tabelas), não por produto; a evidência final usa execuções seriais.

Não foram encontrados `skip`, `todo`, `markTestSkipped`, conditional exits ou catch silencioso nos
casos Instructor que anulassem assertions. O runner conta falha de cleanup/erro de caso no exit code.

## 17. Regression

- I-01/I-02/I-03 Feature focal: verde; última execução dos focos alterados: I-02 **9 passed, 164
  assertions** e I-03 **11 passed, 165 assertions**.
- Feature Learning: **310 passed, 1853 assertions**.
- Feature Assessment: **57 passed, 430 assertions**.
- Financial mirror + Authorization/RBAC + Tenant middleware: **41 passed, 193 assertions**.
- Architecture: **33 passed, 1188 assertions**.
- PHPStan: **503/503**, no errors.
- Pint: pass.
- Scribe: exit 0, com finding documental descrito na seção 13.
- `verify-changes.sh`: pass; `git diff --check`: pass.

## 18. Evidence Classification

| Capability | Evidência máxima legítima |
|---|---|
| Course/Module/Lesson own CRUD e reorder | `RUNTIME_VERIFIED` nos casos E2E + Feature/Architecture |
| LessonMedia/CourseMaterial metadata CRUD/download | `RUNTIME_VERIFIED` nos casos E2E + Feature/Architecture |
| Roster, progress e PII mínima | `RUNTIME_VERIFIED` |
| FREE enrollment, mirror e replay | `RUNTIME_VERIFIED`; gateway-call absence específica `STATIC_ONLY` |
| Paid external negative e zero side effects | `RUNTIME_VERIFIED`; adapter não instrumentado `STATIC_ONLY` |
| Questionnaire/Question/parents/composition | `RUNTIME_VERIFIED` |
| Results least privilege | `RUNTIME_VERIFIED` |
| Historical immutability | `RUNTIME_VERIFIED` |
| Area guard/RBAC/envelopes | `ARCHITECTURE_VERIFIED` + `RUNTIME_VERIFIED` nos cenários |
| Scribe rota/auth | `ARCHITECTURE_VERIFIED`/`STATIC_ONLY` |
| Scribe ausência de body params proibidos | `UNVERIFIED` — finding aberto |
| Ausência de N+1 em volume | `UNVERIFIED` — smoke encontrou concern |

## 19. Findings

### Média — Scribe documenta campos proibidos

Local: `.scribe/endpoints/24.yaml:167-235` e `.scribe/endpoints/23.yaml:616-659`, originados
das regras `prohibited` das FormRequests Instructor. Impacto: contrato/documentação oferece
payloads que a API rejeita. Correção parcial aplicada (`bodyParameters()` explícitos); permanece
necessário filtrar regras `prohibited` no extractor/configuração do Scribe ou separar a fonte de
documentação sem alterar a validação de runtime.

### Média — potencial N+1 no Instructor results

Local: `app/Modules/Assessment/Services/InstructorResultScope.php:27-55`. Impacto: crescimento de
queries por questionnaire/curso na listagem de results; o smoke funcional passa, mas não prova
escalabilidade. Requer otimização/benchmark delimitado em task posterior.

### Resolvidos durante I-04 — falsa prova de cleanup e labels stale

Local: `tests/e2e-http/instructor/i02-roster-progress-attachments.php:209-218` e nomes dos testes
I-02/I-03. Corrigidos, reexecutados e verdes; não são blockers atuais.

## 20. Remaining Deferred

Preservados, sem implementação nesta task: publish/unpublish Instructor, assignment/reassignment,
paid external, MediaProvider/upload, quiz avançado/manual grading, certificates Instructor,
Student self-service, plugins, WS2 e WS3. O finding de performance e o ajuste definitivo de
filtragem Scribe ficam como manutenção posterior; não foram convertidos em decisão de produto.

## 21. Closure Verdict

`INSTRUCTOR_COMPLETE_WITH_EVIDENCE_PENDING`.

O boundary funcional e de segurança mínima está comprovado, mas o closure canônico fica pendente
até a documentação Scribe deixar de listar body params proibidos e o concern de performance ser
avaliado/aceito em task própria. Não há blocker/high finding de ownership, tenant isolation, PII,
financial mirror, Assessment integrity ou least privilege.
