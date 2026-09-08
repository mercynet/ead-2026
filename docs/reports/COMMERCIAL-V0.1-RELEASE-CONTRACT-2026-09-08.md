# Commercial v0.1 Release Contract — 2026-09-08

## 1. Executive Summary

**Verdict atual: `PAID_PILOT_NOT_READY`.**

O menor produto comercializável não é “o EAD completo”. É uma API v0.1 capaz de operar poucos
tenants com onboarding assistido, cursos publicados, autoria do Instructor, matrícula controlada,
consumo pelo Student, progresso persistido e Assessment básico quando o curso o oferecer.

O estado fornecido e a leitura do repositório confirmam uma fundação forte: MZRT está completo no
escopo fechado, Admin está completo no escopo autorizado, Instructor está em `INSTRUCTOR_COMPLETE`,
há autenticação tenant-scoped, conteúdo Learning, matrícula manual/cash, checkout e emissão de
certificado em partes separadas. Isso ainda não fecha a jornada comercial: a área Student canônica
não está fechada, o fluxo de Assessment do aluno não possui E2E HTTP de jornada, e a integração
checkout/webhook/pagamento automático continua fora do estado promovível.

O piloto pode vender e operar com cobrança comercial fora da plataforma e matrícula/cash assistida.
Isso é aceitável porque não altera o domínio: toda concessão continua passando por Action, Policy,
tenant scope, auditoria e efeitos idempotentes. Não é aceitável usar operação manual para inserir
linhas diretamente, marcar pagamento sem trilha ou contornar autorização.

Base documental principal: `AGENTS.md`, `docs/ROADMAP.md`, as specs de Core, Learning, Assessment,
Financial e Ecosystem, os relatórios de fechamento Admin/Instructor e o código/rotas atuais. Os
relatórios históricos são provenance; só execução atual contra app e banco adequados promove uma
capability a `RUNTIME_VERIFIED`.

## 2. Paid Pilot Definition

`PAID_PILOT_READY` significa:

- pelo menos um tenant real pode ser provisionado e configurado com operador MZRT;
- o Admin consegue administrar usuários, conteúdo, publicação e matrícula do próprio tenant;
- o Instructor consegue administrar conteúdo próprio e acompanhar roster/progresso/resultados
  próprios;
- um Student convidado consegue autenticar, ver seus cursos, abrir o conteúdo publicado, consumir
  aula/mídia/material permitido, persistir progresso e concluir Assessment básico quando aplicável;
- a operação pode usar cobrança externa e confirmação `cash/manual`, sem prometer checkout
  automático;
- isolamento de tenant, RBAC, ownership, PII least privilege, contrato `/api/v1`, Resources,
  envelopes, Scribe e evidência atual estão fechados;
- existe operação mínima de deploy, migração, backup/restore, logs, saúde, rollback e secrets;
- a jornada foi reproduzida por HTTP real em banco descartável, com side effects e cleanup
  conferidos, e passou pela auditoria de falso sucesso.

O milestone não promete automação total, escala empresarial, marketplace, pagamentos PSP ou todas as
capabilities de um LMS maduro.

## 3. Commercial Journey

