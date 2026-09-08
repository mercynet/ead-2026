# Instructor I-03 — Decisões Humanas de Assessment — 2026-09-08

## 1. Executive Summary

**Verdict:** `I03_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS`

As decisões humanas mínimas para iniciar I-03 estão fechadas. O slice deve entregar Assessment
básico próprio do Instructor, com Questionnaire ligado somente a Course ou Lesson próprios,
Question própria reutilizável nos Questionnaires próprios, composição completa e leitura mínima de
results dos alunos que possuem matrícula no Course próprio.

`standalone`, parent `Module`, certificados, quiz avançado e qualquer operação de emissão/revogação
ficam fora do núcleo de I-03. O código atual contém superfície legacy tenant-wide e suporte latente
que não deve ser promovido automaticamente para a nova superfície `/api/v1/instructor`.

**Evidência:** `STATIC_EVIDENCE_ONLY`. Foram lidos `AGENTS.md`, o capability context, `docs/STATE.md`,
specs de Assessment/Learning/RBAC/LGPD, decisões e relatórios de Admin/I-01/I-02, código de
Questionnaire/Question/Attempt/Answer/Certificate/Course/Lesson e os testes existentes. Nenhuma
alteração de código, spec, task, STATE, rota, teste ou manifest foi feita.

## 2. Assessment Ownership

O boundary de I-03 é:

| Ator/recurso | Contrato |
|---|---|
| Admin | Operador tenant-wide do Assessment básico do tenant. Cria com `instructor_id = null`. |
| Instructor | Owner pedagógico. Cria com `instructor_id = actor.id` e só opera o próprio Assessment. |
| Permission | Necessária, mas não substitui ownership da instância nem ownership do parent. |
| Tenant | Derivado do `ApiContext`; nunca recebido como autoridade do payload. |
| Assignment/transfer | Fora de I-03; somente operação explícita, autorizada e auditável em slice futuro. |

Um Instructor só pode criar ou operar Questionnaire cujo parent seja Course/Lesson do mesmo tenant e
cujo Course raiz tenha `instructor_id = actor.id`. A relação Lesson → Module → Course é transitiva.
Questionnaire ou Question com `instructor_id = null` é Admin-owned e não entra automaticamente no
escopo do Instructor.

Esse boundary é consistente com `docs/specs/30-assessment/spec.md`, a canonicalização Admin e a
regra de I-01/I-02 de que Course com `instructor_id = null` permanece Admin-owned.

## 3. Questionnaire Parent Model

| Parent | Canônico para I-03? | Estado atual | Decisão |
|---|---:|---|---|
| Course | **Sim** | Model/morph, validação Admin e emissão de certificado existem; ownership Instructor do parent ainda precisa ser aplicado na nova superfície. | MUST. `type=course`, parent Course próprio. |
| Lesson | **Sim** | Model/morph e validação legacy existem; a tentativa atual trata Course/CourseModule e não fecha corretamente o snapshot de Lesson. | MUST. `type=lesson`, parent Lesson próprio; derivar Course e Module pelo parent. |
| Module | **Não** | Há tratamento latente de `CourseModule` em `StartAttemptAction`, mas o contrato de Questionnaire documenta Course/Lesson/standalone e o objetivo de I-03 não pede Module. | Não expor nem aceitar como parent Instructor. O Module continua estrutura de Learning, não parent de Questionnaire. |
| standalone | **Não na nova superfície Instructor** | A migration inicial usa `morphs('quizable')`, seguida de migration que torna `quizable_id/type` nullable; Admin/legacy já cria standalone. | Excluir de I-03 Instructor. Não corrigir schema nem promover suporte latente nesta tarefa; manter eventual compatibilidade existente fora do novo contrato. |

Para I-03, `type` e parent devem ser coerentes. Não haverá combinação `type=course` com Lesson,
nem parent arbitrário apenas porque o ID existe. A nova superfície deve obter o parent de contexto
ou validar conjuntamente tenant, tipo, Course raiz e Instructor owner; `quizable_id/type` não podem
ser um bypass de ownership.

O contrato de Lesson é deliberadamente explícito: o Questionnaire referencia a Lesson, mas o acesso
pedagógico e os results são resolvidos pelo Course proprietário da árvore. O comportamento latente
de `CourseModule` em `StartAttemptAction` não é contrato e não deve orientar I-03.

