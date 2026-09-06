# Decisões Humanas para `ADMIN_COMPLETE` — 2026-09-06

## 1. Decision Classification

### Escopo e evidência

Esta é uma decisão documental, sem alteração de produto. A classificação de evidência é
`STATIC_EVIDENCE_ONLY`: foram consultados o contrato do repositório, specs, ADRs, código, testes,
cenários E2E e relatórios históricos; não houve execução atual de Feature, Architecture, Scribe ou
E2E nesta tarefa.

`ADMIN_COMPLETE` é interpretado pelo boundary já definido para `ADMIN-OPS`: operação do tenant,
usuários, conteúdo, categorias, publicação, matrícula administrativa e `cash/manual`. Assessment
completo, consumo Student, autoria própria do Instructor, upload real, marketplace e lifecycle
completo de plugins não são pré-requisitos desse boundary. Ver `docs/ROADMAP.md:9-21,42-50` e
`docs/reports/ADMIN-CLOSURE-TARGETING-2026-09-06.md:13-25`.

| Decisão | Classificação | Fechamento para Admin |
|---|---|---|
| Assessment ownership | `SHOULD_DECIDE_BEFORE_ADMIN_COMPLETE` | Não bloqueia o mínimo `ADMIN-OPS`, que não exige Assessment completo, mas precisa estar decidido antes de qualquer superfície Admin de questionários/questões. É fechada neste relatório. |
| Fronteira quiz core/advanced | `CAN_WAIT_FOR_PLUGIN_WORK` | O core simples existente não precisa absorver capacidades avançadas para fechar Admin; a fronteira completa fica com a capability/plugin. |
| Nomenclatura System/Custom | `MUST_DECIDE_BEFORE_ADMIN_COMPLETE` | Categoria e vínculo curso-categoria fazem parte da operação Admin. O contrato recomendado confirma o vocabulário System/Custom já adotado pelo ADR-002. |
| MediaProvider | `CAN_WAIT` | Admin pode operar metadados de mídia; upload/provider real não é requisito de publicação nem do mínimo operacional e não depende de uma decisão de persona. |
| Matrícula externa | `CAN_WAIT_FOR_INSTRUCTOR` | O fluxo existente é uma matrícula manual do Instructor para cobrança externa; Admin fecha com matrícula administrativa e `cash/manual`, sem depender da reconciliação externa. |
| Lifecycle de plugins | `CAN_WAIT_FOR_PLUGIN_WORK` | O preset `cash` e a configuração de gateway tenant já sustentam o caminho Admin; marketplace, assinatura e lifecycle completo ficam fora. |
| Publication/readiness | `MUST_DECIDE_BEFORE_ADMIN_COMPLETE` | Publicação é uma ação Admin e define se o conteúdo pode ser descoberto/consumido. Sem um critério mínimo, `ADMIN_COMPLETE` fica semanticamente aberto. A regra é fechada neste relatório. |

As duas decisões `MUST` são, portanto, nomenclatura/semântica de categoria e readiness de
publicação. Assessment ownership é uma decisão `SHOULD` que também é fechada agora por reduzir uma
ambiguidade material futura, sem ampliar artificialmente o boundary de Admin.

## 2. Assessment Ownership Decision

### Decisão recomendada

Adotar uma separação explícita entre ownership operacional e ownership pedagógico:

1. **Admin é owner operacional tenant-wide.** Pode listar, consultar e gerir Assessment básico de
   qualquer curso, aula ou questionário do próprio tenant, inclusive quando o recurso tem um
   Instructor pedagógico diferente.
2. **Instructor é owner pedagógico.** `instructor_id` identifica somente o autor/responsável
   pedagógico, não o operador administrativo.
3. **Admin não vira Instructor por criar um recurso.** Ao criar `Questionnaire` ou `QuizQuestion`,
   Admin deve deixar `instructor_id = null`. `null` significa “recurso operado pelo tenant, ainda
   sem autor pedagógico atribuído”; não significa que o Admin é o autor.
4. **Não há inferência silenciosa de autor.** O ator autenticado, o tenant e o parent não devem
   preencher `instructor_id` por conveniência. Um futuro `assign/transfer` explícito, autorizado e
   auditado pode atribuir um Instructor; essa operação não faz parte desta decisão nem deve ser
   inventada agora.
