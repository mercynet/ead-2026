# Commercial v0.1 — Operations Readiness Audit — 2026-09-08

## 1. Executive Verdict

**Operations verdict: `OPERATIONS_NOT_READY`**
**Paid Pilot verdict: `PAID_PILOT_NOT_READY`**

Resposta à pergunta central: **não**. O código tem uma base local executável, migrations ordenadas,
health liveness, provisioning assistido e fluxo manual de cobrança, mas não há evidência operacional
suficiente para implantar, observar, recuperar e reverter um tenant real com segurança razoável.

O bloqueio não é uma expansão do produto funcional. O estado funcional informado foi preservado como
premissa desta auditoria: MZRT, Admin, Instructor e Student commercial permanecem no estado declarado;
Student Assessment continua condicional e certificados não são prometidos em v0.1.

Escopo e evidência:

- inspeção estática do repositório, `AGENTS.md`, specs e relatórios históricos;
- working tree, HEAD, tags, arquivos de infraestrutura e lockfiles;
- containers Docker atualmente ativos e comandos somente leitura (`about`, `migrate:status`,
  `schedule:list`, `/up` dentro do container);
- nenhuma migration, teste, deploy, instalação, backup, restore ou operação destrutiva foi executada.

Evidência runtime local não é evidência de produção. O container da aplicação reportou Laravel 12.63.0,
PHP 8.4.18, `APP_ENV=local` e debug habilitado. O container nomeado para E2E também reportou
`APP_ENV=local` e debug habilitado; portanto seu nome não prova isolamento E2E.

## 2. Environment Inventory

| Área | Estado observado | Classificação |
|---|---|---|
| Dev/local | `compose.yaml` fornece `laravel.test`, MySQL 8.4, Redis e Mailpit; aplicação montada por bind em `/var/www/html`; servidor atual é `php artisan serve` | `CONFIRMED_DEV_ONLY` |
| E2E | Existe `.env.e2e.example`, banco `ead2026_e2e` em container separado e runner com canário/cleanup; runtime observado no container E2E ainda reportou `local` + debug habilitado | `PARTIAL / NOT_PRODUCTION` |
| Produção | Não há compose/Dockerfile/reverse proxy/runbook de produção, unidade de worker/scheduler, backup job, restore automation ou monitoramento provisionado no repositório | `MISSING` |
| Web server | Dev usa servidor PHP embutido do Sail. Não há configuração versionada de Nginx/Apache/Caddy para o produto | `MISSING_FOR_PRODUCTION` |
| PHP | PHP 8.4.18 observado no container; `composer.lock` existe | `PARTIAL` |
| Banco | MySQL 8.4 no Compose; configuração também admite MariaDB. `migrate:status` local mostrou 73 migrations executadas | `PARTIAL` |
| Redis | Serviço local existe e tem healthcheck; defaults de cache/fila do projeto são database, não há prova de Redis de produção | `PARTIAL` |
| Queue | Tabela `jobs`/`failed_jobs` e conexão database existem; não há worker produtivo versionado ou observado | `MISSING_FOR_PRODUCTION` |
| Storage | Defaults apontam para disco local; não há storage remoto/persistente e backup de arquivos comprovados | `MISSING` |
| Scheduler | Existe uma tarefa Laravel a cada minuto para o outbox; não há cron/systemd/scheduler process documentado | `PARTIAL` |
| Mail | Mailpit é local; template usa mailer `log` por default; transporte real e alertas de entrega não são comprovados | `PARTIAL` |
| Config/env | `.env` e `.env.e2e` são ignorados pelo Git; somente exemplos estão versionados. Não foi lido nenhum valor secreto real | `PARTIAL` |
| Logs | Laravel pode escrever em `storage/logs/laravel.log`; rotação/retention/alerta externo não estão operacionalizados | `PARTIAL` |
| Monitoring | Não há Sentry, error reporter, métrica, synthetic check ou alert routing configurado | `MISSING` |
| Backups | Nenhum mecanismo, artefato, política ou evidência de sucesso encontrada | `MISSING` |

Containers ativos são evidência do host de desenvolvimento compartilhado, não de uma topologia de
produção. Não foram tratados como produção containers de outros projetos presentes no mesmo Docker host.

## 3. Release Provenance

O estado atual não é promovível como release:

