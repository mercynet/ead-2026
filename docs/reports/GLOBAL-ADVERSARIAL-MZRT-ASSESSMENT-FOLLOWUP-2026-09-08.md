# Global Adversarial MZRT / Assessment Follow-up — 2026-09-08

## 1. Server/Runner Runtime Matrix

| Propriedade | Runner | HTTP Server canário | Equal? |
|---|---|---|---|
| `APP_ENV` | `e2e` (override explícito) | `e2e` (override explícito) | Sim |
| `APP_URL` / base | `http://localhost:8086` | `APP_URL=http://localhost`, escutando `0.0.0.0:8086` | Sim, para o canário |
| `DB_CONNECTION` | `mysql` | `mysql` | Sim |
| `DB_HOST` | `mysql` | `mysql` | Sim |
| `DB_DATABASE` | `ead2026_e2e` | `ead2026_e2e` | Sim |
| container | `ead2026-e2e-laravel.test-1` | `ead2026-e2e-laravel.test-1` | Sim |
| processo | `php artisan e2e:run` | `php -d variables_order=EGPCS artisan serve --no-reload --host=0.0.0.0 --port=8086` | Sim, mesma app/container |
| rede | `ead2026-e2e_sail` | `ead2026-e2e_sail` | Sim |
| migrations | 73/73 após `migrate:fresh` | mesma conexão e mesmo estado | Sim |
| tenant/token/contexto | fixtures criadas pelo runner | login/canário HTTP leu essas fixtures | Sim |

O servidor normal em `ead2026-laravel.test-1:8099` é outro runtime (`APP_ENV=local`, banco
`ead2026`) e não foi usado como servidor do receipt MZRT. No estado inicial, o runner dentro do
container E2E também caía nos defaults locais (`ead2026`), e `http://localhost` dentro desse
container atingia o servidor local na porta 80, não o processo E2E.

## 2. Canary Root Cause

Havia três desalinhamentos observáveis: container/rede E2E distintos do runtime canônico, base
`localhost` apontando para a porta 80 local em vez do listener E2E, e overrides de ambiente ausentes
no processo filho do servidor. O ponto causal do 500 foi `php artisan serve` sem `--no-reload`: o
`ServeCommand` repassa ao worker somente variáveis de ambiente permitidas e o worker não recebeu
uma `APP_KEY` funcional. O provisioning então falhou ao persistir o cast criptografado de
`TenantPluginConfig`, em `EcosystemDefaultGatewayProvisioner`.

O canário foi corrigido operacionalmente usando o mesmo container, banco, overrides completos,
`APP_KEY` efêmera de auditoria e `--no-reload`, com base explícita `http://localhost:8086`. Nenhuma
chave ou token foi registrado neste relatório.

## 3. M-001 Verification

O primeiro hardening de M-001 ainda tinha um defeito: `components->task()` retorna `void`; atribuir
seu retorno fazia um `migrate:fresh` bem-sucedido parecer falso. O teste regressivo ficou RED com
exit esperado 0 e recebido 1. A correção passou a capturar o resultado do callback por referência,
passar `--force` e `--no-interaction`, e abortar antes de fixtures quando o resultado é falso.

Negative-canary final:

```text
DB_DATABASE=ead2026_e2e_negative_fresh_failure
e2e:run mzrt/tenant-lifecycle --base=http://localhost:8086 --fresh
RUNNER_EXIT=1
```

O erro foi o acesso negado ao banco inexistente. A mensagem de abort foi emitida antes de
`bootFixtures`; o banco válido permaneceu com `fixture_tenants=0`, `fixture_users=0` e
`tokens=0`. Não houve casos HTTP executados.

## 4. Fresh Install

No banco isolado `ead2026_e2e`, a execução integrada final foi:

```text
e2e:run mzrt/tenant-lifecycle --base=http://localhost:8086 --fresh
```

`migrate:fresh` terminou com `DONE`; a verificação posterior encontrou 73 migration rows, 73
arquivos de migration e 0 pendências. O runner executou o bootstrap mínimo, o canário de
alinhamento, os casos HTTP e o cleanup. O estado pós-cleanup não reteve tenants, usuários ou
tokens de fixture.

