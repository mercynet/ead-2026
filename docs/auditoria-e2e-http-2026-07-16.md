# Auditoria E2E HTTP — 2026-07-16

> **HISTÓRICO (2026-09-05):** snapshot de 2026-07-16, com commit, contagens e ambiente daquele
> momento. Serve como evidência histórica e contexto de segurança; não prova o runtime atual nem
> substitui a auditoria de estado de 2026-09-05 ou uma nova execução isolada.

## Veredito

**Validação parcial, com NO-GO para execução HTTP mutante no ambiente atual.**

A estratégia segura proposta é adequada, mas o runner e o ambiente disponível não cumprem todos os
gates obrigatórios. Nenhuma conclusão nova de segurança, workflow ou performance foi inferida de uma
execução que não pôde ser feita com isolamento suficiente.

## Escopo validado

- API REST `/api/v1` em Laravel Sail local.
- Revisão do runner HTTP, 10 specs declarativas, rotas, testes Architecture/Feature/E2E e contratos.
- Conferência source-to-sink de autenticação, troca/reset de senha, rate limit, tenant e erros.
- Verificação read-only de resíduos sintéticos no banco local.
- Reclassificação dos findings históricos E2E-001 a E2E-003.

## Snapshot

- Data: 2026-07-16.
- Commit base: `a99026b`.
- Working tree: alterações não consolidadas de password reset e outros arquivos; este relatório também
  está no working tree.
- Rotas `/api/v1` observadas: **65**.
- Specs HTTP declarativas: **10**, todas em Learning/Admin.
- Casos presentes nas specs atuais: **55**, não 57.
- Serviços Sail: aplicação, MySQL, Redis e Mailpit locais e saudáveis.
- Runtime observado: `APP_ENV=local`, `APP_DEBUG=true`.
- Configuração local observada: cache e filas em banco, email SMTP local, storage local.

## Validação da estratégia segura

| Controle | Estado | Evidência |
|---|---|---|
| Sail local | confirmado | containers locais ativos; nenhuma URL externa usada |
| Banco exclusivo para HTTP E2E | **não atendido** | servidor usa banco local da aplicação; banco `testing` estava sob execução concorrente |
| Redis, filas, storage e email locais/fakes | parcial | Redis/Mailpit/storage são locais; cache e fila usam banco, não isolamento dedicado |
| Dados sintéticos identificáveis | confirmado no código | `E2eRunCommand.php:138-165` usa prefixos `e2e-*`, emails `@test.local` e tokens efêmeros |
| Cleanup obrigatório | parcial | `finally` executa cleanup, mas falhas são apenas warnings e `--keep` permite resíduos (`E2eRunCommand.php:70-82,318-330`) |
| Ausência de resíduos atuais | confirmado | consultas read-only retornaram 0 tenants e 0 users com prefixo E2E |
| Sem técnicas agressivas | confirmado nesta validação | nenhum brute force, enumeração massiva, payload gigante, fault injection ou carga executado |
| Timeout curto | **não atendido** | cliente HTTP não define timeout (`E2eRunCommand.php:213-217`) |
| Abortagem no primeiro 5xx | **não atendido** | 5xx é registrado, mas o loop continua (`E2eRunCommand.php:64-66,231-234`) |
| Máximo fixo de requests | **não atendido** | runner executa todos os casos sem orçamento global |
| Verificação forte de ambiente | **não atendido** | bloqueia apenas `production`; não exige DB/host/run-id exclusivos (`E2eRunCommand.php:41-45`) |
| Relatório sanitizado | parcial | este documento não contém segredos; runner pode imprimir corpo 5xx e mensagens de exceção sem sanitização (`E2eRunCommand.php:68,219,233,291-315`) |
| Concorrência pequena | não exercitada | nenhuma carga ou concorrência HTTP executada |

### Decisão do gate

Execução HTTP mutante no servidor atual foi bloqueada porque não havia banco E2E exclusivo e havia
processos concorrentes usando `testing`. Rodar as 55 chamadas nessas condições violaria a própria
estratégia aprovada.

## Validações executadas