- branch: `main`;
- HEAD: `8df531fbc826c79aa4073dfbb70ee7a191ad7cb7`;
- não há tags Git no repositório;
- working tree já estava dirty com **113 caminhos** modificados ou não rastreados;
- há alterações funcionais não commitadas e uma migration não rastreada,
  `app/Modules/Learning/Database/Migrations/2026_09_08_120000_add_content_to_lessons_table.php`;
- `composer.lock` existe, mas não há artefato imutável, digest de imagem, SBOM/proveniência de build ou
  manifesto de migrations associado a uma release.

O risco é concreto: o banco local atual já mostra a migration de `Lesson.content` como executada, mas
essa migration está fora do HEAD. Uma operação que copie o working tree, um volume montado ou uma imagem
construída em momento diferente pode produzir combinação diferente de código e schema.

Mínimo antes de RC, sem fazer nesta tarefa:

1. revisão funcional e commit aprovado;
2. working tree limpo;
3. SHA de release e tag/referência imutável;
4. `composer.lock` validado e build reprodutível com versão de PHP/imagem fixada;
5. manifesto com SHA, dependências, migrations esperadas e configuração não secreta;
6. registro de quem promoveu, quando, de qual artefato e com qual smoke result;
7. nenhum deploy a partir de bind mount ou working tree.

**Status:** `MISSING` para release candidate operacional. É `BLOCKER`.

## 4. Deploy

Há fragmentos reproduzíveis, mas não um processo de produção completo.

| Etapa | Evidência | Status |
|---|---|---|
| Obter versão | Git existe, mas não há tag/artefato imutável | `MISSING` |
| Instalar dependencies | `composer install` está em `composer.json`; sem procedimento de build produtivo fixado | `PARTIAL` |
| Configurar env | `composer setup` copia `.env` e gera key; isso é bootstrap local, não segregação de produção | `PARTIAL` |
| Migrations | `composer setup` chama `migrate --force`; não há janela, lock, rehearsal ou decisão de falha | `PARTIAL` |
| Caches | Não há etapa versionada de `config:cache`, `route:cache`, `view:cache` ou `optimize` no deploy | `MISSING` |
| Storage | Não há etapa produtiva para provisionar/verificar storage ou `storage:link` | `MISSING` |
| Workers | Não há serviço/process manager de worker | `MISSING` |
| Scheduler | Há apenas definição Laravel do schedule; nenhum executor contínuo | `PARTIAL` |
| Health validation | `/up` existe, mas só prova resposta HTTP; não prova DB/storage/queue/migration compatibility | `PARTIAL` |
| Ativar release | Não há mecanismo de release directory/symlink/maintenance/reverse proxy | `MISSING` |

`composer setup` também executa `npm install` e `npm run build`, embora o produto seja API-only; não é
um contrato adequado para uma promoção controlada. `composer qa:gate` é uma porta de QA, não um deploy:
inclui `migrate:fresh --env=testing --force`, Pint, análise e testes.

**Deployment status:** `PARTIAL` pelos fragmentos locais; `MISSING` para um deploy reproduzível de paid
pilot. A menor remediação é um runbook único para artefato imutável, env validado, migrations, cache,
storage, processo PHP/web, scheduler/worker conforme necessário, smoke e ativação.

## 5. Migrations

### Inventário e ordem

O repositório contém 73 migrations: 8 em `database/migrations` e 65 nos módulos. A execução somente
leitura de `php artisan migrate:status` no container local reportou as 73 como `[1] Ran`, inclusive a
migration de 2026-09-08. Isso confirma apenas o banco local atual, não uma base de produção.

A ordem é baseada nos timestamps dos arquivos carregados pelos Service Providers dos módulos. Não há
um inventário de upgrade por release nem um gate que compare schema esperado da release com o banco alvo.

### Operações de upgrade relevantes

As migrations não são apenas criação inicial. Há alterações de coluna, índices, foreign keys, colunas
geradas, renames e backfills:

- `certificates.course_id` é adicionado e preenchido com subquery em `enrollments`;
- `payments.charge_state` é preenchido em lotes de 100 e depois tornado non-null/default;
- configuração de plugin recebe backfill e revisões são copiadas linha a linha;
- `category_course` recebe `sort_order`, backfill por cursor e troca de unique/FK/indexes;
- users/enrollments/categories usam colunas geradas e alterações de índices/constraints;
- `lesson_progress` renomeia coluna e adiciona FKs/indexes;
- várias `down()` removem colunas, índices, FKs ou tabelas.

