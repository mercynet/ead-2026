# Student S-01 — Commercial Human Decisions — 2026-09-08

## 1. Executive Summary

Verdict: **`S01_DECISIONS_CLOSED_WITH_CONDITIONAL_CAPABILITIES`**.

O contrato mínimo do primeiro paid pilot pode ser fechado sem completar toda a área Student:
consumo integral exige `Enrollment` própria, `active` e não expirada; curso gratuito pode ter
preview read-only sem matrícula; a superfície comercial Student é separada do catálogo público;
conteúdo, mídia, material e progresso usam projeções próprias e sem metadata interna.

O primeiro piloto deve ser assisted onboarding: Admin/operator cria a matrícula por Action e
confirma pagamento externo/cash quando aplicável. Checkout automático, gateway novo, upload e
MediaProvider novo não são pré-requisitos.

Assessment fica como **capability condicional**. Um curso sem Assessment pode ser vendido. Um curso
que o ofereça ou exija só pode ser vendido depois da prova Student de acesso ao parent, tentativa
própria, snapshot/scoring server-side e fluxo start → answer → finish → result.

Certificado não é prometido em v0.1. A existência histórica da emissão não o torna parte da oferta.
Se um curso for configurado para prometer certificado, ele sai do baseline do piloto e passa a ser
uma capability condicional, com superfície Student e evidência próprias.

## 2. Free Course / Enrollment Decision

Decisão: **opção C** — catálogo/preview pode ser aberto, mas o consumo integral exige `Enrollment`.

Aplicação precisa:

- `price_cents = 0` torna o curso gratuito, mas não cria automaticamente identidade de consumo;
- Course gratuito publicado e ativo pode aparecer no catálogo/preview público;
- uma Lesson explicitamente `is_free` pode ser consumida como preview read-only, sem matrícula;
- o restante do Course, inclusive Course gratuito, só é consumo Student após `Enrollment` própria
  `active` e não expirada;
- preview não aparece em “meus cursos”, não libera materiais e não cria nem atualiza progresso;
- matrícula gratuita continua sendo criada pela operação autorizada e deixa o espelho financeiro
  zero-consideration previsto para o domínio.

Esta escolha preserva o conceito existente de `lesson.is_free` como degustação, mas evita o estado
problemático de consumo completo sem identidade, Enrollment, auditoria, roster ou reconciliação
futura. Também mantém o mesmo contrato para curso gratuito e pago depois que o aluno entra no
consumo integral.

## 3. Enrollment Access States

### Regra de acesso

Somente `Enrollment.status = active` e `access_expires_at` nulo ou futuro permitem consumo
integral. `pending`, `expired` e `cancelled` não permitem abrir conteúdo integral nem registrar
progresso.

| Estado | “Meus cursos” | Conteúdo integral | Progresso persistente | Histórico próprio |
|---|---:|---:|---:|---:|
| `active`, não expirada | sim | sim | sim | sim |
| `active`, expirada por data | não | não | não | sim |
| `pending` | não | não | não | sim |
| `expired` | não | não | não | sim |
| `cancelled` | não | não | não | sim |

“Meus cursos” representa apenas cursos que o Student pode consumir agora. Um futuro endpoint
explícito de histórico de matrículas próprias pode mostrar `pending`, `expired` e `cancelled`, mas
isso não os torna entitlement ativo e não é requisito para a lista S-01.

Uma nova matrícula ativa deve ser escolhida de modo determinístico, pela matrícula ativa mais
recente do par tenant + user + course. Não se deve reutilizar `CURRENT_STATUSES` para conceder
acesso, pois ele também contém `pending` no código atual.

Tentativas de consumir um recurso que não é entitlement do Student retornam o envelope canônico de
**404 `not_found`**, sem revelar se havia Course, Enrollment ou Lesson correspondente. A exceção é
de superfície: autenticação ausente continua 401; persona fora da área Student continua 403
`area_forbidden`; tenant incompatível continua 403 `access_denied` quando rejeitado pelo contexto.