| Passo | Estado atual | Classificação para v0.1 | Fechamento exigido |
|---|---|---|---|
| 1. MZRT cria/configura tenant | MZRT skeleton e provisionamento existem; o provisionamento cria tenant e primeiro Admin e inicializa o preset `cash`. | **MUST** — fundação disponível | Prova atual do endpoint, rollback transacional e nenhum segredo no retorno. |
| 2. Admin administra tenant | Users/invitation, conteúdo, categorias, publicação, matrícula Admin e cash/manual estão fechados no escopo autorizado. | **MUST** — `ADMIN_COMPLETE` no escopo autorizado | Manter superfície Admin/guard e não tratar lacunas de white-label/reporting como blocker do piloto. |
| 3. Admin cria/publica curso | Course → Module → Lesson, readiness e publish/unpublish existem. | **MUST** | Curso ativo, módulo e aula publicada/ativa; draft nunca chega ao Student. |
| 4. Instructor administra conteúdo próprio | `INSTRUCTOR_COMPLETE`, incluindo conteúdo, metadata de mídia/material, roster, progresso e Assessment próprio. | **MUST** | Ownership transitivo, sem transformar Admin em Instructor. |
| 5. Aluno é matriculado | Matrícula manual gratuita/Admin e cash/manual têm espelho/outbox idempotentes. | **MUST** | Para o piloto, Admin/operator executa matrícula e confirma recebimento externo; paid external não é atalho para bypass. |
| 6. Aluno acessa curso | Acesso legado e regra de `published`/enrollment existem, mas a superfície Student-first não está fechada. | **MUST** | Endpoint Student canônico, `area.guard:student`, tenant access e 404 defensivo. |
| 7. Consome aula/conteúdo | `GET` de aula e resolução de mídia/material existem em superfície legada; não bastam como fechamento Student. | **MUST** | Resource Student, somente conteúdo acessível; mídia não pode vazar para aluno sem entitlement/matrícula. |
| 8. Progresso é persistido | Heartbeat e conclusão, recalculo e evento de curso concluído existem em Learning. | **MUST** | Prova HTTP do Student, escopo por aluno, persistência, conclusão e repetição idempotente. |
| 9. Assessment básico quando aplicável | Core de questionnaire/question/attempt/score existe; fluxo Student e E2E Student ainda estão pendentes. | **MUST condicional** | Só vender curso com quiz depois de fechar start → answer → finish → result sem gabarito exposto. |
| 10. Instructor acompanha progresso/resultados | Roster/progresso/resultados próprios estão fechados em `INSTRUCTOR_COMPLETE`. | **MUST** | Resultado só de cursos próprios, PII mínima, sem visão tenant-wide indevida. |

## 4. Capability Matrix