5. **Instructor fica restrito ao próprio escopo pedagógico.** Pode criar e gerir questionários e
   questões com `instructor_id` próprio e só quando o `Course`/`Lesson` associado também estiver no
   seu ownership. Um recurso criado por Admin com `instructor_id = null` continua Admin/tenant-owned
   até uma atribuição explícita; não entra automaticamente no escopo do Instructor.
6. **Admin pode gerir qualquer Assessment do tenant, mas não alterar ownership como efeito lateral.**
   Atualizar título, regra de aprovação, ativação ou vínculo não troca `instructor_id`; transferência
   é uma operação separada.

A escolha de `null` em vez do Admin é coerente com a regra já aplicada ao conteúdo: curso criado na
superfície Admin nasce sem `instructor_id` e não transforma Admin em owner pedagógico
(`docs/specs/20-catalog-learning/subspecs/courses-modules-lessons.md:71-74`;
`tests/Feature/Api/Learning/AdminContentOperationsApiTest.php:15-45`). Ela também evita criar uma
relação artificial entre identidade administrativa e autoria pedagógica.

### Invariantes

- `tenant_id` do Assessment é derivado do `ApiContext`; nunca vem do payload.
- `instructor_id` é nullable e representa autoria pedagógica; nunca é preenchido com o Admin só
  porque ele executou a operação.
- O payload não pode redefinir `tenant_id`, `instructor_id`, `quizable_type` ou a relação de pai.
- Admin só alcança recursos do próprio tenant; cross-tenant continua sendo 404 defensivo.
- Instructor só alcança seu próprio Assessment e os parents que lhe pertencem; não recebe visão
  tenant-wide por possuir a mesma permission nominal.
- Uma alteração administrativa não modifica o histórico de tentativas. Questões usadas continuam
  imutáveis e scoring/snapshots continuam server-side (`docs/specs/30-assessment/spec.md:49-58`;
  `docs/specs/30-assessment/subspecs/attempts-scoring.md:35-51`).
- A área e o permission ceiling continuam independentes do ownership: Admin não é Developer/Mzrt e
  Instructor não é Admin.

### Evidência e implicações atuais

A spec de Assessment ainda descreve `instructor_id` como coluna em `Questionnaire` e
`QuizQuestion` (`docs/specs/30-assessment/subspecs/questionnaires-questions.md:12-35`), mas a
matriz de RBAC concede as mesmas permissions de questionários/questões a Admin e Instructor
(`docs/specs/00-architecture/rbac.md:171-189`). Isso não define ownership por si só.

O código atual confirma a ambiguidade que esta decisão resolve: `StoreQuestionnaireAction` grava o
usuário autenticado em `instructor_id` (`app/Modules/Assessment/Actions/Questionnaire/StoreQuestionnaireAction.php:30-54`)
e `StoreQuestionAction` faz o mesmo (`app/Modules/Assessment/Actions/Question/StoreQuestionAction.php:24-39`).
Os testes existentes usam Developer como ator (`tests/Feature/Api/Assessment/QuestionnaireApiTest.php:24-73`;
`tests/Feature/Api/Assessment/QuestionApiTest.php:27-84`), portanto não provam a semântica Admin.
Além disso, `app/Modules/Assessment/Routes/api.php:9-41` não possui `area.guard:admin`; esta
decisão não declara essa superfície implementada.

### Implicações para Admin

- A futura superfície Admin de Assessment pode ser tenant-wide sem atribuir autoria ao Admin.
- O Admin pode gerir Assessment básico ligado a qualquer curso do tenant, inclusive curso sem
  Instructor.
- A ausência de `instructor_id` não deve impedir criação, edição, associação ou operação
  administrativa quando o tenant e os parents forem válidos.
- Assessment não precisa entrar no mínimo `ADMIN_COMPLETE` somente para validar a regra; sua
  convergência area-first continua sendo um slice próprio caso entre no escopo do produto.

### Implicações futuras para Instructor

- A implementação de Instructor deve filtrar por ownership pedagógico, não apenas por
  `assessment.*` permission.
- Para Assessment de curso/aula própria, a operação deve validar simultaneamente o parent e o
  `instructor_id` do recurso.
- Para um recurso Admin-owned (`instructor_id = null`), o Instructor não ganha acesso por estar
  associado informalmente ao tenant. Se o produto precisar desse fluxo, deverá existir uma ação de
  atribuição clara, com ator, destino e auditoria.