## 4. My Courses Contract

Endpoint: `GET /api/v1/student/courses`.

Contrato mínimo:

- área `student`, stack tenant-scoped canônica e `area.guard:student` exato;
- filtro implícito por tenant resolvido e pelo usuário autenticado, sem `user_id` no query string;
- inclui somente Course publicado, ativo e com Enrollment própria `active` não expirada;
- inclui cursos gratuitos somente depois da Enrollment ativa;
- exclui `pending`, `expired`, `cancelled`, drafts, archived, Course inativo e Course de outro
  tenant;
- usa `cursorPaginate`, ordenação determinística e eager loading do resumo necessário;
- retorna Course summary, resumo da Enrollment e aggregate de progresso próprio;
- não é catálogo público, não aceita filtro de escopo e não reutiliza a listagem administrativa de
  Enrollment.

A projeção Student não expõe `tenant_id`, `user_id`, `created_by_instructor_id`, `instructor_id`,
`price_cents`, relações de usuário ou metadata de authoring, salvo se um campo comercial seguro for
explicitamente necessário em decisão posterior.

## 5. Course Tree

O contrato é de navegação incremental, não de payload recursivo ilimitado.

- `GET /api/v1/student/courses/{courseId}`: resumo do Course acessível, Enrollment própria e
  progresso aggregate;
- `GET /api/v1/student/courses/{courseId}/modules`: módulos do Course acessível, paginados por
  cursor, ordenados por `sort_order` e `id`;
- `GET /api/v1/student/courses/{courseId}/modules/{moduleId}/lessons`: Lessons do módulo correto,
  paginadas por cursor, ordenadas por `sort_order` e `id`;
- `GET /api/v1/student/lessons/{lessonId}`: detalhe e consumo de uma Lesson autorizada.

Visibilidade:

- Course: somente `status = published` e `is_active = true`, pertencente ao tenant resolvido e
  coberto pelo entitlement do Student;
- Module: deve pertencer ao Course e tenant corretos e ter ao menos uma Lesson visível;
- Lesson: somente `status = published` e `is_active = true`, pertencente ao Module e Course
  selecionados;
- drafts, archived, inativos, órfãos e relações parent inconsistentes não aparecem;
- o schema atual não possui lifecycle independente de Module. Para S-01, “módulo ativo” significa
  pertencimento ao Course ativo e presença de Lesson publicada/ativa; não se cria nem se expõe um
  `is_active` de Module como parte desta decisão.

Os itens de árvore são summaries de navegação: id, título, ordem e estado de consumo necessário.
Conteúdo textual, URLs de mídia e material não são carregados na árvore; são obtidos apenas no
endpoint de consumo autorizado.

## 6. Lesson Content / Media

### Content

O contrato público coloca o texto pedagógico em `Lesson.content`, no detalhe da Lesson. A origem
histórica de armazenamento não faz parte do contrato: um adaptador pode projetar registros legados,
mas não deve devolver dois textos concorrentes nem expor estrutura interna.

O Student pode receber apenas o conteúdo da Lesson publicada/ativa que ele pode consumir. O preview
pode receber o conteúdo da Lesson `is_free`, sem persistência de progresso.

### Media

Quando a Lesson é autorizada, `media` contém somente registros ativos e ordenados com os campos
necessários ao consumo:

- identificador, `media_type`, provider normalizado quando útil ao cliente;
- URL consumível (`player`/`direct`) ou URL temporária para storage interno;
- `url_expires_at` quando aplicável;
- duração, ordem e configuração pedagógica mínima de progresso.

Para provider externo, a URL pode ser pública/player. Para `internal`/`s3`, a URL é temporária,
gerada somente depois da autorização e vinculada ao recurso permitido.