| Capability | Classificação | Contrato mínimo e leitura do estado |
|---|---|---|
| Tenant provisioning | `MUST_FOR_PAID_PILOT` | Provisionamento MZRT transacional de tenant + primeiro Admin, domínio único, preset `cash`, sem contexto de tenant no endpoint. |
| Auth | `MUST_FOR_PAID_PILOT` | Sanctum opaco, login tenant-scoped, convite/reset, throttling e `/api/v1/auth/*`; `/core/auth/*` permanece compatibilidade. |
| Admin users | `MUST_FOR_PAID_PILOT` | List/show/invite/update/delete de Instructor/Student do tenant, sem aceitar `user_type`, `email`, `cpf`, `password` ou tenant no payload. |
| Categories | `SHOULD_BEFORE_PAID_PILOT` | System/Custom, hierarquia e vínculo já estão convergidos; o piloto pode operar sem taxonomia rica, mas deve manter leitura/uso seguro das categorias existentes. |
| Courses | `MUST_FOR_PAID_PILOT` | CRUD, preço em cents, draft e acesso publicado; Admin e Instructor não compartilham ownership por conveniência. |
| Modules | `MUST_FOR_PAID_PILOT` | Árvore pertencente ao curso correto, ordenação e isolamento. |
| Lessons | `MUST_FOR_PAID_PILOT` | Conteúdo publicado dentro de módulo/curso correto, lifecycle explícito e acesso condicionado à matrícula. |
| Media metadata | `MUST_FOR_PAID_PILOT` | Metadata/provider reference e URL consumível já configurados; não exige upload real nem `MediaProvider` avançado. |
| Materials | `SHOULD_BEFORE_PAID_PILOT` | Registro, caminho tenant-bound e download temporário podem ser usados; upload/media library real pode ser assistido manualmente. Se o curso vender material como parte essencial, sobe para MUST desse curso. |
| Publication | `MUST_FOR_PAID_PILOT` | Publish/unpublish explícito; curso ativo com módulo e lesson publicada/ativa; drafts não são consumo. |
| Instructor authoring | `MUST_FOR_PAID_PILOT` | Authoring own de Course/Module/Lesson, metadata, roster, progresso e Assessment own; estado declarado `INSTRUCTOR_COMPLETE`. |
| Roster | `MUST_FOR_PAID_PILOT` | Instructor vê apenas alunos de seus cursos e projeção mínima de PII. |
| Progress | `MUST_FOR_PAID_PILOT` | Heartbeat/conclusão por aluno, somente lessons publicadas/ativas no denominador, sem cruzamento de tenant. |
| Free/manual enrollment | `MUST_FOR_PAID_PILOT` | Admin/operator pode conceder acesso gratuito ou confirmar `cash/manual`; replay não duplica ledger, payment, outbox ou matrícula. |
| Assessment basic | `MUST_FOR_PAID_PILOT` quando oferecido | Core simples: single/multiple/true-false, snapshot server-side, score calculado no servidor, tentativa própria e resultado. |
| Student own courses | `MUST_FOR_PAID_PILOT` | Surface area-first Student que lista somente cursos contratados/matriculados, com cursor pagination. |
| Student consumption | `MUST_FOR_PAID_PILOT` | Abrir curso publicado, módulos, aulas, mídia/material acessível; sem draft, sem conteúdo de outro tenant e sem proxy binário grande. |
| Student progress | `MUST_FOR_PAID_PILOT` | Ler/enviar progresso próprio e receber conclusão coerente com Enrollment. |
| Student attempts/results | `MUST_FOR_PAID_PILOT` quando assessment é vendido | Start, answer, finish e result próprios; nunca retornar `correct_options`, explanation sensível ou tentativa de outro aluno. |
| Certificates | `SHOULD_BEFORE_PAID_PILOT` | A emissão automática básica e verify existem; é MUST do curso somente se o comercial prometer certificado. PDF, revoke e trigger complementar ficam fora do mínimo. |
| Payments | `CAN_OPERATE_MANUALLY` | Pagamento comercial pode ocorrer por invoice/transferência fora da API; Admin confirma `cash/manual` com transição e outbox auditáveis. |
| Gateway automation | `DEFERRED_AFTER_REVENUE` | Webhook/job e adapters PSP first-party continuam fora; checkout existente não é promessa de gateway pronto para produção. |
| Emails/notifications | `CAN_OPERATE_MANUALLY` | Convite e reset transacionais devem funcionar; avisos de onboarding, cobrança, progresso e suporte podem ser enviados manualmente para poucos clientes. |
| Plugins | `DEFERRED_AFTER_REVENUE` | Preset `cash` e contratos existentes sustentam o piloto; marketplace, lifecycle e billing de plugin não entram. |
| Reporting | `CAN_OPERATE_MANUALLY` | Roster, progresso e resultados do Instructor bastam; relatórios avançados podem ser exportados/compilados por operador. |
| Observability | `MUST_FOR_PAID_PILOT` | Logs seguros, health check, erro detectável, correlação mínima e acompanhamento operacional; Pulse/Horizon não são pré-requisito. |

## 5. Student Minimum

Student bloqueia receita com o seguinte contrato, sem abrir uma implementação nesta tarefa:

| Item | MUST para o piloto | Pode esperar |
|---|---|---|
| Login | `/api/v1/auth/login`, tenant resolvido, throttling, token Sanctum, logout/me e reset/invite utilizáveis. | SSO, social login, impersonação e gestão avançada de dispositivos. |
| Meus cursos | Lista Student própria, tenant-scoped, somente matrícula/entitlement do aluno, paginada. | Catálogo Home, recomendação, busca rica, wishlist e marketplace. |
| Abrir curso | Detalhe de curso publicado e permitido, sem expor draft ou dados de outro aluno. | Landing pública/Home e preview comercial completo. |
| Módulos/aulas | Navegação da árvore e detalhe da aula publicados/ativos, com autorização por Enrollment. | Sequenciamento avançado, drip content e offline mode. |
| Mídia/material | Consumir URL/provider metadata ou material configurado; sem leak de storage, segredo ou conteúdo não contratado. | Upload real, CDN/DRM, transcodificação e MediaProvider avançado. |
| Progresso | Ler e gravar progresso próprio, completar aula/curso e preservar o efeito de conclusão. | Analytics avançado, ranking, gamificação e estatística enterprise. |
| Assessment | Quando o curso tiver avaliação: iniciar, responder, finalizar e consultar o próprio resultado; score server-side e snapshot. | Banco avançado, questões abertas, manual grading, randomização e proctoring. |
| Certificado | Só é MUST se o curso for vendido com promessa de certificado; nesse caso, emissão/consulta/verify e side effect devem ser provados. | PDF, assinatura visual, revogação operacional e templates avançados. |
| Matrícula | Deve existir acesso efetivo antes do consumo; no piloto pode ser criada/confirmada por Admin/operator via fluxo manual. | Auto-enrollment por funil público, invite-only e regras de venda complexas. |
| Pagamento | O aluno não precisa pagar dentro da API v0.1; o recebimento externo deve ser reconciliado antes da matrícula/confirm manual. | Checkout automático, webhook, múltiplos PSPs, reembolso e reconciliação automática. |