Essas operações podem adquirir locks e aumentar o tempo de indisponibilidade proporcionalmente ao
tamanho da tabela e ao plano do MySQL. O repositório não fornece medição de duração, lock timeout ou
prova de zero downtime; portanto downtime de migration deve ser assumido durante o piloto.

`Lesson.content` é uma adição nullable de JSON: código anterior pode, em princípio, continuar lendo o
schema com a coluna extra. O `down()` remove conteúdo criado depois da migration. Assim:

- rollback de **código** deixando schema aditivo é a opção preferida para essa mudança;
- rollback de **migration** não é uma estratégia segura depois de dados reais;
- migration com backfill/constraint change exige clone/staging com dados representativos e backup antes.

### Estratégia mínima para piloto

1. congelar a referência da release e registrar a lista de migrations pendentes;
2. snapshot/backup comprovado de DB e storage;
3. ensaiar `migrate --force` em clone restaurado;
4. executar em janela assistida, com maintenance/read-only se necessário;
5. não rodar `migrate:rollback` automaticamente em falha;
6. se o schema ou dados ficarem inconsistentes, parar tráfego, voltar código compatível e decidir
   restore do backup pré-release;
7. executar health, smoke HTTP e verificação de dados essenciais antes de abrir o tenant.

**Migration status:** `PARTIAL`. A ordem e o comando existem; upgrade seguro, lock policy, rehearsal e
rollback de dados não existem. É `MUST` antes do piloto e pode virar `BLOCKER` operacional se não houver
janela e backup.

## 6. Backup

Nenhum mecanismo real foi encontrado para:

- dump/snapshot de MySQL;
- cópia de arquivos de `storage/app`/media;
- frequência ou retenção;
- encryption at rest/in transit;
- destino remoto/offsite;
- owner operacional;
- identificação de sucesso/falha;
- restauração correlata de DB + files.

O volume MySQL local do Compose e o bind mount do projeto são persistência de desenvolvimento, não
backup. A existência possível de `mysqldump` no host não seria evidência de proteção.

**Backup status:** `MISSING`. **BLOCKER.** O mínimo é DB + storage em destino separado, com sucesso
observável, retenção definida, acesso restrito e execução antes de cada migration/release.

## 7. Restore

Não há restore executado, script, procedimento comprovado ou evidência de um backup restaurável.

### Teste mínimo desenhado

1. criar uma instância/database descartável explicitamente identificada;
2. inserir somente dados descartáveis cobrindo tenant, users, course, enrollment e progress;
3. gerar backup do DB e do storage associado;
4. registrar checksums, tamanho, timestamp e resultado do backup;
5. destruir somente o DB descartável;
6. restaurar DB e files em destinos descartáveis;
7. validar schema/migrations, tenant/users/course/enrollment/progress e integridade referencial;
8. iniciar a aplicação apontando para a cópia restaurada;
9. executar smoke HTTP de login, meus cursos, aula, progresso, roster e confirmação manual;
10. conferir que não há referência ao ambiente real e guardar o resultado do exercício.

As ferramentas atuais permitem criar um ambiente E2E descartável e verificar migrations, mas não
oferecem backup/restore coordenado nem prova de que arquivos acompanham o DB. O runner E2E não é um
substituto para restore: ele cria fixtures, pode rodar `migrate:fresh` e limpa fixtures; não protege
nem restaura dados.

**Restore status:** `MISSING`. **BLOCKER CRÍTICO.** Backup só conta para o gate depois deste exercício
verde, repetível e documentado.

## 8. RPO/RTO

Proposta humana para um paid pilot pequeno, assistido e sem SLA enterprise:

- **RPO proposto: até 24 horas** para DB e storage, condicionado a backup diário comprovado;
- **RTO proposto: até 4 horas úteis** para restaurar uma instância funcional, condicionado a runbook,
  operador disponível e restore ensaiado.

Essa proposta assume poucos tenants, cobrança principal externa/manual, pouca concorrência e suporte
próximo. Não é fato atual, não é SLA e deve ser aprovada pelo responsável humano do piloto. Se progresso
diário ou material recém-publicado for comercialmente crítico, o RPO deve ser reduzido antes do aceite.