Não fazem parte do Resource Student: `file_path`, `storage_path`, `storage_disk`, `provider_ref`
interno quando não necessário, `metadata` genérico, ownership, configuração do tenant, credenciais,
provider secrets ou qualquer metadata técnica sem função de consumo. `provider_config` não deve
reintroduzir paths sob outro nome.

`LessonMedia` não é proxy binário e a API não deve carregar arquivos grandes. Upload e lifecycle do
MediaProvider permanecem fora de S-01.

## 7. Course Materials

Student pode listar e baixar materiais somente para Course publicado/ativo com Enrollment própria
`active` e não expirada. Preview, `pending`, `expired` e `cancelled` não liberam material, mesmo em
Course gratuito.

Contrato mínimo:

- listagem Student separada, scoped por tenant + Course + Enrollment do usuário;
- recurso de material sem `file_path`, basename de path interno, `instructor_id`, `tenant_id` ou
  estatísticas administrativas;
- download por operação autorizada no Course/material corretos;
- resposta de download com identificador de download e URL temporária/assinada, além de
  `url_expires_at` quando disponível;
- backend não faz proxy binário;
- path persistido continua validado server-side com prefixo do tenant, sem traversal e com disk
  allowlisted.

O Resource administrativo atual não é reutilizado: ele expõe `file_path` e `instructor_id`, que
não são contrato de consumo Student.

## 8. Progress Eligibility

Student só pode criar ou atualizar `LessonProgress` quando todos os vínculos abaixo forem verdadeiros:

1. tenant resolvido e pertencente ao usuário;
2. Course publicado e ativo;
3. Module e Lesson pertencentes ao Course correto;
4. Lesson publicada e ativa;
5. Enrollment própria `active` e não expirada;
6. a Lesson não estiver sendo usada apenas como preview sem matrícula.

O progresso persistente deve ficar identificado por tenant + user + Course + Enrollment + Lesson.
O Student lê somente a própria projeção. O aggregate Course é o `progress_percentage` da própria
Enrollment, calculado considerando somente Lessons publicadas e ativas; `completed_at` da Enrollment
é o estado pedagógico de conclusão, não mudança de status para `completed`.

`pending`, `expired` e `cancelled` não criam novo progresso nem atualizam progresso existente.
Preview sem Enrollment não cria estado persistente reconciliável depois. Rewatch é permitido e não
reduz o maior progresso já salvo; heartbeat repetido deve ser idempotente.

Ausência de Enrollment no endpoint de progresso mantém o contrato já fixado: 404 `not_found`, não
403.

## 9. Assessment Commercial Boundary

Status: **`CONDITIONAL_CAPABILITY`**.

O primeiro paid pilot pode vender Course sem Assessment. Assessment não é pré-condição pedagógica
do Course nem motivo para adiar a primeira receita.

Um Course que habilite ou exija Assessment só é elegível para venda quando o Student Assessment
estiver comprovado na superfície própria, com:

- parent Course/Module/Lesson publicado, ativo e acessível pela Enrollment do Student;
- tentativa própria, tenant-scoped e não enumerável;
- snapshot de questionário/questões e gabarito mantidos server-side;
- cliente enviando somente seleção de resposta;
- score/pass-fail calculados no servidor;
- start → answer → finish → result por HTTP real e E2E com side effects;
- nenhum gabarito, `correct_options` ou explanation sensível no Resource.

O Assessment Student é um slice condicional, não bloqueia o S-01 base. Entretanto, o operador não
pode prometer quiz apenas porque o CRUD/Instructor Assessment existe.

## 10. Certificates

Status comercial do primeiro piloto: **`NOT_PROMISED_IN_V0_1`**.

Courses do baseline devem ser vendidos sem promessa de certificado e, para evitar emissão implícita,
devem estar configurados com `certificate_enabled = false` ou ser retirados do cohort até que a
capability seja fechada.