## 4. Questionnaire Composition

Todas as operações abaixo são **MUST** para I-03, pois CRUD de Questionnaire sem montagem não produz
um Assessment utilizável:

- criar Questionnaire próprio, com Course/Lesson próprio;
- listar e consultar Questionnaires próprios;
- atualizar e excluir Questionnaire próprio, respeitando a imutabilidade abaixo;
- criar Question própria;
- listar, consultar, atualizar e excluir Question própria;
- listar Questions anexadas ao Questionnaire;
- anexar Question própria ao Questionnaire próprio;
- desanexar Question;
- reordenar Questions por `sort_order`, com conjunto fechado do Questionnaire;
- reutilizar a mesma Question própria em mais de um Questionnaire próprio.

O attach não transfere ownership. Uma Question de Instructor A só pode ser anexada por A a
Questionnaires de A e no mesmo tenant. Question Admin-owned (`instructor_id = null`) não pode ser
anexada pela superfície Instructor, mesmo que o Admin a tenha criado no tenant correto. Não há
CRUD de categorias neste slice.

As listagens devem usar escopo próprio e paginação cursor quando forem listagens independentes.
O Questionnaire show deve conseguir representar a composição sem expor gabarito indevidamente.

## 5. Question Ownership

- Question criada pelo Instructor pertence ao Instructor: `instructor_id = actor.id`.
- Pode ser reutilizada entre Courses próprios por meio de Questionnaires próprios; Question não
  recebe ownership separado por Course.
- Não pode ser usada, editada, anexada, desanexada ou excluída por outro Instructor, ainda que no
  mesmo tenant.
- Não pode usar Question Admin-owned. Não existe auto-claim, herança ou transferência implícita.
- Categorias são taxonomia pedagógica compartilhada, não ownership de Question.
- Instructor pode selecionar categorias **System** globais e **Custom** do tenant atual, conforme a
  superfície permitida; não pode criar, editar ou excluir categorias.
- Category IDs precisam ser validados contra `AssessmentCatalog`: categoria System ou Custom do
  tenant atual; nenhuma categoria de outro tenant, soft-deleted ou fora do catálogo permitido.

O `System/Custom` boundary canônico permanece intacto. `category_ids` não são prova de ownership e
não podem ampliar o escopo da Question.

## 6. Attempt Immutability

Scoring permanece exclusivamente server-side. O cliente envia somente a resposta; score, aprovação,
`is_correct` e `points_earned` são calculados a partir do snapshot persistido no início da tentativa.
O gabarito, a explicação e os snapshots internos não são reconstituídos a partir do payload.

Regra mínima segura:

- Questionnaire pode ser criado/editado/completado enquanto não existir nenhuma tentativa iniciada.
- Depois que existir qualquer tentativa `in_progress` ou `completed`, o Questionnaire fica
  imutável para I-03: sem update, delete, attach, detach ou reorder. Isso inclui mudanças de
  título, passing score, limite, ativação e `show_results`; o snapshot histórico não pode ser
  divergente do contrato operacional.
- Question pode ser editada somente enquanto não tiver sido usada por nenhuma tentativa iniciada.
  Depois disso, criar uma nova Question/version é o caminho seguro.
- Question usada por tentativa não pode ser excluída. Question sem tentativa só pode ser excluída
  depois de desanexada de todos os Questionnaires; não há cascade silencioso da composição.
- Questionnaire sem tentativa pode ser excluído; Questionnaire com qualquer tentativa não pode ser
  excluído. O histórico de attempts/answers não é apagado por CRUD pedagógico.
- Detach e reorder são permitidos somente antes da primeira tentativa. Após a primeira tentativa,
  ambos são proibidos, preservando a ordem e o conjunto que originaram o snapshot.

O contrato é mais estrito que a checagem atual, que bloqueia Question apenas quando encontra attempt
`completed`; tentativa iniciada já é suficiente para congelar a estrutura.

## 7. Results Visibility

Instructor pode consultar somente attempts/results que satisfaçam simultaneamente:

1. tenant do attempt = tenant resolvido;
2. Questionnaire pertence ao Instructor;
3. parent é Course ou Lesson próprio, com Course raiz do Instructor;
4. o aluno tem registro de Enrollment no Course próprio;
5. o attempt pertence a esse aluno e ao Questionnaire desse Course.

