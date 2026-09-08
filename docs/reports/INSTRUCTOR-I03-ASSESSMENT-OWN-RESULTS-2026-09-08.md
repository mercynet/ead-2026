# Instructor I-03 — Assessment Own Surface + Results

Data: 2026-09-08
Verdict: I03_COMPLETE

## 1. Baseline

- HEAD: 8df531fbc826c79aa4073dfbb70ee7a191ad7cb7 (main).
- Provenance: I-01 e I-02 já estavam no working tree; I-03 foi implementado no mesmo
  working tree, sem stage, commit ou push.
- Fonte de decisão: docs/reports/INSTRUCTOR-I03-HUMAN-DECISIONS-2026-09-08.md,
  I03_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS.
- A superfície legacy /api/v1/assessment foi preservada e não foi convertida em superfície
  canônica de Instructor.

## 2. RED Evidence

Antes da implementação, os REDs iniciais falharam com 404 defensivo porque a superfície canônica
não existia (5 failed). Foram confirmados como blockers:

1. listagem própria de Questionnaire;
2. isolamento de Questionnaire/Question entre Instructors;
3. criação com parent próprio e rejeição de parent foreign;
4. leitura de resultado próprio;
5. imutabilidade/composição depois de attempt.

Após a implementação, o Feature I-03 ficou verde: 11 passed (165 assertions).

## 3. Routes

Foram criadas 16 rotas em /api/v1/instructor/assessment:

- Questionnaire own CRUD;
- GET/POST de composição, PATCH de reorder e DELETE de detach;
- Question own CRUD;
- GET de results e GET/{id} de result.

Todas usam auth:sanctum, area.guard:instructor, resolução/obrigatoriedade de tenant e
tenant.access. Permissions canônicas continuam derivadas de config/permissions.php; ownership
é verificado adicionalmente por Policy e Actions.

## 4. Ownership Model

InstructorAssessmentScope restringe por tenant, instructor_id e parents retornados pelo
AssessmentCatalog. Questionnaire próprio exige Course próprio ou Lesson cujo Module aponta para
Course próprio. O payload não controla tenant, instructor ou parent arbitrário.

Questions também são tenant + creator-owned. A Policy não substitui ownership por permission:
permission nominal habilita a operação, mas o recurso precisa pertencer ao actor no tenant atual.

## 5. Questionnaire

Implementados list, show, create, update e delete próprios. A criação deriva tenant e instructor do
ApiContext; type/parent aceitam somente course ou lesson. Admin-owned com instructor_id = null não
entra no scope Instructor.

## 6. Questions

Implementados list, show, create, update e delete próprios. Categories são validadas pelo Contract
de Learning no tenant atual; a taxonomia não foi transformada em ownership da Question. O Resource
Instructor não expõe correct_options, tenant ou PII.

## 7. Composition

Implementados list de Questions anexadas, attach, detach e reorder. Attach aceita somente Questions
próprias, sem duplicação; reorder exige conjunto fechado; a mesma Question própria pode ser
reutilizada em Questionnaires próprios.

## 8. Attempt Immutability

Qualquer attempt do Questionnaire torna o Questionnaire imutável para update, delete, attach,
detach e reorder. Question anexada a Questionnaire histórico fica imutável e não pode ser editada
ou excluída. As Actions bloqueiam antes da mutação, com erro canônico de validação e sem side
effect parcial.

## 9. Results

InstructorResultScope nasce do mapa Questionnaire próprio → Course próprio e cruza com IDs de
alunos matriculados nesse Course. Não parte de attempts tenant-wide para filtrar depois. O Resource
expõe somente attempt id, questionnaire id/resumo, student id/name/avatar, score, percentage,
pass/fail, timestamps, tempo e attempt number.

## 10. Answers

Answers são uma projeção least-privilege por questão: resposta dada, questão/tipo, correção,
points earned e feedback pedagógico permitido. Não são expostos email, telefone, documentos,
tenant id, dados financeiros, correct_options, regras internas ou snapshot técnico bruto.
Scoring continua server-side e a resposta E2E tentou forjar points_earned=999, recebendo o valor
calculado pelo servidor.

## 11. Security

- area guard e auth protegem a superfície;
- tenant vem do contexto;
- parents são resolvidos transitivamente pelo Contract de Learning;
- Questionnaire e Question usam ownership próprio nas Actions/Policy;
- results exigem simultaneamente Questionnaire próprio, Course próprio e matrícula do aluno;
- A não alcança B no mesmo tenant;
- cross-tenant falha antes de criar;
- Admin-owned (instructor_id = null) permanece invisível;
- nenhuma importação nova cruza internals de módulos: Assessment usa Contract de Learning.

## 12. Feature / Architecture

- Feature I-03: 11 passed (165 assertions).
- Assessment regression: 57 passed (430 assertions).
- Learning ownership regression: 39 passed (491 assertions).
- RBAC/security/tenant: 16 passed (677 assertions).
- Architecture completa: 33 passed (1188 assertions).
- PHPStan: 503/503, sem erros.
- Pint: pass.
- git diff --check: pass.

As provas Architecture discriminam prefixo/guard Instructor, controllers finos, ausência de
leakage Admin, Resources próprios, ownership no scope, results derivados de Course/matrícula,
fronteira de módulo e scoring server-side.

## 13. Scribe

composer docs terminou com exit 0 e gerou as 16 rotas Instructor I-03. Os endpoints reais de
Questionnaire, Questions, composition, results e answers foram documentados. Warnings existentes
de requests legados sem bodyParameters() permanecem fora do slice.

## 14. E2E

Spec: tests/e2e-http/instructor/i03-assessment-own-results.php.

- runner exit: 0;
- resultado: 20 passou, 0 falhou;
- base URL: http://localhost:8083;
- DB: ead2026_e2e;
- jornada: Instructor A/B, Courses/Lesson boundary, Questions, composition, Admin-owned null,
  student attempt, scoring, results, answers, isolamento, cross-tenant e congelamento pós-attempt;
- cleanup: teardown do runner; consulta posterior confirmou users=0, tenants=0,
  questionnaires=0, quiz_questions=0, quiz_attempts=0.

## 15. Regression

Além das suites acima, foram executados Scribe, Pint, PHPStan e git diff --check. O harness de
invariantes scripts/ai/verify-changes.sh terminou com exit 0: `Invariantes do diff verdes (10
arquivo(s) de tests/Architecture)`.

## 16. Deferred

Preservados fora de I-03: standalone, Module parent, quiz avançado, manual grading, analytics
avançado, assignment/transfer, publish/unpublish Instructor, paid/external, MediaProvider,
plugin lifecycle, certificates Instructor e mudanças no lifecycle mutante de Enrollment.

## 17. Remaining Instructor MUST

I-03 não deixa MUST aberto dentro do slice autorizado. Permanecem para autorização futura os itens
deferred de I-01/I-02 e os slices posteriores, especialmente I-04/Student e certificados.

## 18. Verdict

I03_COMPLETE: Questionnaire own, Question own, parents Course/Lesson próprios, composition,
isolamento A/B e cross-tenant, Admin-owned isolation, results/answers least-privilege,
imutabilidade pós-attempt, scoring/snapshots preservados e evidências Feature, Architecture,
Scribe e E2E estão verdes. I-04, Student, WS2 e WS3 não foram iniciados.