## 9. Logging

### Estado

- canal default é `stack` para `single`, em `storage/logs/laravel.log`;
- o nível default configurado no arquivo é `debug`;
- canal `daily` de 14 dias existe, mas não é o default evidenciado;
- não há política de rotação, crescimento de disco, retenção ou coleta externa versionada;
- não há log de web server/queue/DB do ambiente produtivo no repositório;
- `activitylog` fornece trilha de mudanças de PII para models inventariados, mas não substitui
  observabilidade de exceções.

Há alguns logs de warning/error com IDs de order/outbox e classe de exceção. Não foi encontrado um
middleware de request/correlation ID nem um contexto consistente de tenant/user nos logs.

### Sensíveis

Não foram encontrados logs de payload bruto, Authorization header, senha ou token na aplicação auditada.
O runner E2E possui sanitização de Bearer/token/password, mas ela é local ao output do runner e não é
redação central de toda exceção/log. O token de convite e senha gerada por provisioning são exibidos
uma vez por design operacional; não podem ser persistidos em CI/log/agregador.

**Logs status:** `PARTIAL`. Para piloto é necessário nível apropriado, rotação, redaction testada,
correlation ID seguro, tenant/user ID não-PII quando útil, retenção e inspeção pós-deploy.

## 10. Error Monitoring

Não há Sentry, Bugsnag, Rollbar, OpenTelemetry, métrica de erro, synthetic alert ou pager configurado.
Há canais opcionais Slack/Papertrail no `config/logging.php`, porém nenhuma ativação, ownership ou prova
de entrega.

Um HTTP 500 pode cair no log local, mas não há evidência de que chegue a alguém sem o cliente avisar.

**Error monitoring status:** `MISSING`. **BLOCKER** para paid pilot: mínimo é alertar 5xx, falha de
DB, falha de queue/outbox e falha de backup para uma pessoa responsável. Não é necessário instalar
Sentry/Pulse nesta tarefa.

## 11. Health

Existe `/up` via `withRouting(... health: '/up')`. A chamada dentro do container respondeu `200 OK` e
`Application up`, mas a resposta não executa checks observáveis de DB, storage, fila ou compatibilidade
de migrations. O `migrate:status` local funcionou separadamente; isso não torna `/up` um readiness check.

| Sinal | Estado |
|---|---|
| Liveness HTTP | `PARTIAL/CONFIRMED` — `/up` responde |
| DB reachable | `CONFIRMED` somente no container local desta auditoria |
| Storage usable | `UNVERIFIED` — diretórios existem; não foi feito write/read de teste |
| Queue necessária | `UNVERIFIED` — nenhum worker produtivo foi observado |
| Migrations compatíveis | `UNVERIFIED` para produção |

**Health status:** `PARTIAL`. Antes do piloto, separar liveness de readiness e checar DB, storage,
migrations e a queue apenas se ela for necessária para a jornada; não exigir Kubernetes.

## 12. Rollback

### Code rollback

Ainda não é operacional: não há artefato/tags/release directories, nem manifesto de env. Depois de um
RC imutável, voltar o symlink/release para o SHA anterior é a estratégia preferida, mantendo schema
aditivo quando compatível.

### Migration rollback

Não deve ser o procedimento padrão. `down()` remove colunas, índices, FKs e tabelas; backfills e
alterações de constraints podem ter consumido dados/semântica. `migrate:rollback` não desfaz efeitos
externos nem recupera dados.

### Data restore

É o fallback realista para corrupção, migration incompatível ou combinação code/schema inválida. Ele
depende do backup + restore comprovados, atualmente ausentes.

**Rollback status:** `MISSING`. **BLOCKER.** Antes do piloto deve existir uma matriz explícita:

| Falha | Ação mínima |
|---|---|
| smoke falha sem alteração de dados | parar ativação, voltar código ao SHA anterior |
| migration falha antes de abrir tráfego | parar, preservar evidência, corrigir/repetir em clone |
| dados/schema comprometidos | código compatível + restore do backup pré-release |
| `Lesson.content` publicado na nova release | não usar `down()` sem decisão; restaurar ou manter schema aditivo |

## 13. Secrets

