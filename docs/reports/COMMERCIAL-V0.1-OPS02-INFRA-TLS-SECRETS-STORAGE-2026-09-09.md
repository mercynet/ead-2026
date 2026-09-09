# Commercial v0.1 — OPS-02 Infrastructure, TLS, Secrets & Storage

Data: 2026-09-09
Escopo: preparação local/staging provider-neutral para um paid pilot; nenhum deploy público.

## 1. Baseline

O baseline observado antes desta task era `PRODUCT_FUNCTIONAL_CLOSURE_CONFIRMED`,
`RC_CHECKPOINTED_LOCAL`, Git limpo e candidate funcional commitado. O repositório tinha apenas
Compose Sail de desenvolvimento: source bind-mounted, MySQL com credencial/root compartilhada,
Redis e Mailpit expostos, sem imagem de produção ou reverse proxy versionado.

O domínio real de hosting, VPS/VM, domínio/DNS, SMTP, object storage, Redis gerenciado e provedor de
backup não foi fornecido nem descoberto no workspace. Portanto, não foi inventado fornecedor.

## 2. Hosting Assumptions

Assumptions explícitas para o piloto:

- um único Docker host/VPS controlado pelo operador;
- DNS aponta `APP_DOMAIN` para o host;
- Caddy pode alcançar a Internet para ACME e o SMTP selecionado pode ser alcançado pelo app;
- MySQL e storage são persistentes no host;
- os valores reais de domínio, DNS, SMTP, backup e credenciais serão fornecidos fora do Git.

Essas são premissas de preparação, não evidência de recursos existentes.

## 3. Target Topology

```text
Internet :80/:443
       │
       ▼
   Caddy (edge, ACME/TLS, redirect, headers)
       │ private network :9000
       ▼
   PHP-FPM app (imagem por SHA, sem bind mount)
       │ private network :3306
       ├── MySQL (volume persistente; sem porta publicada)
       ├── storage/app/private (volume persistente; privado)
       └── scheduler (sempre) / worker (profile opcional)
```

Somente Caddy publica portas. O app expõe apenas PHP-FPM na rede interna; DB não tem `ports`.
Não há Kubernetes, autoscaling, multi-region, service mesh ou observability platform nesta task.

## 4. Production Env

`.env.production.example` foi criado sem segredo real. O Compose exige explicitamente ambiente,
`APP_KEY`, SHA da imagem, URL HTTPS, trusted proxy/host, DB, storage, mail, CORS e TLS. O contrato
seleciona `FILESYSTEM_DISK=local` e `MEDIA_DISK=local` em volume privado host-managed; S3 permanece
compatível pela configuração existente, mas não foi escolhido sem provider real.

`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=notice`, SMTP
explícito e queue database são defaults do contrato operacional. O script
`scripts/ops/validate-production-env.sh` falha fechado para placeholders, URL HTTP, wildcard CORS,
DB de teste/E2E, root e TLS Caddy interno.

## 5. Secrets

| Secret | Owner | Armazenamento/acesso | Runtime | Rotação/logs |
|---|---|---|---|---|
| `APP_KEY` | operador da aplicação | env protegido do host/CI, fora do Git | app, criptografia/session | rotação planejada com `APP_PREVIOUS_KEYS`; nunca logar |
| `DB_ADMIN_PASSWORD` | operador DB | host/bootstrap restrito | somente inicialização/admin | rotação controlada; nunca app/log |
| `DB_RUNTIME_PASSWORD` | operador DB | env protegido do host/CI | app/scheduler/worker | rotação coordenada; nunca logar |
| `DB_MIGRATION_PASSWORD` | operador deploy | env protegido, job de migração | somente comando de deploy | por release ou política definida; nunca logar |
| `DB_BOOTSTRAP_PASSWORD` | operador DB | env de bootstrap inicial | somente init do DB | remover usuário após init; nunca app |
| SMTP credentials | owner de mail | env/secret store do host/CI | app quando mail for prometido | rotação pelo provedor; nunca payload/log |
| storage credentials | owner storage | env/secret store se S3 for escolhido | app, se `s3` | rotação pelo provider; nunca URL/log |
| monitoring token | owner ops | env/secret store | ainda não usado | definir no OPS-04; nunca log |
| gateway/plugin tokens | owner Financial/Ecosystem | configuração cifrada/secret store | somente adapter/resolver | rotação por provider; nunca log |