### Inventário e revisão estática

- `graphify query` localizou runner, autenticação, tenant, rate limit, error envelope e lifecycle de
  tokens.
- `route:list` retornou 65 rotas `/api/v1`.
- As 10 specs atuais somam 55 casos: 7 + 7 + 7 + 5 + 4 + 4 + 10 + 4 + 4 + 3.
- Revisão source-to-sink não confirmou bypass de tenant, auth ou autorização nos três findings
  históricos.

### Architecture e Feature

Resultado **não confirmado**. A primeira execução retornou 9 testes verdes e 7 falhas de infraestrutura
durante migrations. Tentativas posteriores também sofreram concorrência no mesmo banco `testing`.
Foi identificado processo externo de testes Core/E2E usando esse banco; novas tentativas foram
interrompidas para não continuar corrompendo a evidência.

Essas falhas não demonstram defeito do produto e não sustentam a afirmação anterior de “16 testes,
66 assertions, todos verdes”. Esse número foi removido até nova execução isolada.

### HTTP E2E

Nenhuma nova execução HTTP mutante foi aceita nesta rodada. Os resultados históricos de 53/57 não
representam as specs atuais e não são reutilizados como prova presente.

### Cleanup

Consultas sanitizadas após a inspeção:

- tenants com domínio `e2e-*`: **0**;
- users com email sintético `e2e-*@test.local`: **0**.

## Findings

### AUD-001 — Runner não implementa limites automáticos obrigatórios

- Classificação: tooling/safety.
- Severidade: média para confiabilidade da auditoria; não é vulnerabilidade da API.
- Exploitabilidade: não aplicável.
- Evidência: `app/Console/Commands/E2eRunCommand.php:41-88,193-249,284-330`.
- Source→sink: spec local controla casos e headers → runner envia requests sem timeout/orçamento →
  continua após 5xx → pode prolongar execução e imprimir resposta sensível.
- Mitigação mínima: exigir allowlist de ambiente/host/DB, timeout por request, orçamento global,
  circuit breaker no primeiro 5xx inesperado, cleanup fatal e sanitização central da saída.

### AUD-002 — Cobertura HTTP externa permanece limitada

- Classificação: cobertura.
- Severidade: informativa.
- Evidência: 10 specs/55 casos para 65 rotas.
- Coberto: criação/publicação de conteúdo e partes do consumo/progresso Learning.
- Não coberto por spec HTTP declarativa: Core, Assessment e grande parte de Learning, incluindo
  categorias, enrollments, mídia, materiais, ratings e várias mutações de curso/módulo/aula.
- Mitigação mínima: ampliar por workflow crítico somente após stack E2E exclusiva e runner endurecido.

### SEC-001 — Login, forgot e reset podem compartilhar bucket por IP

- Classificação: segurança/hardening.
- Severidade: baixa.
- Exploitabilidade: confirmada no código; impacto em runtime depende de proxy/NAT.
- Evidência: `app/Modules/Core/Routes/api.php:14-16` e chave padrão do middleware de throttle por
  domínio+IP.
- Source→sink: requests anônimos distintos → mesmo bucket posicional `throttle:5,1` → middleware
  retorna 429 antes da autenticação/recuperação.
- Impacto: cinco chamadas em uma rota podem bloquear temporariamente outras para o mesmo IP.
- Mitigação mínima: limiters nomeados separados para login, forgot e reset; validar IP confiável do
  proxy antes de definir a chave.

### SEC-002 — Novo pedido de recuperação invalida token pendente anterior

- Classificação: disponibilidade do fluxo de recuperação.
- Severidade: baixa.
- Exploitabilidade: provável; implementação está no working tree, ainda não consolidada.
- Evidência: `app/Modules/Core/Actions/Auth/RequestPasswordResetAction.php:30-45`.
- Source→sink: request público para email conhecido → tokens pendentes marcados como usados → novo
  token/notificação emitido.
- Impacto: repetição controlada pode interromper link legítimo anterior; não prova tomada de conta.
- Mitigação mínima: definir cooldown e semântica de rotação por tenant+email, mantendo limite por IP.