Regra comercial: não anunciar Assessment ou certificado para um curso até o MUST condicional
correspondente estar evidenciado. Um piloto pode escolher um curso sem quiz/certificado e ainda assim
usar o contrato mínimo de consumo.

## 6. Manual Operations Allowed

Permitido para poucos clientes, desde que rastreável e reversível:

- criar o tenant e o primeiro Admin por operação MZRT assistida;
- configurar nome, domínio, conteúdo inicial, metadata de mídia/material e parâmetros do curso;
- convidar alunos/Instrutors e acompanhar aceite, reset e primeiro login;
- cobrar comercialmente fora da plataforma e registrar/confirmar `cash/manual` somente pela API e
  Action canônicas;
- matricular manualmente alunos gratuitos ou pagos já reconciliados, sem escrever diretamente no DB;
- executar suporte próximo, reenviar convite/reset e corrigir configuração por endpoint autorizado;
- acompanhar logs, health, outbox/worker e realizar backup/restore controlado;
- produzir roster/progresso/resultados sob demanda em vez de dashboard analítico.

Não é operação manual aceitável: editar `tenant_id`, `user_type`, ownership ou status diretamente;
marcar order como paga sem `ConfirmManualPaymentAction`; entregar URL privada sem expiração;
desligar guard/Policy; ignorar outbox; apagar histórico para “limpar” teste; ou compartilhar tokens,
secrets, CPF, email ou payload sensível em logs.

## 7. Security Gate

Nada é promovido a `PAID_PILOT_READY` se falhar um dos controles abaixo:

- **Tenant isolation:** header/host resolve tenant ativo; toda leitura/escrita Student/Admin/Instructor
  é explicitamente tenant-scoped; Tenant B não lê, atualiza, matricula, consome ou observa dados de A.
- **RBAC e área:** cada rota area-first tem exatamente `area.guard` da própria área, `auth:sanctum`,
  `tenant.access` e permission com teto efetivo por `UserType`; Student não alcança Admin/Instructor.
- **Ownership:** Instructor só acessa seus cursos/alunos/resultados; Student só acessa seus
  enrollments, progresso, attempts, orders e certificados.
- **PII least privilege:** roster e Resources retornam somente projeção mínima; CPF/email/password,
  tokens e secrets não aparecem em payloads indevidos nem logs. Novo PII exige `config/lgpd.php` e
  `LogsActivity`.
- **Scope protection:** tenant, owner, parent, user type, status e `is_system` são derivados do
  contexto/URL/transição e proibidos como redefinição pelo payload.
- **Defensive existence:** cross-tenant e ownership negados usam o mesmo envelope defensivo de 404
  quando a existência não deve ser denunciada.

Evidência mínima: Feature com 401/403/404/422, persona correta e persona errada; Architecture
(`AreaRouteGuardTest`, `RouteSecuritySurfaceTest`, `TenantScopingTest`, `TenantIsolationSmokeTest`,
permission/PII/envelope/Scribe); E2E HTTP com dois tenants e papéis cruzados; inspeção de side effects
no banco. Histórico de testes não executado contra o app atual não é suficiente.