Para o piloto, env protegido no host é aceitável somente com arquivo fora do Git, permissions
restritas, backup controlado e acesso humano definido. O workspace não contém secret manager nem
segredo real.

## 6. DB Credentials

O container MySQL usa um usuário bootstrap temporário apenas para permitir a inicialização da
imagem. `docker/mysql/init-users.sh` remove esse usuário e cria:

- `DB_RUNTIME_USERNAME`: somente `SELECT, INSERT, UPDATE, DELETE` no schema do app;
- `DB_MIGRATION_USERNAME`: `ALL PRIVILEGES` no schema do app para DDL durante deploy controlado.

Nenhum deles recebe `CREATE DATABASE` ou `DROP DATABASE` global. O runtime da aplicação recebe
somente a primeira credencial; `DB_ADMIN_*`, bootstrap e migration não entram no environment do app.
Migração deve ser executada como job/one-off separado com `DB_USERNAME`/`DB_PASSWORD` substituídos
pela credencial de migration, sem alterar o serviço persistente.

## 7. Production Safety

Há defesa em profundidade:

1. `DestructiveDatabaseGuard` e `E2eRunCommand` já recusam E2E fora de `testing|e2e`;
2. Compose exige `APP_ENV=production` e nome de DB que não seja `testing`/`e2e`;
3. DB e E2E devem usar nomes/hosts/volumes separados;
4. a credencial runtime não tem `DROP`, `ALTER` ou `CREATE`, então `migrate:fresh` falha no DB;
5. somente migration user recebe DDL, em comando controlado.

O usuário E2E continua sendo o da stack descartável separada (`.env.e2e`, DB contendo `e2e`), nunca
o runtime de produção. `qa:fresh`/`e2e:run` continuam proibidos em `production`; o caminho `--force-db`
já foi removido pelo hardening anterior.

## 8. TLS

`docker/caddy/Caddyfile` define termination TLS, redirect explícito HTTP→HTTPS, ACME por e-mail
(`CADDY_TLS_DIRECTIVE`), HSTS e headers básicos. `CADDY_TLS_DIRECTIVE=internal` é aceito somente
para rehearsal local; o validador recusa esse valor em `APP_ENV=production`. `bootstrap/app.php`
passa a configurar trusted proxies e trusted hosts por allowlist fornecida no env.

Não existe domínio/certificado real no workspace; portanto o veredito é configuração pronta, não
TLS staging verificado.

## 9. HTTP Security

Foi adicionada configuração CORS sem wildcard implícito, com origens explícitas e credentials false
por padrão (a API usa token Bearer). O contrato exige `CORS_ALLOWED_ORIGINS`; cookies de sessão são
secure/HTTP-only/SameSite no env de produção. Caddy adiciona HSTS, `nosniff`, `DENY`, Referrer-Policy
e remove o header `Server`.

HSTS só deve ser mantido após HTTPS estável no domínio real. A lista `TRUSTED_PROXIES` deve ser
substituída pelos endereços reais do proxy; não se deve copiar a faixa de rehearsal cegamente.

## 10. Storage

Decisão operacional concreta para este piloto: volume Docker nomeado `PRODUCTION_STORAGE_VOLUME`,
montado em `/var/www/html/storage`, fora do filesystem efêmero da imagem e sem exposição pelo Caddy.
O disco `local` usa root `storage/app/private`, visibility `private`, arquivos `0600` e diretórios
`0700`; o default do Media Library também foi alterado de `public` para `local`.