- Tentativas, respostas e certificados não são transformados em ownership de autor; suas regras de
  consumo e histórico permanecem separadas.

## 3. Category Semantics Decision

### Vocabulário canônico

O contrato público e a discussão de produto devem usar somente **System** e **Custom**:

- **System** = categoria global mantida pela plataforma.
- **Custom** = categoria criada e mantida por um tenant.

`SYSTEM`, `DEFAULT` e `TENANT_CUSTOM` podem aparecer em histórico/compatibilidade interna, mas não
são nomes alternativos no payload, Resource, permission ou documentação nova. Se um tipo precisar
ser exposto no futuro, seus valores canônicos serão `system` e `custom`; `is_system` permanece um
detalhe de persistência/autorização e é proibido no payload. A decisão por escopo vive na
Policy/Action, não em `if` de controller.

O vocabulário e a separação de superfícies já estão registrados no ADR-002
(`docs/specs/00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md:57-83`)
e na subspec de catálogo (`docs/specs/20-catalog-learning/subspecs/catalog.md:47-56,117-135`).

### Contrato recomendado

| Tipo | Persistência/ownership | CRUD | Visibilidade e uso |
|---|---|---|---|
| **System** | `tenant_id = null`, `is_system = true`; global, owner = plataforma/Mzrt | Developer na área Mzrt; Admin e Instructor não criam, editam ou excluem | Visível como catálogo de referência para os tenants. Pode ser selecionada no vínculo com curso; não é pai de categoria Custom. |
| **Custom** | `tenant_id = X`, `is_system = false`; pertence exclusivamente ao tenant X | Admin do tenant X na área Admin | Visível somente dentro do tenant X. Admin pode CRUD e vincular a cursos do tenant; Instructor pode listar/ler, mas o CRUD e o vínculo canônico permanecem Admin-only. |

Consequentemente:

- Admin gerencia apenas **Custom** e pode selecionar **System** ou **Custom** ao categorizar curso
  do próprio tenant.
- Instructor não administra a taxonomia. Pode consultar as categorias disponíveis para seu tenant,
  mas não cria, edita, exclui ou altera o pivô `category_course` nesta decisão.
- Mzrt/developer administra **System**; isso é operação global e não faz parte do fechamento Admin.
- Uma categoria System não é copiada para cada tenant: o tenant a seleciona como referência global.

### Nome, slug e colisões

- `normalized_name` é a chave semântica: lowercase, sem acento, trim e espaços colapsados.
- Não pode haver dois nomes normalizados ativos no escopo System global nem no mesmo tenant Custom.
- Um nome Custom não pode colidir com nome System. Tenants diferentes podem reutilizar o mesmo nome.
- `slug` é derivado do nome normalizado, não é um segundo canal para contornar a unicidade. Se for
  exposto como lookup, deve ser único no escopo em que é resolvido e a resolução deve carregar o
  escopo; não se exige slug global entre todos os tenants.
- Colisões de slug derivadas (por exemplo, nomes diferentes que geram o mesmo slug) são recusadas
  no mesmo escopo. A resposta deve ser 422 e não pode deixar uma categoria parcialmente criada.
- Nome/slug de categoria soft-deleted não bloqueia novo nome ativo segundo a regra do ADR, mas o
  restore deve recusar ou exigir rename quando colidir com uma categoria ativa.

### Hierarquia e lifecycle

- Parent System só pode ser System.
- Parent Custom só pode ser Custom do mesmo tenant.
- Cross-scope e cross-tenant são proibidos; categoria System serve para seleção, não para aninhar a
  árvore Custom.
- A árvore é N-level com `parent_id` e materialized path/depth; ciclos são recusados.
- System com cursos vinculados não pode ser excluída.
- Custom com cursos exige confirmação explícita e detach; nenhum pivô pode apontar para categoria
  soft-deleted.
- Restore devolve a categoria sem vínculos de curso, sujeito à regra de colisão ativa.

Esses invariantes fecham a semântica para Admin sem exigir migration/schema nesta tarefa. O fato de
parte do alvo estrutural do ADR-002 ainda estar em `Pending`
(`docs/specs/20-catalog-learning/tasks.md:99-105`) é delta de implementação, não uma nova decisão
de produto.

## 4. Publication Readiness Decision

### Regra mínima

Um curso pode ser publicado pelo Admin quando, e somente quando, todos os itens abaixo forem
verdadeiros no tenant do curso:

1. O curso não está `archived` e está ativo (`is_active = true`).
2. Existe pelo menos um `CourseModule` pertencente ao curso.
3. Existe pelo menos uma `Lesson` pertencente a um módulo desse curso, não soft-deleted, com
   `status = published` e `is_active = true`.

Essa é a menor regra que evita publicar um agrupador vazio ou um curso cuja grade não possui uma
aula consumível. Não se cria um requisito paralelo de “módulo ativo” porque o modelo atual de
`CourseModule` não possui esse estado; a existência de uma aula publicável é a condição efetiva.

A transição é explícita: CRUD de curso não publica nem arquiva. `draft → published` é a publicação;
`published → draft` é a despublicação; `archived` é terminal para o MVP. Uma chamada de publicação
em curso já publicado pode ser idempotente, sem alterar `published_at`; publicar ou despublicar um
arquivado retorna 422 e não altera estado. A primeira publicação preenche `published_at`, que não é
apagado por unpublish/re-publish.

### Respostas às perguntas de escopo

| Questão | Decisão |
|---|---|
| Curso pode publicar sem Instructor? | **Sim.** `instructor_id = null` é válido para curso operado pelo Admin. Admin não precisa virar owner pedagógico. |
| Precisa de módulo? | **Sim, pelo menos um.** A estrutura canônica é `Course → Module → Lesson`. |
| Precisa de pelo menos uma aula? | **Sim, pelo menos uma aula publicável** dentro de um módulo do curso. |
| Aula precisa estar ativa/publicável? | **Sim:** `status = published`, `is_active = true` e não soft-deleted. O curso não deve publicar aulas draft por efeito lateral. |
| Quiz é obrigatório? | **Não.** Quiz é capability opcional do conteúdo. Se `certificate_requires_quiz` estiver ativo, a aprovação do quiz é requisito de certificado, não de publicação do curso. |
| Mídia é obrigatória? | **Não.** Uma aula de texto ou outro conteúdo válido pode ser publicada sem upload/provider; mídia é complementar. |
| Grátis/pago muda readiness? | **Não.** `price_cents = 0` e preço positivo usam a mesma regra. Gateway, checkout e matrícula são preocupações de acesso/venda, não pré-condições de publicação. |
| Archived pode voltar? | **Não no MVP.** `archived` é terminal para publish/unpublish. Um futuro restore/reopen deve ser uma transição explícita separada. |
| O que acontece com curso órfão? | `instructor_id = null` não é órfão: é curso administrado pelo tenant. Se o Instructor for removido, o FK pode ficar null e o curso permanece sob Admin; não há auto-delete nem auto-unpublish. Curso sem módulo/aula publicável permanece draft e falha readiness com 422. |

### Invariantes testáveis

- Falha de qualquer pré-condição devolve o envelope padrão de 422 e deixa curso, módulos, aulas e
  `published_at` inalterados.
- A consulta de publicação nunca atravessa tenant, module ou lesson de outro escopo.
- Publicação não cria Instructor, não altera `instructor_id`, não cria quiz e não cria mídia.
- O catálogo só considera o curso consumível quando `status = published` e `is_active = true`; a
  grade consumível só considera lessons publicadas e ativas, coerente com
  `Course::isActive()` (`app/Modules/Learning/Models/Course.php:120-128`) e `Lesson::isActive()`
  (`app/Modules/Learning/Models/Lesson.php:76-90`).
- A operação é dedicada e auditável; update genérico não pode mudar status.

### Estado observado e delta sem implementação

O contrato de conteúdo já diz que publish/unpublish é transição Admin e que drafts não são
acessíveis ao aluno (`docs/specs/20-catalog-learning/subspecs/courses-modules-lessons.md:60-74,95-103`).
Porém, `PublishCourseAction` hoje só bloqueia `archived` e grava `published` sem verificar módulos ou
lessons (`app/Modules/Learning/Actions/Course/PublishCourseAction.php:8-26`). O cenário
`tests/e2e-http/learning/courses-publish.php:10-20,63-74` inclusive prepara um curso vazio para
publicação. Portanto, esta decisão fecha o contrato, mas não afirma que a regra já esteja
implementada.