O histórico continua visível enquanto houver o registro de Enrollment do Course próprio, inclusive
para acompanhamento de uma matrícula que posteriormente expirou ou foi concluída. Não há consulta
tenant-wide, por `user_id` livre ou por attempt ID sem a redução prévia ao Course próprio.

### Allowlist de resultado

Permitidos no Resource específico de Instructor:

- `attempt_id`;
- `questionnaire_id` e identificação mínima do Assessment próprio;
- aluno: `user.id`, `user.name`, `user.avatar`;
- `score` calculado pelo servidor, em escala 0–100;
- `percentage` somente como representação explícita do mesmo score, não como segundo valor autoritativo;
- `passed`/pass-fail;
- `started_at` e `completed_at` (`finished_at` no armazenamento);
- `time_spent_seconds`;
- número agregado de tentativas do mesmo aluno para o mesmo Questionnaire, limitado ao Course próprio;
- answers pedagógicas permitidas na seção seguinte, sem snapshot interno nem gabarito cru.

Não permitidos na resposta Instructor:

- `tenant_id`, tokens, dados internos de autorização ou IDs de infraestrutura;
- `questionnaire_snapshot`, `questions_snapshot` ou `question_snapshot` brutos;
- dados internos de cálculo além de score/percentage, pass-fail e pontos pedagógicos autorizados;
- email, telefone, documento, endereço, dados financeiros ou UserResource completo.

O resultado de attempt em andamento pode ser consultado apenas se a implementação realmente precisar
de acompanhamento ao vivo; o mínimo recomendado é listar resultados concluídos e mostrar attempt em
andamento sem score/pass-fail, preservando a mesma regra de ownership. Isso não autoriza o Instructor
a responder, iniciar ou finalizar attempts.

## 8. Answer Visibility

Para os únicos tipos core de I-03 — `single_choice`, `multiple_choice` e `true_false`, todos
auto-corrigidos — o Instructor pode ver:

- resposta selecionada pelo aluno;
- identificação/texto da Question a partir do snapshot sanitizado;
- resultado por Question (`is_correct`) e `points_earned`;
- feedback/explanation pedagógico da Question, sem enviar o snapshot interno completo.

O Instructor **não recebe `correct_options`/gabarito cru** no Resource de results nem qualquer campo
de scoring interno não necessário. A Question própria continua sendo administrável no seu escopo,
mas o endpoint de results aplica uma projeção mínima e não deve vazar o snapshot que contém o
gabarito.

Essay, correção manual, hotspots, gaps, penalties, limites sofisticados e outros tipos avançados
não fazem parte deste contrato. Não criar campos ou estados de manual grading por antecipação.

## 9. Certificates

Certificados **não são MUST de I-03**. O mínimo pedagógico deste slice é montar o Assessment e
consultar attempts/results. Conclusão básica já é acompanhada no Learning/I-02; uma lista de
certificados de alunos não é necessária para iniciar o slice.

Ficam para I-04/Student ou slice específico:

- listar ou consultar certificados por Course próprio;
- exibir status issued/revoked ao Instructor;
- revogar certificado;
- emitir manualmente;
- PDF e qualquer nova autoridade administrativa.

O Instructor não ganha revogação nem emissão manual por possuir `assessment.certificates.view`.
Verificação pública continua independente e não é uma capability Instructor.

## 10. PII

A allowlist de I-02 continua sendo a única projeção de aluno em I-03:

- `user.id`;
- `user.name`;
- `user.avatar`.

Assessment não precisa de PII adicional. Email, documento/CPF/NIF, telefone, endereço, nascimento,
dados financeiros, tenant ID e metadata interna permanecem **deny by default**. Nenhum model novo de
Assessment entra em `config/lgpd.php`; a projeção usa PII já inventariada no model `User` e não cria
campo pessoal novo.

Há evidência de que Resources/actions legacy ainda eager-loadam ou emitem email de Instructor
(`QuestionnaireResource` e `List/ShowQuestionnairesAction`), e que a superfície legacy não é o
contrato Instructor. I-03 deve usar Resources próprios e não copiar esse shape.

## 11. Same-Tenant Isolation