## 5. MZRT Provisioning

Os 10 casos do spec `tests/e2e-http/mzrt/tenant-lifecycle.php` passaram: autenticação Developer
canônica, criação HTTP de tenant e entitlement `cash`, projeção sem configuração sensível, login
do Admin criado, suspend/negação de login, negação de contexto suspenso, reativação, novo login e
reuso do token original.

**Verdict: `MZRT_FRESH_INSTALL_CONFIRMED`.**

## 6. Legacy Assessment Inventory

Todas as rotas abaixo são legacy domain-first em `/api/v1/assessment` e não possuem
`area.guard:student`. O novo middleware é executado depois de autenticação e acesso ao tenant nas
rotas privadas.

| Rotas | Middleware efetivo | Permission | Validações de domínio / projeção | Classificação atual |
|---|---|---|---|---|
| `GET/POST /questionnaires` e `GET/PATCH/DELETE /questionnaires/{id}` | tenant required, `auth:sanctum`, `tenant.access`, bloqueio Student | `list/create/view/update/delete`; Student não está na matriz | CRUD de questionário; não é fluxo de matrícula/attempt; Resource | `NOT_STUDENT_ACCESSIBLE` |
| `GET/POST /questions` e `GET/PATCH /questions/{id}` | tenant required, `auth:sanctum`, `tenant.access`, bloqueio Student | `list/create/view/update`; Student não está na matriz | authoring; não é fluxo de matrícula/attempt; Resource | `NOT_STUDENT_ACCESSIBLE` |
| `POST /attempts/questionnaires/{questionnaireId}` | tenant required, `auth:sanctum`, `tenant.access`, bloqueio Student | `assessment.attempts.create` inclui Student | Antes do bloqueio: tenant, questionário ativo e perguntas ativas; **não** Enrollment, parent Course/Lesson ou acesso do aluno ao parent | `SAFE_LEGACY_COMPAT` após guard; `STUDENT_BYPASS` antes |
| `GET /attempts/{id}` | mesma stack | `assessment.attempts.view` inclui Student | Antes do bloqueio: `where(user_id)` próprio; query não explicita tenant; Resource não expõe gabarito | `SAFE_LEGACY_COMPAT` após guard; `STUDENT_BYPASS` antes |
| `PATCH /attempts/{id}` | mesma stack | `assessment.attempts.answer` inclui Student | Antes do bloqueio: `where(user_id)`, status, question snapshot; não Enrollment/parent; score usa snapshot servidor | `SAFE_LEGACY_COMPAT` após guard; `STUDENT_BYPASS` antes |
| `POST /attempts/{id}/finish` | mesma stack | `assessment.attempts.finish` inclui Student | Antes do bloqueio: `where(user_id)`, status e score do snapshot; não Enrollment/parent | `SAFE_LEGACY_COMPAT` após guard; `STUDENT_BYPASS` antes |
| `GET /certificates` e `GET /certificates/{id}` | tenant required, `auth:sanctum`, `tenant.access`, bloqueio Student | `list/view` inclui Student | Actions filtram `user_id` próprio; não são capability Student canônica e certificates continuam fora da v0.1 | `SAFE_LEGACY_COMPAT` após guard; acesso Student legado antes |
| `GET /certificates/verify/{certificateNumber}` | endpoint público, `@unauthenticated` | nenhuma | Verificação pública, fora de uma operação autenticada Student | `NOT_STUDENT_ACCESSIBLE` como superfície Student |

O ponto adversarial foi reproduzido em RED no teste de fronteira: antes do guard, o start retornava
201 e as outras operações não tinham uma negativa segura; após o guard, as seis rotas privadas
retornam envelope `403/access_denied` e não criam `QuizAttempt`.

## 7. H-001 Decision

Foi escolhida a opção **A**: Student é bloqueado na superfície legacy Assessment privada,
preservando Developer/Admin/Instructor e sem promover essas rotas a canonical. A implementação é
`BlockLegacyStudentAssessment`, aplicado aos grupos privados de questionnaires, questions, attempts
e certificates.