A emissão histórica, `CourseCompletedEvent` e verificação pública não constituem superfície Student
comercial comprovada. Se um Course habilitar/prometer certificado, a capability torna-se condicional
à implementação e evidência de list/show Student, emissão idempotente, download/PDF se prometido,
controle de PII e E2E. Isso não é requisito para a primeira receita sem essa promessa.

## 11. PII / Own Data

O allowlist Student é mínimo e próprio:

- **Enrollment:** id da matrícula se necessário para navegação, Course summary, status, validade,
  data de matrícula e progresso próprio;
- **Progress:** Lesson/Course identifiers necessários, percentual, tempo, posição, conclusão e
  timestamps próprios;
- **Course consumption:** Course/Module/Lesson IDs, títulos, slugs, ordem, conteúdo permitido,
  mídia/material consumíveis e estado de consumo próprio.

Não expor `user_id`, `tenant_id`, CPF, email, tokens, dados de outros Students, roster, relações
de Instructor, `created_by_instructor_id`, ownership interno, financial ledger/order/payment,
secrets de provider, authoring metadata ou paths de storage.

Não há PII novo necessário para este contrato; portanto, nenhuma alteração em `config/lgpd.php` é
recomendada por S-01. Qualquer PII futuro deve entrar no inventário e no audit trail antes de ser
serializado.

## 12. Security Semantics

| Cenário | Resultado esperado |
|---|---|
| Student A tenta Course/Enrollment de Student B no mesmo tenant | 404 `not_found`, envelope idêntico ao recurso inexistente |
| Mesmo tenant, sem Enrollment | 404 `not_found` para consumo integral, material e progresso |
| `pending` | 404 para conteúdo integral, material e progresso; não aparece em “meus cursos” |
| `expired` | 404 para novo consumo; não aparece em “meus cursos”; permanece apenas em histórico próprio |
| `cancelled` | 404 para consumo; não aparece em “meus cursos”; permanece apenas em histórico próprio |
| Cross-tenant por ID em contexto válido | 404 `not_found`, sem confirmar existência |
| Header/tenant sem membership do Student | 403 `access_denied` pelo `tenant.access` |
| Course draft ou archived | 404 `not_found` na superfície Student |
| Course inativo ou não publicado | 404 `not_found` na superfície Student |
| Module de outro Course/tenant | 404 `not_found` |
| Lesson unpublished, draft ou inativa | 404 `not_found` |
| Lesson preview (`is_free`) publicada/ativa | conteúdo/media de preview podem ser lidos sem Enrollment; sem material e sem progresso |
| Persona não autorizada na área Student | 403 `area_forbidden` antes de binding/404 |
| Sem Sanctum | 401 `unauthenticated` |

Nenhuma negativa deve devolver `can_access=false` acompanhada de metadata que confirme um recurso
protegido. O 404 defensivo é usado quando a existência não deve ser inferida.

## 13. Performance Constraints

- `GET /student/courses` e toda listagem usam `cursorPaginate`;
- filtros e ordenações são allowlisted;
- consultas são tenant/user scoped antes do fetch e usam eager loading apropriado;
- tree é paginado/separado, sem payload recursivo ilimitado;
- conteúdo/media/material não entram automaticamente em listagens de Course/Module;
- progresso próprio é carregado em lote para a árvore, nunca resolvendo Enrollment uma vez por Lesson;
- URLs assinadas/temporárias só são geradas após autorização e somente para media/material permitido;
- a API não faz proxy binário grande;
- não há SLA arbitrário neste contrato, mas N+1 material conhecido, full scan evitável e payload
  ilimitado são bloqueadores antes do primeiro cohort.

## 14. S-01 Contract