| Cenário | Comportamento esperado na superfície Instructor |
|---|---|
| Instructor A → Questionnaire de B no mesmo tenant | 404 defensivo `not_found` em show/update/delete/composition; não revelar existência. |
| Instructor A → Question de B no mesmo tenant | 404 defensivo `not_found`; nenhuma mutação ou attach. |
| Instructor A → Course de B no mesmo tenant | 404 defensivo `not_found`; parent não é aceito. |
| Instructor A → Lesson de B no mesmo tenant | 404 defensivo `not_found`; a cadeia Lesson → Module → Course deve falhar no owner. |
| Instructor A → attempt de aluno no Course de B | 404 defensivo `not_found`; attempt ID nunca substitui o filtro pelo parent próprio. |
| Aluno matriculado em Courses A e B | A vê somente attempts/results do Course A; B vê somente os do Course B. Não há agregação tenant-wide por aluno. |
| Assessment Admin-owned (`instructor_id = null`) | Não aparece em listagem Instructor e retorna 404 defensivo para show, update, delete, attach e results. Não há auto-claim. |
| Outro tenant | `tenant.access`/contexto bloqueia; listagens não retornam registros e lookup individual não denuncia existência. |

Admin continua podendo operar Assessment tenant-wide no seu próprio tenant pela área Admin, sem
transferir ownership e sem abrir essa área para Instructor.

## 12. Standalone/Morph Decision

**Decisão fechada:** standalone fica excluído da nova superfície Instructor de I-03; não bloqueia o
slice e não será corrigido/reprojetado agora.

A migration posterior já torna `quizable_id/type` nullable, o que explica a capacidade atual de
criar standalone em Admin/legacy. Porém, o contrato de execução e snapshot não fecha standalone como
parent pedagógico, e `StartAttemptAction` contém suporte latente divergente para `CourseModule`. O
menor contrato seguro é não promover nenhum desses comportamentos para Instructor.

Qualquer futuro standalone Instructor exigirá decisão própria sobre parent/escopo, start/snapshot,
results e schema final; a ausência dessa capability não pode ser preenchida implicitamente pelo
Codex. O I-03 novo aceita apenas Course e Lesson.

## 13. Deferred Advanced Assessment

Permanecem explicitamente fora do core:

- randomização avançada;
- essay/manual grading;
- hotspots e gaps;
- penalties;
- time/attempt limits sofisticados;
- analytics avançado, ranking, cohort e exportações;
- plugin/capability advanced sem activation/entitlement/config efetivos.

O núcleo é deliberadamente limitado aos três tipos objetivos existentes e à composição básica com
snapshot e scoring server-side.

## 14. I-03 Contract

I-03 pode iniciar com o seguinte escopo final:

1. superfície area-first `/api/v1/instructor`, tenant-scoped, com guard de Instructor e permissions
   próprias; rotas legacy de `/api/v1/assessment` não viram automaticamente superfície canônica;
2. Questionnaire próprio com parent obrigatório Course ou Lesson próprio; sem Module e sem
   standalone;
3. CRUD próprio de Questionnaire, condicionado à regra de attempts;
4. CRUD próprio de Question para `single_choice`, `multiple_choice` e `true_false`;
5. categorias apenas por seleção de System/Custom permitidas; sem CRUD e sem IDs cross-tenant;
6. composition completa: list attached, attach, detach e reorder, com reutilização de Question própria
   em múltiplos Questionnaires próprios;
7. attempts/results somente leitura para alunos com Enrollment no Course próprio, com allowlist de
   resultado e resposta definida acima;
8. scoring, gabarito e snapshots controlados pelo servidor; nenhuma autoridade para iniciar,
   responder ou finalizar attempt;
9. nenhum certificado, emissão, revogação, PII adicional, quiz advanced, assignment/transfer ou
   operação tenant-wide;
10. cobertura discriminante mínima para A/B no mesmo tenant, null-owner Admin-owned, parent spoof,
    cross-tenant, aluno em Courses A/B, imutabilidade após attempt e ausência de gabarito/PII.

## 15. Final Verdict

`I03_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS`

**I-03 pode iniciar:** **yes**.

Os itens deferred são deliberadamente não bloqueantes porque não pertencem ao menor contrato:
standalone, Module como parent, certificados, advanced quiz, manual grading, analytics, assignment/
transfer e qualquer PII além de `user.id`, `user.name` e `user.avatar`.

O status é decisão documental, não implementação nem runtime verification. Antes de codificar, o
slice deverá transformar este contrato em testes RED discriminantes e manter a separação entre a
compatibilidade legacy tenant-wide e a nova superfície Instructor own-only.