Inventário operacional necessário: `APP_KEY`, credenciais DB, mail, storage/S3, monitoring, gateway e
qualquer token de integração. O código lê esses valores via configuração, e configs de gateway tenant
usam configuração criptografada/hidden; valores reais não foram expostos nem lidos.

Pontos atuais:

- `.env`, `.env.production` e `.env.e2e` estão ignorados;
- não há secret manager, rotação, owner, segregação formal dev/e2e/prod ou procedimento de acesso humano;
- exemplos usam defaults de desenvolvimento como `APP_DEBUG=true`, URL HTTP, mail `log` e DB local;
- Compose expõe um padrão de usuário root para o banco local;
- o mínimo privilégio do usuário DB produtivo não pode ser confirmado.

**Secrets status:** `MISSING` para produção segura. **BLOCKER.** Definir env de produção fora do repo,
DB user sem DDL destrutivo para a aplicação, APP_KEY gerada e protegida, acesso auditado, rotação e
validação de que nenhum segredo aparece em logs/artefatos.

## 14. Queue/Workers

Jobs/efeitos relevantes:

| Item | Necessário ao fluxo principal? | Estado |
|---|---|---|
| Convite | Não: token é retornado uma vez e pode ser entregue manualmente | Manualmente operável |
| Password reset notification | Sim se reset por e-mail for prometido; `PasswordResetNotification` implementa `ShouldQueue` | Sem worker produtivo comprovado |
| Manual payment → enrollment | O publish é tentado no Action e o outbox persiste retry; não depende de worker para a confirmação inicial | Parcial |
| Outbox retry | Scheduler Laravel a cada minuto e comando manual existem; executor contínuo não existe | Parcial |
| RabbitMQ/statistics | Planejado, não necessário para o piloto assistido | Deferred |

Não há Horizon nem necessidade demonstrada de adicioná-lo. A tabela `failed_jobs` existe e o outbox
registra attempts/last error, mas não há worker, retry policy operacional ou alerta de falha.

**Queue status:** `PARTIAL`. Classificação: `SHOULD` para piloto invite-only; vira `MUST` se password
reset por e-mail ou qualquer side effect assíncrono for anunciado. Um operador deve conseguir executar
retry/outbox de forma segura e verificar o resultado.

## 15. Storage

O default de `config/filesystems.php` é o disco `local`, em `storage/app/private`; o Compose monta o
projeto como bind volume. `config/media-library.php` usa `public` por default, enquanto LessonMedia e
CourseMaterial admitem metadata/path e URLs temporárias para `local`/`s3`. Upload real/media provider
continua fora do slice funcional.

Riscos operacionais:

- não há bucket/volume produtivo, persistência entre releases ou capacity policy comprovados;
- não há backup de files correlacionado ao DB;
- container-local storage pode ser perdido ao recriar a aplicação;
- permissions observadas no container local não são política de produção;
- cleanup/retention de arquivos e signed/temp URL em produção não estão verificados.

Curso sem arquivo pode usar provider externo e reduzir o risco, mas isso precisa ser uma decisão
explícita do contrato do piloto. Para vender CourseMaterial/LessonMedia internos, storage durável,
privado, com URL temporária e backup é indispensável.

**Storage status:** `MISSING`. **BLOCKER** para qualquer curso que dependa de arquivo interno; no gate
geral de paid pilot, é `BLOCKER` até haver storage produtivo e backup comprovados.

## 16. Email/Onboarding

Onboarding assistido pode funcionar sem automação: operador cria o tenant, Admin emite convite, entrega
o token por canal controlado e o usuário aceita definindo a própria senha. O convite guarda somente hash
e o token claro aparece uma vez.

O reset de senha é diferente: a notificação é queued e o mailer default do template é `log`; Mailpit
é dev. Logo, não há prova de e-mail real ou worker para recuperação.

**Status:** `PARTIAL`. Classificação `SHOULD` para onboarding inicial manual; `MUST` condicionado à
promessa de reset por e-mail. Procedimento mínimo: registrar que o token foi entregue sem registrar o
valor, verificar aceite/login, e escalar recuperação ao operador se o canal de e-mail não estiver ativo.

## 17. TLS/Security

Não há domínio público, certificado HTTPS, reverse proxy, trusted proxy ou configuração de headers
básicos versionados para o produto. O exemplo usa `APP_URL=http://localhost`; cookie secure é apenas
configurável por env; não há configuração `cors.php` encontrada.