| Decisão | Contrato fechado |
|---|---|
| Free Course access | Preview público/read-only pode existir; consumo integral requer Enrollment ativa |
| Enrollment required | **Sim** para consumo integral, inclusive Course gratuito |
| Allowed states | Somente `active` e não expirada |
| My courses | Courses published + active com Enrollment própria ativa/não expirada; cursor-paginated |
| Course tree | Course summary + módulos e Lessons em endpoints paginados/separados, somente parent correto e Course/Lesson published+active |
| Lesson content/media | `content` textual no contrato da Lesson; media ativa com URL consumível/temporária e metadata mínima; sem paths/config/secrets |
| Course material | List/download somente com acesso integral; download por URL temporária/assinada; sem path interno |
| Progress | Somente Student próprio com Course/Lesson acessíveis e Enrollment ativa; preview não persiste |
| Assessment | `CONDITIONAL_CAPABILITY`; não bloqueia Course sem quiz; Course com quiz exige prova Student |
| Certificate | `NOT_PROMISED_IN_V0_1`; qualquer promessa/configuração ativa fica fora do baseline |
| show_results | Não bloqueia S-01 base; adiado para Student Assessment; quando Assessment for vendido, flag deve ser respeitada server-side |
| PII | Own data only; projeções mínimas; sem PII/tenant/financial/authoring internals desnecessários |
| Security | Area guard Student, tenant isolation, own scope e 404 defensivo para existência protegida |

Implementação futura deve nascer em `/api/v1/student/*`, com Resource Student, `ApiContext`, Action,
Policy/Gate, FormRequest, Scribe e testes de área/tenant/ownership. Rotas legacy `/learning/*` e
`/assessment/*` não são o contrato de produto do piloto.

## 15. Paid Pilot Impact

O menor fluxo vendável é:

1. Mzrt provisiona tenant e preset `cash`;
2. Admin configura usuários e Course;
3. Instructor/Admin cria Module/Lesson e metadata de media/material;
4. Admin publica Course ativo;
5. Admin/operator cria Enrollment gratuita ou registra a venda externa e confirma `cash/manual`
   pela API;
6. Student autentica no tenant, lista “meus cursos”, navega o Course, abre Lesson/media/material e
   persiste progresso;
7. suporte opera manualmente cobrança externa, convite, reenrolamento e histórico.

Sim: Student pode ser fechado em **2 slices de implementação + 1 closure/evidence pass**:

- Slice 1: own access surface, `GET /student/courses`, Course summary/tree, Enrollment/access e
  isolamento;
- Slice 2: Lesson/content/media, materiais e progress read/write;
- Closure: Scribe, Architecture/Feature, E2E HTTP integrado, side effects, cleanup, performance
  smoke e receipt runtime.

Assessment condicional adiciona um terceiro slice somente para Courses que o vendam. Certificado
pode ficar após a primeira receita, desde que não seja prometido nem habilitado no cohort inicial.
Não há razão material para alterar as datas já estimadas no targeting Student:

| Cenário | Data alvo para fechar o baseline Student |
|---|---:|
| Best-case | **2026-09-22** |
| Realistic | **2026-10-06** |
| Conservative | **2026-10-27** |

Essas datas continuam condicionadas a upstream Admin/Instructor estável, ambiente E2E disponível e
à execução atual contra app/banco corretos. O estado atual continua `PAID_PILOT_NOT_READY` até a
closure evidencial.

## 16. Final Verdict

**`S01_DECISIONS_CLOSED_WITH_CONDITIONAL_CAPABILITIES`**.

As decisões humanas mínimas para iniciar S-01 estão fechadas. O baseline comercial é um Course
publicado/ativo, com consumo integral protegido por Enrollment `active` não expirada, onboarding e
pagamento assistidos, sem Assessment ou certificado prometidos. Preview gratuito existe apenas como
leitura sem progresso persistente.

Assessment permanece condicional por Course; `show_results` pertence ao slice Student Assessment.
Certificado fica fora da promessa v0.1 e só retorna ao escopo mediante decisão comercial explícita.

Este relatório fecha decisão documental, não implementação nem runtime verification. Nenhum endpoint
Student novo é considerado pronto até obter Feature + Architecture + Scribe + E2E HTTP + side
effects + cleanup + provenance atuais.