## Reclassificação dos findings anteriores

### E2E-001 — Base URL padrão via Sail

- Estado atual: **parcialmente confirmado e condicional**.
- Runner usa `config('app.url')` ou `--base`; a ajuda já documenta `--base=http://localhost` dentro do
  container (`E2eRunCommand.php:26-28,52`).
- Classificação correta: risco de tooling quando `APP_URL` aponta para porta publicada no host, não
  incompatibilidade universal.

### E2E-002 — Três falsos positivos nas specs

- Estado atual: **resolvido em `a99026b`**.
- Specs atuais esperam curso `draft` e associam instructor às fixtures de módulo/aula.
- O finding descrevia estado histórico; não deve permanecer como defeito atual.

### E2E-003 — Ausência de matrícula no heartbeat

- Estado atual: **resolvido/sem divergência atual**.
- Contrato e spec atuais esperam 404; implementação usa busca tenant+user+course com `firstOrFail()`.
- Nenhum bypass de segurança confirmado.

## Correções sobre findings de autenticação

### Stack trace com debug ligado

- Classificação atual: **não verificável como vulnerabilidade; severidade informativa**.
- O runtime local observado estava com `APP_DEBUG=true`.
- O relatório anterior não prova exposição em configuração semelhante à produção.
- Handlers explícitos cobrem 401/403/404/422; não há teste atual provando resposta 500 genérica sem
  trace quando `APP_DEBUG=false`.
- Reteste HTTP com `APP_DEBUG=false` não foi executado porque faltou stack E2E dedicada. Até esse gate,
  não classificar como vulnerabilidade confirmada.
- Stack traces observadas no log local de teste não equivalem a exposição no corpo HTTP.

### Tokens após troca/reset de senha

- `PATCH /api/v1/core/users/me/password`: implementação troca a senha e mantém tokens existentes.
  A spec canônica não define revogação; portanto isso é **decisão de contrato pendente**, não bug
  automático.
- `POST /api/v1/core/auth/password/reset`: implementação atual no working tree apaga todos os tokens e
  testes novos expressam essa semântica. Como a suite não foi validada isoladamente, registrar como
  contrato-alvo do working tree, não como comportamento verde confirmado.
- A spec canônica deve declarar explicitamente a política dos dois fluxos antes de qualquer finding.

## Rate limit

- Validação principal deve permanecer em Feature tests determinísticos.
- Não existe hoje teste focado em 429 para essas rotas.
- Nenhuma tentativa de contornar limite por IP ou headers foi realizada.
- HTTP real deve usar poucas chamadas controladas somente em stack exclusiva, após separar os buckets.

## Performance

Não avaliada. Sem banco/dataset exclusivo, qualquer número seria contaminado e enganoso. Quando
liberada, usar dataset sintético, warm-up explícito, baixa concorrência (máximo 2–4), orçamento fixo e
registrar n/p50/p95/máximo/erros sem alegar SLA ou capacidade.

## Gate para próxima execução

1. Stack Sail exclusiva para auditoria, com DB novo e identificável; cache array/Redis exclusivo,
   fila sync, mail array/Mailpit e storage local dedicado.
2. `APP_ENV=e2e`, `APP_DEBUG=false` e URL local allowlisted no servidor e runner.
3. Nenhum processo Pest/E2E concorrente; hash/diff do código fixados durante a rodada.
4. Runner com timeout, máximo de requests, circuit breaker em primeiro 5xx, sanitização e cleanup fatal.
5. Canário prova servidor↔DB correto antes de qualquer mutação.
6. Ao final, prova de zero fixtures, tokens e arquivos com prefixo do run-id.

## Conclusão

A estratégia proposta foi validada como direção segura. Ambiente e runner atuais não satisfazem seus
limites automáticos nem isolamento, então a decisão correta foi não executar nova bateria HTTP. Findings
históricos E2E-002/E2E-003 estão resolvidos; stack trace e permanência de tokens após troca de senha não
são vulnerabilidades confirmadas sem, respectivamente, reteste com debug desligado e contrato explícito.