## 8. Performance Gate

O piloto é dimensionado para poucos tenants e centenas de alunos ativos por tenant, não para uma
promessa de SLA empresarial. O gate é de comportamento sustentável e de ausência de regressões
óbvias:

- toda listagem comercial usa `cursorPaginate`, filtros/sorts allowlisted e eager loading adequado;
- listagens de cursos, meus cursos, roster, progresso, attempts/results e orders não têm N+1 material;
- Course → Module → Lesson e Student consumption não podem emitir uma query por item de uma lista;
- mídia/arquivo é entregue por URL direta/temporária/provider, sem proxy binário grande pela API;
- progresso pessoal fica no banco; cache só para catálogo frio quando necessário;
- async/queue é usado onde há side effect durável ou integração externa: outbox, webhook, estatística;
  não se adiciona fila só por prescrição;
- endpoints críticos não fazem full scan ou query cujo custo cresça linearmente com itens retornados;
  qualquer query anômala deve ser corrigida antes do primeiro cliente.

Smoke obrigatório no fixture de piloto: login → meus cursos → abrir curso → módulos → aula/mídia →
progresso → Assessment (se aplicável) → resultado/certificado → Instructor roster/progresso. O
smoke deve registrar status, envelope, tempo observado, contagem de queries nas listagens críticas,
side effects e cleanup. Não há número de p95/SLA inventado; há limite zero para N+1 conhecido,
vazamento e erro funcional.

## 9. Architecture Gate

O fechamento exige:

- manter os cinco módulos e `app/Shared`; Student novo nasce na área/módulo dono do recurso;
- preservar `Route → Controller fino → Action → Model → Resource`, sem query ou regra de negócio no
  controller;
- usar `ApiContext`, `Gate::forUser(...)->authorize(...)`, FormRequest e envelope central;
- manter tenant scoping explícito nas Actions/Services conforme ADR-004;
- cruzar Learning, Assessment e Financial apenas por Events/Contracts já definidos; não importar
  internals Eloquent novos entre módulos;
- manter dinheiro em cents e o ledger de venda separado do ledger futuro da plataforma;
- não resolver Student adicionando permissões a uma rota legacy sem a superfície `area.guard:student`;
- registrar ADR/spec somente se uma decisão durável de contrato for realmente necessária; não abrir
  WS2/WS3 nem transformar capability avançada em core.

O gate é reprovado por controller gordo, bypass de Policy/tenant/ownership, rota legacy usada como
produto novo ou coupling cross-module não justificado.

## 10. Evidence Gate

Cada capability comercial MUST precisa de uma matriz de evidência, com o nível aplicável:

1. **Feature:** endpoint HTTP + banco, happy path, validação, auth, permission, ownership e
   isolamento.
2. **Architecture:** área/guard, security surface, tenant scoping/isolation, RBAC, envelope,
   controller leanness, money e boundary.
3. **E2E HTTP real:** servidor e banco dedicados, HTTP externo, papéis/tenants cruzados e jornada
   relevante.
4. **Runtime evidence:** comando, data, commit, container/app, banco, migration state, resultado e
   artefato atual. O resultado precisa ser repetível no código que será promovido.
5. **Side effects:** matrícula, order/payment/outbox, progress, attempt/result, certificate e
   auditoria conferidos diretamente no banco quando aplicável.
6. **Cleanup:** fixtures, usuários, tenants, orders, outbox, arquivos e jobs removidos ou
   contabilizados; cleanup que deixa órfãos invalida a prova.
7. **Provenance:** receipt aponta spec, teste, commit, ambiente e comando; relatório histórico é
   referência, não prova atual automática.
8. **Scribe:** geração atual sem rota/annotation/middleware divergente e sem campos proibidos.

`STATIC_EVIDENCE_ONLY` ou `DOCUMENTATION_ONLY` não pode ser promovido a production-ready. Para
Student e o fluxo pago, o mínimo é Feature + Architecture + E2E HTTP + runtime + side effects +
cleanup + provenance + Scribe.

## 11. False-Success Gate