Há um ponto operacional a resolver no slice de implementação: a criação Admin de lesson força
`status = draft` e proíbe status no payload (`app/Modules/Learning/Actions/Lesson/StoreLessonAction.php:19-29`;
`app/Modules/Learning/Http/Requests/Admin/StoreLessonRequest.php:9-18`). A superfície Admin precisará
de uma transição explícita de publicação de lesson, ou de outro mecanismo formal equivalente, antes
de o fluxo Admin conseguir satisfazer o readiness. O curso não deve publicar a lesson como efeito
colateral.

## 5. Deferred Decisions

### Core/advanced quiz — `CAN_WAIT_FOR_PLUGIN_WORK`

Não bloqueia Admin agora. O core simples já tem tipos básicos (`lesson`, `course`, `standalone`;
`single_choice`, `multiple_choice`, `true_false`), snapshot e scoring server-side
(`docs/specs/30-assessment/subspecs/questionnaires-questions.md:47-75`;
`docs/specs/30-assessment/subspecs/attempts-scoring.md:35-58`). A decisão diferida é somente a
fronteira completa de capacidades avançadas, entitlement, activation, permissions e lifecycle.

Não adicionar requisito de quiz à publicação para “resolver” essa lacuna. Quando o trabalho ocorrer,
deve seguir ADR-005: capability advanced é código do core gated por activation/entitlement/config,
não uma flag solta
(`docs/specs/00-architecture/decisions/005-plugins-capability-gated-gateway-como-plugin.md:32-47`).

### MediaProvider — `CAN_WAIT`

Não bloqueia Admin agora. A superfície Admin já pode administrar registros/metadados de
`LessonMedia`; upload real, media library e a costura `MediaProvider` permanecem fora do slice
(`docs/specs/20-catalog-learning/subspecs/media-ratings.md:76-84,100-116`;
`docs/specs/20-catalog-learning/tasks.md:116-117`). A regra de publicação deliberadamente não exige
mídia. Não se decide aqui adapter, proxy binário, contrato de URL ou storage.

### Matrícula externa — `CAN_WAIT_FOR_INSTRUCTOR`

Não bloqueia Admin agora. A spec coloca `billing_type=external` no fluxo de matrícula manual do
Instructor, com estado `pending` para curso pago; a reconciliação financeira após aprovação está
explicitamente pendente (`docs/specs/20-catalog-learning/subspecs/enrollment-progress.md:78-86`;
`docs/specs/20-catalog-learning/tasks.md:84-85,119`). O caminho Admin de matrícula e `cash/manual`
usa o contrato de confirmação manual e não precisa inventar adapter, aprovação externa ou espelho
financeiro novo.

### Lifecycle de plugins — `CAN_WAIT_FOR_PLUGIN_WORK`

Não bloqueia Admin agora. O tenant novo recebe `cash` e o Admin já tem configuração de gateway
tenant como instância de plugin; isso sustenta o caminho manual sem exigir marketplace ou assinatura
completa (`docs/specs/00-architecture/decisions/005-plugins-capability-gated-gateway-como-plugin.md:38-53`;
`docs/specs/50-ecosystem-plugins/spec.md:42-60`). Ficam diferidos estados completos de instalação,
assinatura, suspensão, expiração, grants, quotas, billing e efeitos de desativação. Não criar estados
ou efeitos adicionais por analogia.

## 6. Admin Impact

### Decisões fechadas que afetam Admin

- **Categorias:** Admin opera Custom no próprio tenant, lê/seleciona System e não executa CRUD de
  System. `is_system`, `tenant_id` e parent arbitrário não são campos de escopo aceitos.
- **Publicação:** Admin pode publicar curso sem Instructor, desde que a grade tenha conteúdo mínimo
  publicável. Publish/unpublish é uma operação dedicada, não consequência de CRUD.
- **Assessment:** quando a futura superfície Admin existir, Admin terá visão/gestão tenant-wide e
  não será gravado como `instructor_id`; esta decisão evita que o fechamento de conteúdo gere
  ownership pedagógico acidental.
- **Dependências:** Admin não espera Instructor completo, Student completo, MediaProvider,
  Assessment advanced ou marketplace para continuar o control-plane mínimo.

### Riscos e contradições que permanecem como implementação

1. O código de criação de Assessment ainda atribui o ator autenticado como Instructor, portanto não
   implementa a decisão para Admin.
2. Assessment ainda está em rota legacy sem guard de área Admin; sua futura convergência deve ser
   uma decisão/implementação de superfície própria, sem tratar `v1/assessment` como Admin-compliant.
