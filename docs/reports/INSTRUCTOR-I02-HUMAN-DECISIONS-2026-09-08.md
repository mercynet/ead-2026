# Instructor I-02 — Decisões humanas — 2026-09-08

## 1. Executive Summary

**Verdict:** `I02_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS`

O menor contrato suficiente para I-02 está fechado: roster e progresso ficam limitados aos
Courses próprios do Instructor; matrícula manual é somente gratuita; a resposta usa uma projeção
mínima de aluno; e o ciclo de vida da matrícula continua sob autoridade Admin. Assessment/results,
matrícula paga `external` e o `MediaProvider` permanecem fora deste slice.

Esta é uma decisão de contrato, não uma alegação de implementação. A evidência desta análise é
`STATIC_EVIDENCE_ONLY`. O I-01 confirmou a área `/api/v1/instructor` para Course → Module → Lesson,
mas seus próprios gaps registram roster, progresso, PII e enrollment externo como não iniciados.

## 2. Roster Ownership

### Boundary

O Instructor pode listar e consultar somente matrículas que satisfaçam simultaneamente:

1. `enrollments.tenant_id` é o tenant resolvido no `ApiContext`;
2. o Course pertence ao mesmo tenant;
3. `courses.instructor_id = actor.id`;
4. o aluno é membro permitido do tenant.

O Instructor **não** possui roster tenant-wide. `course_id` é apenas um filtro de redução quando a
query já está ancorada no conjunto de Courses próprios; nunca é o controle de autorização.

O Instructor pode abrir uma matrícula individual somente dentro desse mesmo escopo. Matrícula de
Course de outro Instructor, de Course `instructor_id = null` (Admin-owned) ou de outro tenant produz
404 defensivo no envelope canônico `not_found`, sem distinguir “existe, mas não pertence”.

### Statuses

O status persistido continua sendo `pending | active | expired | cancelled`.

- Default do roster: `active` — alunos com acesso operacional atual.
- Filtro explícito: `pending`, para acompanhar aprovação de matrícula manual; `expired` e
  `cancelled`, para histórico/retomada operacional.
- Matrículas históricas inativas não entram por default, mas não são apagadas nem confundidas com
  conclusão pedagógica.
- `completed` não é status de matrícula; conclusão permanece no progresso do Course.

Filtros mínimos: `course_id` opcional, `status` opcional e paginação por cursor. Não é necessário
um filtro livre por `user_id` no contrato mínimo; uma consulta individual pode existir, desde que a
query seja primeiro restringida ao Course próprio. Busca textual por nome/e-mail é `LATER` e não deve
ser usada para ampliar o escopo.

## 3. PII Allowlist

O Resource de roster do Instructor deve expor somente:

| Campo | Decisão | Motivo |
|---|---|---|
| `user.id` | **ALLOW** | Identidade estável para operação da matrícula/progresso. |
| `user.name` | **ALLOW** | Identificação pedagógica do aluno. |
| `user.avatar` | **ALLOW** | Identificação visual mínima da turma, se disponível. |
| `user.email` | **DENY no I-02** | Não há operação de comunicação que torne o e-mail necessário; login/contato é PII e exige decisão própria. |
| `user.phone` | **DENY** | Não existe no contrato operacional do slice. |
| CPF/NIF/documento fiscal | **DENY** | Não é necessário para acompanhamento pedagógico. |
| Endereço | **DENY** | Não é necessário para acompanhamento pedagógico. |
| Data de nascimento | **DENY** | Não é necessário para acompanhamento pedagógico. |
| `tenant_id` | **DENY na resposta** | É controle interno de escopo, não dado pedagógico do aluno. |
| Dados financeiros | **DENY** | Instructor não consulta ledger, preço contratado, Order ou Payment. |
| Metadata interna | **DENY** | Não é contrato de operação pedagógica e pode conter dados indevidos. |

`course_id`, status da matrícula e campos de progresso são dados da relação pedagógica, não uma
licença para devolver o `User` completo. `EnrollmentResource` atual inclui `user.id`, `name` e
`tenant_id` (`app/Modules/Learning/Http/Resources/Enrollment/EnrollmentResource.php:28-34`), mas
isso não autoriza reutilizar o Resource como contrato Instructor: o novo Resource deve ser uma
projeção própria e não deve eager-loadar PII fora da allowlist.