Antes do milestone, uma auditoria adversarial consolidada é obrigatória. Ela deve ignorar verdicts
anteriores até reproduzir a prova e procurar:

- testes skipped, `todo`, mocks que substituem o comportamento sob prova e fixtures que não usam a
  rota canônica real;
- assertions que verificam apenas status/shape, sem banco, outbox, matrícula, progresso, score,
  certificate ou cleanup;
- cleanup falso que deixa usuários, tenants, orders, files ou jobs órfãos;
- E2E rodado contra app/banco diferentes, banco compartilhado, `--force-db`, cache velho ou
  migration incompleta;
- claims `RUNTIME_VERIFIED` baseados somente em código, teste histórico, relatório ou Scribe;
- cross-tenant, persona errada, área/guard ausente, 404 que denuncia existência, payload de escopo
  aceito e PII excessiva;
- Student funcionando somente por rota legacy sem `area.guard:student`;
- Assessment com gabarito exposto, score aceito do cliente, attempt de outro aluno ou certificado
  sem side effect idempotente.

O gate exige repetir a jornada comercial inteira por HTTP, com pelo menos dois tenants, sucesso,
negativas e teardown, e consolidar um receipt único. Uma capability sem essa reprodução permanece
`UNVERIFIED` ou `STATIC_EVIDENCE_ONLY`.

## 12. Operations Minimum

| Operação | Classificação | Mínimo aceitável no piloto |
|---|---|---|
| Deploy | `MUST` | Procedimento repetível, versão identificável, migrations separadas de release e smoke pós-deploy. |
| Environment/config | `MUST` | `.env`/config fora do código, tenant/app URL/DB/storage/queue explícitos, sem `env()` em runtime de domínio. |
| DB migrations | `MUST` | Executadas de forma controlada, estado conhecido, backup antes de mudança destrutiva e smoke pós-migration. |
| Backup/restore | `MUST` | Backup automatizado ou operador-agendado com teste de restore documentado antes do primeiro cliente. |
| Logs | `MUST` | Logs acessíveis e retidos pelo período operacional do piloto, sem senha/token/PII/secret em claro. |
| Error monitoring | `MUST` | Canal operacional para detectar 5xx, falhas de job/outbox e erro de integração; stack paga é oportunidade, não requisito. |
| Health check | `MUST` | Probe de app, DB e dependências necessárias, com resposta utilizável pelo operador. |
| Rollback | `MUST` | Procedimento para voltar aplicação/configuração e recuperar migration/release sem apagar histórico. |
| Secrets | `MUST` | Gestão fora do repositório, rotação possível, gateway config cifrada/write-only/redacted. |
| Queue/worker | `MUST condicional` | Se outbox/webhook/estatística estiver habilitado, worker/drainer observado e recuperável; cash assistido pode usar drainer operacional controlado. |
| Storage | `MUST condicional` | Necessário se o curso oferecer arquivo/mídia própria; pode usar provider externo configurado manualmente, sem upload avançado. |

Não é blocker operacional instalar Pulse, Horizon, APM, RabbitMQ, MariaDB stats ou novo pacote. Se
a operação exceder poucos clientes, isso vira decisão de capacidade antes de ampliar a venda.

## 13. Deferred Capabilities

Explicitamente não prometidas na v0.1:

- advanced quiz, questões abertas, manual grading, randomização e proctoring;
- MediaProvider avançado, upload real como produto, transcodificação, DRM, CDN e live;
- marketplace, plugin marketplace, lifecycle completo de plugins e billing MZRT→tenant;
- advanced analytics, dashboards enterprise, quotas e ranking/gamification;
- multiple gateways, Stripe/Mercado Pago/PagSeguro/PIX/Asaas, webhook automático, refund e
  reconciliação automática;
- automações externas pagas de email/marketing/CRM e notificações de jornada em escala;
- certificados PDF, templates avançados, assinatura visual e revogação operacional;
- subscriptions, cart, coupons, affiliates e commission de Instructor;
- Home/vitrine pública completa, SEO/i18n e funil público;
- SSO, social login, impersonação, offline learning e DRM.