Antes de dados reais, o mínimo é:

- domínio e HTTPS obrigatório, redirect/termination documentado;
- `APP_DEBUG=false`, `APP_ENV` separado e `APP_URL` real;
- trusted proxies/IP/rate-limit verificados;
- CORS allowlist para o consumidor real, sem wildcard com credenciais;
- cookies/tokens e headers básicos revisados;
- smoke por URL pública e verificação de mixed content.

**TLS/security status:** `MISSING`. **BLOCKER**.

## 18. Production Safety

O hardening recente do runner E2E é uma melhoria real: aceita apenas `local|testing|e2e`, exige nome
de DB contendo `e2e|test` por padrão, executa canário servidor↔DB, tem timeout, circuit breaker de 5xx,
cleanup sinalizado e sanitização de output.

Ele não fecha o gate de produção:

- `--force-db` permite bypass explícito do guard de DB descartável;
- o guard depende de `APP_ENV`, que pode estar mal configurado;
- `--fresh` chama `migrate:fresh --force` depois do bypass;
- `composer qa:gate` contém `migrate:fresh --env=testing --force`;
- `tenant:provision` e `db:seed --force` são comandos mutantes sem uma deny policy de produção;
- não há DB user/credential separation comprovada que torne esses caminhos incapazes de atingir dados reais.

Caminho source→sink confirmado no código: operador/CI escolhe `e2e:run --force-db --fresh` → o teste
de nome do DB é bypassado → `callSilent('migrate:fresh', '--force')` → o banco configurado pode ser
apagado. O canário vem depois do `migrate:fresh`, portanto não protege esse caso.

**Production-safety status:** `MISSING`. **BLOCKER.** Separar credenciais, bloquear comandos destrutivos
em produção, exigir allowlist de host/DB/ambiente não burlável e remover `--force-db` de qualquer caminho
de produção antes do primeiro tenant real.

## 19. Manual Operations

| Operação | Responsável | Procedimento e verificação | Recovery/rollback |
|---|---|---|---|
| Provisionar tenant | Operador MZRT | Usar `tenant:provision`/endpoint canônico; confirmar tenant ativo, Admin e preset cash; não registrar senha/token | Reexecutar idempotente ou suspender tenant; nunca apagar dados diretamente |
| Preparar curso | Admin/Instructor | Criar Course→Module→Lesson, configurar metadata, publicar explicitamente; smoke como Student | Unpublish, corrigir e republicar; preservar histórico |
| Convidar usuário | Admin | Emitir convite, transferir token por canal seguro, confirmar aceite/login | Reemitir/invalidar convite conforme endpoint; não consultar hash para reconstruir token |
| Cobrança externa | Financeiro/operador | Registrar pagamento fora da API, conferir referência/valor e tenant, depois usar confirmação manual canônica | Não marcar DB direto; corrigir/reconciliar pelo fluxo manual auditável |
| Matrícula | Admin/operador | Criar/confirmar enrollment pelo endpoint, conferir order/payment/outbox e acesso do aluno | Cancelar pelo fluxo autorizado; não remover linhas para desfazer pagamento |
| Suporte | Operador/on-call | Correlacionar request/tenant/user por IDs não sensíveis, consultar logs/audit e reproduzir em E2E isolado | Se incidente de dados: parar, preservar evidência, decidir restore |
| Backup | Release operator | Executar DB + storage, conferir checksum/retention/success e registrar referência | Falha de backup interrompe migration/deploy |
| Restore | Release operator + owner | Executar somente em destino descartável no primeiro exercício; validar smoke e side effects | Restore real somente após decisão humana e janela comunicada |

Manual não significa acesso direto a tabelas, alteração de `tenant_id/user_type/ownership`, bypass de
Policy, exposição de token ou confirmação financeira sem trilha.

## 20. Observability

Sinais mínimos de operação:

| Sinal | Cobertura atual | Gate |
|---|---|---|
| HTTP 5xx | log local possível; sem alerta | `MUST` |
| Latência grossa | nenhuma medição/synthetic | `MUST` |
| DB failure | exceção/log; `/up` não testa DB | `MUST` |
| Disk/storage | nenhuma métrica/threshold | `MUST` |
| Queue/outbox failure | failed_jobs/outbox/log parcial; sem alerta | `SHOULD`, `MUST` se async usado |
| Backup failure | nenhum sinal | `MUST` |