3. `PublishCourseAction` ainda permite curso vazio; a regra de readiness está fechada, mas falta
   implementação e teste.
4. Existe divergência entre a superfície legacy de criação de curso, cujo teste histórico espera o
   ator em `instructor_id` (`tests/Feature/Api/Learning/Course/CourseCrudApiTest.php:15-55`), e a
   superfície Admin recente, que verifica `instructor_id = null`
   (`tests/Feature/Api/Learning/AdminContentOperationsApiTest.php:15-45`). A decisão deste relatório
   vale para a superfície Admin canônica; o comportamento legacy não deve reabrir a regra.

### Admin pode continuar?

**Sim, pode continuar sem ambiguidade decisória**, desde que o trabalho próximo respeite este
boundary e trate publication readiness como delta de implementação. Isso não significa que
`ADMIN_COMPLETE` já possa ser declarado: ainda são necessários convergência de superfícies, testes,
Scribe e evidência E2E/runtime atuais, conforme o gate de fechamento documentado em
`docs/reports/ADMIN-CLOSURE-TARGETING-2026-09-06.md:267-275`.

## 7. Required Canonical Updates

Nenhum dos itens abaixo foi aplicado nesta tarefa; são atualizações necessárias quando houver uma
task autorizada de reconciliação canônica:

1. **Manifesto de capability:** retirar `category-system-custom-naming`, `assessment-ownership` e
   `learning-publication-readiness` do estado `HUMAN_DECISION_REQUIRED` ou apontá-los para esta
   decisão; manter `quiz-core-advanced-boundary`, `media-provider`, `external-enrollment` e
   `plugin-lifecycle` como gates humanos diferidos.
2. **Assessment spec/subspec:** registrar Admin como operador tenant-wide, Instructor como owner
   pedagógico, `instructor_id = null` para criação Admin, filtro own do Instructor e atribuição
   futura explícita. Alinhar a matriz de permissions sem confundir permission com ownership.
3. **Learning catalog/ADR-002:** confirmar System/Custom como único vocabulário, preservar
   `SYSTEM/DEFAULT/TENANT_CUSTOM` apenas como histórico se necessário e registrar a regra de slug
   derivado/escopado, hierarquia same-scope e uso read-only do Instructor.
4. **Learning course lifecycle:** registrar o predicado mínimo de readiness, archived terminal,
   curso sem Instructor válido, ausência de requisito de quiz/mídia/preço e a necessidade de uma
   transição explícita para lesson publicável. O update genérico continua sem status.
5. **Tasks e roadmap:** criar os deltas de implementação e aceite para ownership Assessment e
   publish readiness; não marcar a decisão documental como `RUNTIME_VERIFIED`. Manter matrícula
   externa, MediaProvider, advanced quiz e lifecycle de plugins nos workstreams diferidos.
6. **Testes/contrato:** quando implementado, adicionar testes discriminantes para Admin tenant-wide
   sem ownership pedagógico, Instructor own, category System/Custom, readiness com curso vazio,
   sem módulo, sem lesson ativa/publicada, curso sem Instructor, archived, gratuito/pago e ausência
   de quiz/mídia. Gerar Scribe após a superfície final; nenhuma execução histórica deve ser promovida
   automaticamente a evidência atual.

Esses updates são referências de governança, não autorização para alterar schema, migration, código,
testes ou configuração nesta tarefa.

## 8. Final Verdict

### Decisões Admin fechadas

- Nomenclatura e semântica **System/Custom**.
- Regra mínima de **publication/readiness**.
- Ownership de **Assessment** fechado como decisão `SHOULD`: Admin operacional tenant-wide,
  Instructor pedagógico, criação Admin com `instructor_id = null`.

### Decisões diferidas

- Fronteira completa quiz core/advanced — `CAN_WAIT_FOR_PLUGIN_WORK`.
- MediaProvider — `CAN_WAIT`.
- Matrícula externa — `CAN_WAIT_FOR_INSTRUCTOR`.
- Lifecycle completo de plugins — `CAN_WAIT_FOR_PLUGIN_WORK`.

O repositório pode prosseguir com Admin sem transformar nenhuma dessas quatro decisões diferidas em
dependência artificial. A declaração de `ADMIN_COMPLETE` continua condicionada à implementação das
regras fechadas e à evidência corrente de contrato, testes e runtime.

**`ADMIN_DECISIONS_CLOSED_WITH_DEFERRED_ITEMS`**