## 14. Release Blockers

Somente MUSTs bloqueiam `PAID_PILOT_READY`:

1. **Student canonical surface:** fechar `/api/v1/student/*` para meus cursos, abrir curso,
   módulos/aulas, consumo de mídia/material e progresso, com stack/guard exatos e Resources.
2. **Student security proof:** provar tenant isolation, own scope, 401/403/404/422, RBAC, PII
   least privilege e proibição de spoofing de tenant/ownership.
3. **Student Assessment basic, quando o curso o oferecer:** start → answer → finish → result,
   snapshot/score server-side, sem gabarito e com tentativa própria.
4. **Progress and promised completion effects:** confirmar por HTTP e banco persistência, conclusão,
   outbox/events e emissão de certificado apenas onde for promessa comercial.
5. **Integrated commercial E2E:** tenant → Admin/content publish → enrollment/cash/manual → Student
   login/consumption/progress → Instructor roster/results, com dois tenants e cleanup zero.
6. **Current evidence receipt:** Feature + Architecture + E2E HTTP + runtime + side effects +
   cleanup + provenance + Scribe, no código/app/banco atual; static-only não fecha.
7. **False-success audit:** auditoria adversarial reproduzida e consolidada, sem skips/mocks/asserts
   fracas/cleanup falso/claims históricos indevidos.
8. **Operations readiness:** deploy, migrations, backup/restore testado, logs, error monitoring,
   health, rollback, secrets e worker/drainer quando o fluxo o exigir.

Não são blockers: gateway automático, webhook, múltiplos PSPs, marketplace, analytics avançado,
upload/MediaProvider avançado, Home, PDF de certificado, roles customizadas, WS2 ou WS3.

## 15. Timeline

Estimativa a partir de **2026-09-08**, assumindo um responsável focado, ambiente E2E disponível e
nenhuma decisão humana nova que amplie o escopo. A estimativa considera integração, debugging,
reexecução, auditoria adversarial e operação; não é contagem de arquivos/tasks.

| Cenário | Data-alvo | Hipótese |
|---|---:|---|
| Best case | **2026-09-22** | Student mínimo converge sem regressões grandes; Assessment é usado só no core simples; runtime/E2E e operações passam na primeira rodada. |
| Realistic | **2026-10-06** | Há correções de surface/guard/Resource, ajustes de side effect/cleanup, uma rodada adicional de false-success e hardening de deploy/backup/observability. |
| Conservative | **2026-10-27** | Ambiente, fila/storage, contrato Student e evidência exigem remediações repetidas; alguma capability condicional de Assessment/certificado precisa ser reduzida ou provada separadamente. |

As datas não significam código iniciado nesta tarefa e não autorizam abrir Student, WS2 ou WS3.
São uma janela de release condicionada ao fechamento dos blockers acima.

## 16. Recommended Next Work

1. Ratificar este contrato e a regra comercial: cobrança externa + matrícula/cash assistida são
   permitidas; gateway automático não será vendido como v0.1.
2. Preparar, em tarefa explicitamente autorizada, o recorte Student mínimo: primeiro consumo/own
   courses/progresso; depois Assessment básico se houver curso que o comercialize; manter cada
   slice com Feature, Architecture, Scribe e E2E HTTP.
3. Ao final, executar uma única jornada comercial em banco E2E descartável, fazer a auditoria de
   falso sucesso e fechar o pacote operacional antes de qualquer primeiro cliente pago.

Não iniciar nesta tarefa implementação, packages, WS2, WS3 ou capabilities avançadas.

## 17. Final Verdict

**`PAID_PILOT_NOT_READY`**.

O projeto está perto no control plane e no authoring, e pode operar manualmente o recebimento e a
matrícula de poucos clientes. Ainda não é seguro prometer a v0.1 comercial porque o Student mínimo
e a prova integrada da jornada não estão fechados. O próximo marco deve ser `PAID_PILOT_READY_WITH_MANUAL_OPERATIONS`
somente depois dos blockers MUST, da auditoria adversarial e do gate operacional passarem com
evidência atual.