CourseMaterial e LessonMedia continuam acessados pela autorização existente antes da URL temporária.
O contrato de provider MediaProvider permanece em aberto; nenhuma adapter nova ou decisão humana foi
inventada. O caminho S3 continua disponível para uma decisão posterior com provider real.

## 11. Persistence Canary

Canário planejado para staging/local:

1. gravar arquivo descartável em `Storage::disk('local')` no volume;
2. lê-lo pela instância app e obter URL temporária somente após autorização;
3. reiniciar/recriar app sem remover volumes;
4. ler novamente e validar que o arquivo persiste;
5. validar que outro tenant não obtém a URL;
6. remover o arquivo.

Resultado nesta sessão: **não executado**. O daemon Docker do sandbox recusou acesso ao socket
(`/var/run/docker.sock`); não há staging seguro configurado. Logo não há `RUNTIME_VERIFIED` nem
`STORAGE_PERSISTENCE_VERIFIED`.

## 12. Build

`Dockerfile.production` é multi-stage: Composer instala apenas `--no-dev` a partir do lockfile;
PHP-FPM 8.4 recebe extensões necessárias; o SHA é label/env da imagem; não há `php artisan serve`,
bind mount do source, Mailpit, Node ou dependências de desenvolvimento no runtime. O target `web`
incorpora apenas `public/` e Caddyfile; o target `app` contém o código PHP e vendor.

Build real não foi executado por indisponibilidade do daemon Docker. O Compose e os shell scripts
foram validados estaticamente.

## 13. Web Server

Caddy 2.9 é o único serviço de borda e encaminha PHP para `app:9000` via `php_fastcgi`. A aplicação
não depende de `php artisan serve`. Portas internas são 9000/3306; apenas 80/443 são publicadas no
Compose de produção.

## 14. Worker/Scheduler

- Scheduler: **MUST** para o piloto, porque `financial:drain-order-paid-outbox` está agendado a cada
  minuto; o serviço `scheduler` executa `php artisan schedule:work`.
- Worker: **SHOULD/condicional**. O fluxo comercial/manual publica o outbox na request e mantém
  retry persistido. Password reset por e-mail usa notificação queued; se esse canal for prometido,
  iniciar o profile `worker` e validar SMTP/failed jobs antes do piloto.
- RabbitMQ/Redis/Horizon: deferred; não necessário para o primeiro piloto assistido.

## 15. Rehearsal

O mesmo Compose pode ser usado em host local/staging seguro, com `HTTP_PORT/HTTPS_PORT` não
privilegiadas e `CADDY_TLS_DIRECTIVE=internal` apenas para TLS local equivalente. O roteiro é:

```bash
cp .env.production.example /caminho/seguro/.env.production
# substituir todos os placeholders; manter o arquivo fora do Git
docker compose --env-file /caminho/seguro/.env.production -f compose.production.yaml build
docker compose --env-file /caminho/seguro/.env.production -f compose.production.yaml up -d
docker compose --env-file /caminho/seguro/.env.production -f compose.production.yaml exec app php artisan migrate --force
docker compose --env-file /caminho/seguro/.env.production -f compose.production.yaml ps
```

Para migração, substituir explicitamente `DB_USERNAME`/`DB_PASSWORD` pela credencial de migration
em um job temporário. Não executar `migrate:fresh` e não apontar o arquivo para produção.

O roteiro não foi executado nesta sessão por limitação ambiental do socket Docker.

## 16. Security Review

Nenhum secret real foi lido ou adicionado. O `.dockerignore` exclui `.env*`, vendor, testes, logs e
artefatos de desenvolvimento. O Compose não publica DB/9000, não contém Mailpit nem source bind mount.
O init SQL revoga privilégios do usuário bootstrap e concede somente DML ao runtime.