O inventário LGPD atual registra `name`, `email`, `cpf`, `headline`, `bio`, `avatar`, `linkedin_url`
e `twitter_url` em `config/lgpd.php`; nenhum campo novo de usuário é necessário para I-02. Acesso ou
alteração de PII continua auditável pelo model `User`/`LogsActivity`.

## 4. Progress Visibility

### MUST_FOR_I02

Para cada matrícula visível no roster, o Instructor pode ver:

- percentual agregado do Course;
- aulas concluídas e total de aulas elegíveis, contando somente Lessons publicadas e ativas;
- última atividade (`last_watched_at`, com fallback definido pelo implementation slice);
- status da matrícula, `enrolled_at` e `access_expires_at`;
- detalhe por Lesson do Course próprio: `lesson_id`, progresso percentual, `is_completed`,
  `completed_at`, `last_watched_at` e `time_spent_seconds`.

O percentual agregado pode usar o valor persistido em `Enrollment.progress_percentage`, desde que a
regra de denominador seja a da spec. A fonte detalhada já existe em `LessonProgress`, que contém
percentual, conclusão, tempo e timestamps; `UpdateProgressAction` também atualiza o agregado da
matrícula e a progressão de conclusão.

### CAN_WAIT_FOR_I03

- attempts, respostas, score, aprovação e resultados de quiz;
- certificados e estado de emissão/revogação;
- qualquer leitura de Assessment de aluno.

Esses dados não entram no Resource de roster/progresso de I-02. A permission nominal
`assessment.*` não cria visibilidade por si só; I-03 precisa validar parent/ownership e definir seu
contrato próprio.

### LATER

- agregação visual por Module como recurso independente;
- progresso por LessonMedia e `watched_seconds` por mídia;
- histórico completo de rewatches/sessões, heatmaps, retenção, ranking, cohort e analytics;
- exportações, alertas e dashboards.

O agrupamento por Module pode ser derivado das Lessons e não deve transformar I-02 em plataforma de
analytics. `LessonMediaProgress.watch_sessions` é tracking granular de consumo, não requisito para
o primeiro acompanhamento pedagógico do Instructor.

## 5. Free Enrollment

O contrato novo de Instructor deve ser uma operação dedicada de matrícula manual **FREE**:

- área `/api/v1/instructor`, tenant-scoped, com o guard exato da área;
- Course obrigatório, do tenant atual, atribuído ao Instructor (`instructor_id = actor.id`),
  publicado e ativo;
- `user_id` obrigatório e resolvido para um usuário `student` não excluído do mesmo tenant;
- switch do tenant `manual_free_by_instructor` obrigatório;
- `manual_free_requires_approval` respeitado: sem o switch, nasce `active`; com o switch, nasce
  `pending`;
- `tenant_id`, `status`, `billing_type`, `created_by_instructor_id` e qualquer ownership são
  derivados pelo servidor e proibidos no payload;
- `created_by_instructor_id` recebe o actor somente para auditoria;
- preço, desconto, valor e gateway não são recebidos do cliente.

Uma repetição para a mesma combinação tenant + aluno + Course que já tenha matrícula `pending` ou
`active` é idempotente: devolve a matrícula corrente sem criar outra, sem novo evento e sem duplicar
efeitos financeiros. Após `cancelled` ou `expired`, uma nova solicitação pode criar uma nova matrícula
corrente. Não há alteração silenciosa do registro histórico.

“FREE” não significa “sem qualquer rastro financeiro”. O contrato financeiro atual exige, para a
concessão manual sem `billing_type`, um espelho determinístico de zero-consideration: Order `paid`
do tipo `direct`, valores zero, um `OrderItem` do Course e Payment `free` automático/resolvido. Isso
é auditoria/idempotência, não cobrança: I-02 não chama gateway, não inicia charge, não confirma
pagamento e não emite `OrderPaidEvent`. A regra está em
`docs/specs/40-financial/subspecs/orders-payments.md` e é exercitada por
`tests/Feature/Financial/EnrollmentFinancialMirrorTest.php`.

## 6. Paid External Boundary

`billing_type=external` permanece `HUMAN_DECISION_REQUIRED` no domínio, mas pode ser completamente
excluído de I-02.

Na nova superfície Instructor/I-02:

- `billing_type` não deve ser aceito como payload opcional; o FormRequest deve rejeitá-lo;
- tentativa de matrícula em Course pago ou tentativa explícita de `external` deve retornar
  `422 validation_error`, sem criar Enrollment, Order, Payment, outbox, evento financeiro ou chamar
  gateway;
- não existe confirmação, reconciliação, aprovação financeira nem transição `pending → active` para
  esse caso dentro de I-02.

O código legacy atual permite paid external e cria Enrollment `pending`
(`app/Modules/Learning/Actions/Enrollment/StoreEnrollmentAction.php:41-43,54-87`); isso é estado
existente de compatibilidade, não contrato novo. Antes de implementar I-02, a rota nova não deve
reutilizar essa Action genérica sem uma proteção explícita de free-only. A compatibilidade legacy não
é expandida nem silenciosamente reinterpretada nesta tarefa.

## 7. Enrollment Lifecycle Authority

Instructor **não pode**:

- cancelar ou remover matrícula;
- suspender/alterar status;
- reativar matrícula histórica;
- alterar `billing_type`;
- confirmar pagamento manual ou qualquer pagamento.

O Instructor só cria a concessão free conforme §5 e lê o resultado dentro do Course próprio. A
reativação de acesso ocorre por nova matrícula após `cancelled`/`expired`, não por mutação do registro
histórico.

Admin mantém a autoridade tenant-wide existente para alteração/cancelamento e confirmação manual;
Developer conserva o override global explícito. A matriz atual já reflete isso: as permissions
`learning.enrollments.update` e `.delete` não incluem `instructor` em `config/permissions.php`,
enquanto `.list`, `.view` e `.create` incluem o Instructor. A permission nominal nunca substitui o
ownership do Course.

## 8. Media / Materials

Não há decisão humana adicional bloqueando o metadata slice de I-02, desde que o escopo permaneça:

- `LessonMedia` é recurso 1:N da Lesson própria; pode haver múltiplos registros;
- Instructor pode fazer list/show/create/update/delete de **metadata** apenas em Lessons transitivamente
  próprias (`Lesson → Module → Course → actor`);
- `CourseMaterial` é recurso distinto, extra do Course próprio; Instructor pode fazer
  CRUD/list/show e download conforme contrato da área;
- CourseMaterial não vira LessonMedia, e LessonMedia não vira CourseMaterial;
- `file_path`/storage path interno não é contrato de resposta; download deve devolver URL temporária
  quando aplicável, não expor caminho interno bruto;
- não há upload real, proxy binário, novo provider, novo adapter ou decisão de URL além do contrato
  provider-aware já existente.

O `MediaProvider` continua uma decisão humana separada. Ele não bloqueia CRUD de metadata, mas
bloqueia upload/storage/provider real. A regra de publicação já fechada pelo Admin também confirma
que mídia não é requisito de readiness; I-02 não deve inventar essa dependência.

## 9. Security / Privacy

### Findings estáticos relevantes

| ID | Severidade | Caminho source → sink | Decisão de I-02 |
|---|---|---|---|
| `I02-S1` | Alta | `course_id` do request → `StoreEnrollmentAction` busca só por `tenant_id` → cria matrícula para Course de outro Instructor (`StoreEnrollmentAction.php:36-49,75-87`). | Nova Action/entrypoint deve filtrar Course por tenant + `instructor_id` antes de criar. Foreign owner sai como 404 defensivo. |
| `I02-S2` | Alta | Request de listagem → `ListEnrollmentsAction` filtra só tenant e eager-loada usuários → roster tenant-wide (`ListEnrollmentsAction.php:14-29`). | Query deve nascer do conjunto de Courses próprios; `course_id` é narrowing filter. |
| `I02-S3` | Alta | Enrollment carregada por tenant → `EnrollmentPolicy::view` retorna true para Instructor sem testar owner do Course (`EnrollmentPolicy.php:64-72`) → exposição individual de matrícula. | Policy/Action própria deve validar Course owner transitivo antes do Resource. |
| `I02-S4` | Média | Enrollment → `EnrollmentResource` retorna `tenant_id` do usuário (`EnrollmentResource.php:28-33`). | Resource Instructor separado; não devolver tenant id nem User completo. |

Controles obrigatórios:

- tenant scope explícito em Actions/Services e `tenant.access` na stack da área;
- ownership composto Course → Enrollment → progress antes de qualquer eager load de aluno ou
  progresso;