Minimum operacional sem nova ferramenta obrigatória: um synthetic check externo de `/up` e readiness,
contagem de 5xx/latência no proxy ou log collector, alerta de DB/storage, alerta de outbox/failed jobs e
alerta de backup ausente/falho. Cada alerta precisa de owner, canal, severidade e procedimento.

## 21. Release Runbook

### Pre-deploy

1. confirmar SHA/tag imutável, working tree limpo e lockfile;
2. confirmar diff aprovado e manifesto de migrations;
3. validar env de produção sem imprimir valores: APP_KEY, debug off, DB least privilege, mail,
   storage, URL, proxy e monitoring;
4. confirmar backup DB + storage verde e restore reference;
5. confirmar janela, responsável e plano code rollback/restore;
6. bloquear qualquer teste destrutivo e tráfego não planejado.

### Deploy

1. publicar o artefato imutável em release directory/container;
2. instalar dependências conforme lockfile e build provenance;
3. colocar serviço em maintenance/read-only se a migration exigir;
4. executar `php artisan migrate --force` uma vez, com output/exit code registrados;
5. aquecer caches/config/routes somente após env validado;
6. verificar storage/link/permissions sem expor files;
7. ativar PHP/web, scheduler e worker somente se a jornada os exigir;
8. ativar a referência de release.

### Verify

1. liveness e readiness;
2. smoke HTTP de auth/invite, tenant, curso publicado, enrollment manual, Student consumption,
   progress e Instructor mínimo;
3. se houver Assessment/certificado no curso comercial, usar somente o gate funcional já declarado;
4. confirmar outbox/queue/failed jobs e logs sem token/PII indevida;
5. confirmar que o tenant real vê somente seu escopo.

### Failure

1. parar a ativação e preservar logs/IDs/exit codes;
2. se não houve alteração de dados, voltar código ao SHA anterior;
3. se schema/dados foram alterados, não executar `migrate:rollback` por reflexo;
4. decidir restore do backup pré-deploy conforme impacto e RPO;
5. reexecutar health/smoke em destino restaurado e comunicar o tenant.

### Post-deploy

1. manter synthetic/alerting sob observação assistida;
2. revisar 5xx, latência, DB, queue/outbox, disk e storage;
3. validar auditoria de operações e ausência de tokens/secrets nos logs;
4. registrar release SHA, migration batch, backup, smoke, operador e decisão de aceite.

## 22. Blockers

1. **Backup real ausente:** DB e files não têm mecanismo, retenção, destino ou prova.
2. **Restore não comprovado:** sem restore, não existe recuperação confiável.
3. **Release dirty/não imutável:** 113 caminhos sujos, sem tag/artefato; code/schema drift é possível.
4. **Deploy produtivo incompleto:** não há processo de ativação, cache, storage, worker e health integrado.
5. **Rollback incompleto:** code rollback depende de release; data rollback depende de restore inexistente.
6. **Storage produtivo não comprovado:** default local e ausência de backup tornam mídia/material frágeis.
7. **Error monitoring ausente:** 500 pode não chegar a um responsável.
8. **TLS/domínio/proxy/debug de produção não confirmados:** dados reais não devem entrar nessa superfície.
9. **Via de teste destrutivo:** `--force-db --fresh` pode alcançar DB não descartável se executado com
   configuração errada ou credencial ampla.
10. **Least privilege/secrets/access não comprovados:** DB e secrets de produção ainda não têm owner,
    rotação e segregação demonstrados.

## 23. MUST/SHOULD/DEFERRED

### MUST

- RC limpo, SHA/tag imutável, lockfile e provenance;
- deploy runbook reproduzível com env validation, migrations, caches, activation e smoke;
- backup DB + storage real, protegido, com retenção e sinal de sucesso;
- restore drill verde cobrindo schema, tenant, user, course, enrollment, progress e smoke HTTP;
- code rollback e decisão explícita de restore;
- storage persistente e privado para qualquer material/mídia interno;
- HTTPS, domínio, trusted proxy, debug off, CORS/headers e public URL revisados;
- secrets fora do repo, APP_KEY segura, DB least privilege e acesso/rotação definidos;
- basic error visibility e alert owner para 5xx, DB, storage e backup;
- readiness que valide DB/storage/migrations e queue quando necessária;
- bloqueio não burlável de tests/migrate:fresh contra produção;
- staging/clone rehearsal das migrations e janela assistida;
- manual operations com responsável, verificação e recovery.