O bypass histórico estava confirmado: um Student com permission podia iniciar, responder, finalizar
e consultar attempt em `/api/v1/assessment`, fora de `/api/v1/student`, sem Enrollment nem validação
do parent Course/Lesson. O guard remove a exposição atual, mas não transforma o legacy em contrato
Student.

**Verdict atual: `LEGACY_ASSESSMENT_SAFE` (após guard mínimo).**

## 8. H-002 Decision

O estado atual não oferece endpoint Student Assessment acessível para provar uma closure RBAC
completa. O caminho histórico era inseguro para esse objetivo: permission nominal existia, não havia
guard de área Student, e a Action de start não validava Enrollment nem parent acessível; own-attempt
sozinho não fecha o contrato.

**Verdict: `STUDENT_ASSESSMENT_RBAC_NOT_PROVEN`.**

Isso é deliberado: bloquear a superfície impede o bypass, mas não reivindica que uma futura
capability Student Assessment já tenha auth + tenant + área + Enrollment + parent + ownership +
cross-tenant closure.

## 9. Commercial Release Constraint

`Student Assessment = CONDITIONAL_CAPABILITY / NOT RELEASED` permanece explícito. Não houve nova
superfície Student, mudança de `show_results`, certificate implementation ou ampliação de RBAC.

Não existe rota/annotation `/api/v1/student/*` de Assessment. Porém, as annotations genéricas de
Scribe ainda descrevem os endpoints legacy de attempts/certificates; isso não é uma promessa de
área Student, mas deve ser tratado como documentação legacy condicionada antes de publicar um
contrato consumer-facing. `composer docs` não foi executado nesta auditoria.

O primeiro Course piloto pode operar sem Assessment e sem certificate. Há risco de configuração:
`StoreCourseRequest` e `UpdateCourseRequest` ainda aceitam `certificate_enabled`,
`certificate_requires_quiz`, `certificate_min_progress` e `certificate_min_score`. Assim, um
operador pode configurar um Course para exigir uma capacidade que Student não pode exercer. O
curso com essa configuração não pode ser comercializado até existir closure própria.

## 10. Findings

### HIGH

- **H-001 histórico — bypass legacy Student Assessment:** confirmado antes do guard; mitigado para
  Student pela decisão A. Não é closure canônica.
- **H-002 — configuração de Course sem gate de capability:** quiz/certificate podem ser exigidos
  enquanto Student Assessment/certificates estão fora da release. É blocker comercial para esse
  tipo de Course, mas não para o primeiro piloto sem essa promessa.
- **Documentation drift — legacy Assessment documentado genericamente:** Scribe não promete uma
  área `/student`, mas as annotations continuam apresentando o legacy como API pedagógica. A
  publicação deve manter a condição de compatibilidade ou remover a promessa antes da release.

### BLOCKER

Nenhum blocker MZRT permanece após a prova final. Para qualquer oferta que exija quiz/certificate,
H-002 é condição bloqueadora de release até existir capability Student fechada.

## 11. Verdict

| Área | Verdict |
|---|---|
| MZRT fresh install | `MZRT_FRESH_INSTALL_CONFIRMED` |
| H-001 | `LEGACY_ASSESSMENT_SAFE` após guard; bypass histórico confirmado |
| H-002 | `STUDENT_ASSESSMENT_RBAC_NOT_PROVEN` |
| Student Assessment | `CONDITIONAL_CAPABILITY / NOT RELEASED` |

### Provenance

Execução feita no working tree dirty existente, sem commit ou push. O receipt MZRT usou os
containers `ead2026-e2e-laravel.test-1` e `ead2026-e2e-mysql-1`, DB `ead2026_e2e`, PHP 8.4/Laravel
12, e o processo HTTP explícito na porta 8086. O servidor canônico em 8099 não foi confundido com
o alvo E2E. Nenhum segredo, token ou valor de `APP_KEY` foi incluído.