Finding residual de preparação: o storage local ainda depende de volume e backup/restore não existem;
isso é blocker operacional, não um vazamento confirmado. A URL temporária local pode carregar o
caminho lógico assinado na URL; a resposta API continua sem expor `storage_path` bruto conforme os
testes existentes. Uma URL opaca/proxy binário é decisão futura do MediaProvider e não foi criada.

## 17. Tests/Evidence

Confirmado estaticamente:

- `bash -n scripts/ops/validate-production-env.sh docker/mysql/init-users.sh` — passou;
- `docker compose --env-file .env.production.example -f compose.production.yaml config --quiet` — passou;
- `docker compose ... config --services` — `db`, `app`, `scheduler`, `web` (worker é profile opcional);
- `git diff --check` — passou;
- o validador rejeitou `.env.production.example` por placeholders — fail-closed confirmado;
- `tests/Architecture/ProductionInfrastructureContractTest.php` foi adicionado para impedir drift
  estático, mas não foi executado porque PHP/artisan devem rodar no container e o daemon não está
  acessível neste sandbox;
- Architecture/PHPStan/Pint, build, DB privilege canary, storage canary, HTTPS smoke e app smoke:
  **não confirmados** nesta sessão.

## 18. Commits

O primeiro commit local desta task é `4e69cbc` (`feat(ops): add production rehearsal topology`).
Este relatório e o teste arquitetural estático estão sendo consolidados no segundo commit local;
o hash final é reportado no handoff Git desta sessão. Não houve push.

## 19. Remaining Blockers

- hosting real, domínio/DNS e certificado ACME não fornecidos;
- build/start/restart não executados contra Docker;
- storage persistence canary não executado;
- backup/restore ausentes;
- migration/deploy rehearsal e rollback ausentes;
- storage backup/retention/capacity policy ausentes;
- SMTP real e worker não verificados;
- monitoring/readiness/error visibility ainda ausentes;
- credenciais reais, owner humano e rotação ainda precisam ser configurados fora do Git;
- `MediaProvider` continua `HUMAN_DECISION_REQUIRED`.

## 20. Verdicts

| Área | Veredito | Motivo |
|---|---|---|
| Infrastructure | `INFRA_READY_FOR_REHEARSAL` | topologia/image/Compose versionados; runtime Docker não provado |
| TLS | `TLS_CONFIG_READY` | Caddy/redirect/trusted proxy/host configurados; sem domínio/cert real |
| Secrets | `SECRETS_CONTRACT_READY` | inventário, owners, storage/access/rotation e env sem segredos |
| Runtime DB credential | `CONFIGURED_STATIC_ONLY` | DML-only no init SQL; grants não executados |
| Migration credential | `CONFIGURED_STATIC_ONLY` | DDL por schema separado; job controlado ainda não ensaiado |
| E2E credential isolation | `STATIC_EVIDENCE_ONLY` | guards/nomes separados e E2E existente; canário externo não executado |
| Production safety | `PARTIAL` | guards + DB privilege design; prova runtime e deploy controls faltam |
| Storage | `STORAGE_CONFIG_READY` | volume privado, disk local privado e URLs temporárias configurados |
| Storage technology | volume Docker host-managed, provider-neutral | S3 não escolhido sem provider real |
| Persistence canary | `NOT_RUN` | daemon Docker inacessível no sandbox |
| Web server/build | `CONFIG_READY_NOT_BUILT` | Caddy + PHP-FPM multi-stage; build não executado |
| Scheduler | `CONFIGURED_REQUIRED` | `schedule:work` para retry do outbox |
| Worker | `OPTIONAL_CONDITIONAL` | profile separado; MUST somente se reset por e-mail for prometido |

## Paid Pilot verdict

`PAID_PILOT_NOT_READY`. OPS-02 está preparado para rehearsal, mas backup/restore, deploy/rollback,
storage persistente comprovado, TLS real, secrets provisionados, monitoring/readiness e synthetic
pilot ainda são necessários.