### SHOULD

- worker persistente para reset email e scheduler para retry do outbox;
- structured logs/correlation ID e coleta remota com redaction;
- métrica básica de latência/5xx e synthetic check externo;
- build em imagem/digest fixo e SBOM/provenance automatizada;
- backup mais frequente que 24h se progresso/material recente for crítico;
- runbook de rotação de APP_KEY/DB/mail/storage/monitoring secrets.

### DEFERRED

- Horizon, Pulse, Sentry ou outra ferramenta específica, se a observabilidade mínima puder ser coberta
  pela stack operacional escolhida;
- RabbitMQ, MariaDB de estatísticas e escalabilidade enterprise;
- zero-downtime/blue-green, multi-region, read replicas e DR enterprise;
- PSP/webhooks/reembolsos automáticos;
- expansão de Assessment Student, certificados ou qualquer capability funcional fora do estado informado.

## 24. Remediation Plan

Sequência mínima baseada somente nos gaps encontrados:

1. **Fixar release:** encerrar/revisar mudanças funcionais existentes, gerar SHA/tag, lockfile e
   manifesto; não promover working tree.
2. **Escolher topologia mínima:** serviço web PHP atrás de HTTPS, DB gerenciado/isolado, storage
   persistente privado e mail real ou onboarding invite-only explícito.
3. **Blindar produção:** env/debug/secrets/DB user, acesso humano, CORS/proxy, deny de comandos
   destrutivos e separação inequívoca de DB E2E/testing.
4. **Implementar operação de dados:** backup DB+storage, retention/encryption/offsite, sucesso observável
   e restore drill descartável.
5. **Ensaiar migrations:** clone restaurado, dados representativos, medir locks/duração, definir janela
   e confirmar code rollback sem `down()` destrutivo.
6. **Fechar deploy:** artefato, dependencies, migrations, caches, storage, processes, activation,
   readiness e smoke.
7. **Fechar observabilidade:** 5xx/latency/DB/storage/backup alerts e log redaction/correlation.
8. **Operar um tenant sintético:** provisioning, invite, manual payment/enrollment, consumption,
   progress, support, backup/restore e rollback rehearsal.
9. **Aceite humano:** registrar RPO/RTO, owners, janela, curso/claims permitidos e decisão final.

## 25. Timeline

Estimativa nova, não herdada de relatórios funcionais e condicionada a infraestrutura/owner disponíveis:

| Cenário | Data proposta | Premissa |
|---|---:|---|
| Best case | **2026-09-15** | uma pessoa operacional, DB/storage gerenciados já disponíveis, sem correção inesperada nas migrations |
| Realistic | **2026-09-22** | 10 dias úteis para secrets/TLS, backup+restore, rehearsal, deploy, observability e dois exercícios assistidos |
| Conservative | **2026-10-06** | provisionamento externo atrasado, lock/migration issue, ajustes de storage/mail e novo restore drill |

As datas são planejamento, não compromisso nem evidência de readiness atual. Qualquer falha no restore,
TLS, provenance ou production safety mantém o veredito fechado.

## 26. Operations Verdict

**`OPERATIONS_NOT_READY`**

A aplicação local está executável e o código contém peças úteis para operação assistida, mas não há
cadeia completa e comprovada de release → deploy → backup → restore → monitoramento → rollback. O
ambiente E2E não pode ser usado como proxy de produção, e o estado runtime observado reforça essa
separação.

## 27. Paid Pilot Verdict

**`PAID_PILOT_NOT_READY`**

O menor conjunto operacional ainda necessário é: release imutável, deploy reproduzível, backup real,
restore comprovável, rollback strategy, storage persistente, HTTPS/secrets de produção, visibilidade
básica de erro, readiness e bloqueio de comandos destrutivos. Operações manuais de onboarding, cobrança
externa, matrícula e suporte são aceitáveis depois que esse conjunto estiver verde.

Não é necessário abrir WS2/WS3 nem expandir o produto funcional para resolver estes blockers.