- 404 defensivo para outro Instructor, Course Admin-owned e outro tenant quando o recurso é
  individual;
- IDs numéricos não são segredo, mas enumeração não deve revelar existência: mesma forma de envelope
  e status para recurso inexistente e fora do ownership;
- filtros e sorts devem ser allowlistados; não aceitar coluna arbitrária;
- Resources por área, com campos mínimos e sem `UserResource` completo;
- eager loads limitados a `user:id,name,avatar` e relações pedagógicas já escopadas;
- nenhum Course, Enrollment ou progress de B pode entrar na resposta de A apenas porque o aluno
  também está matriculado em Courses de A.

Caso “aluno em A e B”: aparece no roster de A somente com a matrícula/progresso do Course de A, e
no roster de B somente com o escopo de B. Não existe uma resposta agregada tenant-wide para o
Instructor. Cross-tenant é bloqueado pelo contexto/tenant access; IDs e PII não alteram essa regra.

### Coupling boundary

I-02 deve permanecer em Learning + o model de identidade compartilhado `Core\Models\User`. A ponte
para o espelho financeiro continua sendo `EnrollmentCreatedEvent`/Contract existente; não se deve
importar `Financial\Models\Order` ou qualquer model de Assessment para montar roster/progresso. Isso
mantém o acoplamento intermodular no contrato/evento estável e deixa attempts/results para I-03.

## 10. Decisions for I-02

| Decisão | Contrato fechado |
|---|---|
| Roster scope | Matrículas de Courses do tenant atual com `course.instructor_id = actor`; nunca tenant-wide. |
| Roster status | Default `active`; `pending`, `expired`, `cancelled` somente por filtro explícito. |
| PII | `user.id`, `user.name`, `user.avatar`; sem email, documento, endereço, nascimento, tenant id, financeiro ou metadata interna. |
| Progress | Course %, concluídas/total, última atividade e detalhe mínimo por Lesson; sem analytics de rewatch/media. |
| Assessment boundary | Attempts/results/certificates/quiz fora de I-02; decisão/implementação em I-03. |
| Free enrollment | Course próprio publicado/ativo, aluno student do mesmo tenant, switch respeitado, status derivado, replay idempotente, sem cobrança/gateway; espelho financeiro zero-consideration obrigatório pelo contrato existente. |
| Paid external | Excluído de I-02; nova superfície rejeita com 422 e não cria efeitos. |
| Lifecycle | Instructor não cancela/remove/suspende/reativa/altera billing/confirma pagamento; Admin mantém autoridade. |
| Media/material | Metadata CRUD/list/show/download próprios estão prontos como decisão; upload/provider real continua fora. |

## 11. Deferred for I-03/Later

### I-03

- attempts, answers, scores, pass/fail e resultados;
- certificados, emissão/revogação e sua projeção para o Instructor;
- parent/ownership de Questionnaire/Question e composição do Assessment básico;
- qualquer exposição de PII adicional necessária para avaliação.

### Later / separate human gate

- paid external: aprovação, reconciliação, ledger/espelho pós-aprovação e transição financeira;
- MediaProvider: adapter, upload real, storage, proxy/pre-signed contract novo e provider lifecycle;
- email, telefone ou comunicação com aluno;
- rollups por Module como contrato dedicado e analytics pedagógico avançado;
- histórico de rewatches/sessões, progresso por mídia, cohort, ranking, alertas e exportação;
- assignment/reassignment de Course ou Assessment. `instructor_id = null` permanece Admin-owned e
  não é auto-claimed por Instructor;
- publish/unpublish de Course/Lesson pelo Instructor, já preservado fora do I-01.

## 12. Final Verdict

`I02_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS`

As decisões humanas necessárias para iniciar I-02 estão fechadas com um corte mínimo: roster own,
PII reduzida, progresso pedagógico básico, matrícula free idempotente e metadata de mídia/material.
Os itens diferidos são explicitamente não bloqueantes porque não entram no slice: Assessment/results,
paid external, MediaProvider/upload e analytics avançado.

**I-02 pode iniciar: yes**, limitado a esse contrato. Nenhum comportamento existente foi promovido a
runtime por esta análise; as findings `I02-S1`–`I02-S4` devem virar testes discriminantes antes da
implementação.
