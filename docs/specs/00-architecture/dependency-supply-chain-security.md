---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-13
---

# Segurança de Dependências e Supply Chain

## Intenção

Reduzir o risco de **malware, backdoors e execução inesperada** introduzidos por packages Composer
de terceiros no ciclo de desenvolvimento e CI. Esta spec define a política local de dependências,
o contrato do comando Artisan `security:audit-deps` e o mínimo obrigatório de enforcement em
**pre-commit**, **pre-push** e **CI**.

O objetivo **não** é “provar que um package é seguro”. O objetivo é detectar cedo sinais de risco,
forçar revisão humana e bloquear mudanças perigosas antes que entrem no repositório.

## Threat Model coberto

### 1. Execução automática no bootstrap

Ataques que ganham execução sem ação explícita da aplicação:

- `autoload.files` em `composer.json` do package (caso real: **laravel-lang**, 2026).
- `extra.laravel.providers` / package discovery do Laravel.
- callbacks/scripts que rodam em `post-autoload-dump` do root package.

### 2. Execução durante install/update

- packages `type: composer-plugin`.
- alteração maliciosa em `config.allow-plugins`.
- scripts root com `php -r`, `bash`, `sh`, `curl`, `wget`, `powershell`, `cscript`.

### 3. Compromisso do upstream

- **tag rewriting / tag hijack** (ex.: `laravel-lang/*`).
- takeover de conta GitHub/Packagist/PAT.
- typosquatting.
- mudança de `source.url`, `dist.url` ou `reference` para origem não confiável.

### 4. Payload de segunda fase / exfiltração

- loaders/downloader em `helpers.php`, `functions.php`, `ServiceProvider`, `bin`.
- uso de `exec`, `shell_exec`, `proc_open`, `curl`, `fsockopen`, `stream_socket_client`,
  `base64_decode`, `gzinflate`, `assert(string)`.
- leitura de `.env`, `auth.json`, `/proc/*/environ`, chaves SSH, secrets de CI/cloud.

### 5. Drift local / adulteração do vendor

- conteúdo em `vendor/` divergente de `composer.lock`.
- package presente em `vendor` mas ausente no lock.
- binários, `.phar`, `.so`, `.dll`, `.exe` ou symlinks inesperados.

## Limites explícitos

Detecção local/offline **não cobre** completamente:

- advisories/CVEs recém-publicados sem base externa.
- upstream comprometido após o download local.
- malware que só ativa em runtime específico ou baixa payload remoto depois.
- distinção perfeita entre código legítimo e malicioso quando ambos parecem plausíveis.

Por isso, esta spec combina **análise local** + **controles de workflow** + **checagens gratuitas de CI**.

## Política de Dependências

### Regras do root `composer.json`

1. `composer.lock` é **obrigatório** e versionado.
2. Não usar `"*"`, `dev-main`, `dev-master` ou `minimum-stability=dev`, salvo allowlist explícita.
3. `config.allow-plugins` deve ser **whitelist explícita**; nunca `true` global nem curingas.
4. `repositories` custom só entram com justificativa e host allowlisted.
5. `secure-http` deve permanecer habilitado.
6. Scripts root devem ser mínimos, legíveis e revisáveis; comandos shell dinâmicos são suspeitos.
7. Toda dependência nova entra por PR/commit dedicado; evitar atualizar “meio mundo” de uma vez.

### Política de update

Updates devem ser estreitos e executados via Sail:

```bash
./vendor/bin/sail composer update vendor/package --with-all-dependencies
```

Evitar `composer update` amplo sem necessidade. Dependências novas ou upgrades grandes exigem
revisão explícita de `composer.lock` e auditoria.

## Contrato do comando Artisan

Comando canônico:

```bash
./vendor/bin/sail artisan security:audit-deps
```

### Objetivo

Inspecionar `composer.json`, `composer.lock` e opcionalmente `vendor/` em busca de sinais de risco
de supply chain. O comando é **offline por padrão**, determinístico e gratuito.

### Modos

- `--lock-only` — só `composer.json` + `composer.lock`; rápido o suficiente para pre-commit.
- `--scan-vendor` — adiciona drift e heurísticas em `vendor/`; obrigatório em pre-push/CI.
- `--include-dev` — inclui `require-dev` e bins/plugins de desenvolvimento.
- `--format=table|json` — humano ou máquina.
- `--fail-on=low|medium|high|critical` — threshold de falha.
- `--no-baseline` — ignora baseline.
- `--generate-baseline` / `--update-baseline` — fluxo controlado de ruído conhecido.
- `--all` — expande também os sinais **abaixo do threshold** (por padrão eles são resumidos em
  uma linha agregada por regra, para o output ficar legível em emergência).

### Saída e exit codes

Veredito de um relance (banner), pensado para decisão rápida (ex.: commit em emergência):

- **PASS** (verde) — nenhum finding ativo. Exit `0`.
- **REVIEW** (amarelo) — há findings, mas **todos abaixo do threshold**. Não bloqueia. Exit `0`.
  O detalhe é colapsado em uma linha (`autoload_files ×24, ...`); use `--all`/`--format=json`.
- **FAIL** (vermelho) — há finding **no nível do threshold ou acima**. Exit `1`. Só os bloqueantes
  são detalhados (tabela + evidência + recomendação + fingerprint); o resto vai para a linha-resumo.

> O banner reflete o **mesmo critério do exit code** (`highestSeverity ≥ fail-on`). Não pode haver
> "FAIL visual com exit 0" — foi o bug corrigido.

| Exit | Quando |
|------|--------|
| `0` | PASS (sem findings) **ou** REVIEW (todos abaixo do threshold) |
| `1` | FAIL (finding ≥ threshold) |
| `2` | erro operacional/configuração |

Cada finding expõe:

- `rule`
- `severity`
- `package`
- `version`
- `file`
- `evidence`
- `recommendation`
- `integrity` — referência fixada do artefato (`dist.reference` / `shasum` / `source.reference`)
- `fingerprint` — inclui `version` **e** `integrity` (ver baseline)

## Regras mínimas do scanner

### Root manifest (`composer.json`)

O comando deve marcar:

- wildcard version (`*`) → `medium`
- `dev-main`, `dev-master`, aliases de branch → `medium`
- `minimum-stability=dev` ou `prefer-stable=false` → `medium`
- `allow-plugins` amplo ou plugin novo não allowlisted → `high|critical`
- `repositories` em `http://` → `critical`
- `repositories` custom não allowlisted → `high`
- scripts root com shell/download/exec/obfuscação → `high|critical`
- `secure-http=false` → `critical`

### Lockfile (`composer.lock`)

O comando deve inspecionar:

- package novo desde baseline → `info|medium`
- `type: composer-plugin` → `high` se não allowlisted
- `autoload.files` → `medium`, subir para `high` se combinado com padrões suspeitos
- `extra.laravel.providers` / auto-discovery → `medium`
- `bin` novo ou inesperado → `medium`
- `source.url` / `dist.url` fora de host confiável → `high`
- package abandonado → `medium`
- `replace` / `provide` amplos → `medium`

### Vendor scan (`vendor/`)

Quando `--scan-vendor` estiver ativo, o comando deve verificar:

- package em `vendor` ausente no lock → `critical`
- package no lock ausente em `vendor` → `high`
- divergência entre `vendor/*/*/composer.json` e lock → `high`
- symlink suspeito → `high`
- binários inesperados (`.phar`, `.so`, `.dll`, `.exe`) → `medium|high`
- uso suspeito em arquivos autoexecutáveis (`autoload.files`, providers, bins):
  `eval`, `assert(string)`, `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`,
  `curl_*`, `fsockopen`, `stream_socket_client`, `base64_decode`, `gzinflate` → `high`
- leitura/escrita de `.env`, `auth.json`, chaves, secrets, diretórios home ou CI → `high`

Heurística não deve classificar “malware” automaticamente; o texto sempre fala em **sinal de risco**
e exige revisão humana.

## Policy e baseline

### Arquivo de policy

Configuração em `config/dependency_audit.php` com no mínimo:

- `trusted_composer_plugins`
- `trusted_repository_hosts`
- `trusted_vendor_bins`
- `trusted_laravel_providers`
- `fail_on`
- `vendor_scan_paths`

### Baseline

Arquivo versionado: `security/dependency-audit-baseline.json`.

Cada entrada precisa de:

- `fingerprint`
- `rule`
- `package`
- `reason`
- `owner`
- `expires_at`

Regras:

1. baseline nunca ignora “pacote inteiro” por wildcard.
2. mudança de versão **ou de `dist.reference` (re-tag malicioso na mesma versão)** reabre finding —
   o `fingerprint` inclui `integrity`, então um artefato trocado sob a mesma string de versão
   re-surge em vez de ser silenciosamente suprimido. Custo de churn zero: update legítimo já muda
   a versão. (Esse é o vetor do incidente laravel-lang.)
3. expiração é obrigatória.
4. findings `critical` não entram em baseline sem decisão arquitetural explícita.

## Hook de Git obrigatório

Hooks versionados em `.githooks/` (`pre-commit`, `pre-push`). Ativados via
`git config core.hooksPath .githooks`, **religado automaticamente** a cada `composer install/update`
pelo script `git:hooks` (chamado no `post-autoload-dump`). Rodar `composer git:hooks` religa manual.
Invariante coberta por teste (`SecurityAuditDepsCommandTest`): se a fiação sumir, o teste quebra.

### Pre-commit

O repositório deve instalar um hook `pre-commit` que execute o comando **quando houver mudanças em**:

- `composer.json`
- `composer.lock`
- `config/dependency_audit.php`
- `security/dependency-audit-baseline.json`

Comando mínimo do hook:

```bash
./vendor/bin/sail artisan security:audit-deps --lock-only --include-dev --fail-on=high
```

Objetivo do pre-commit:

- bloquear alterações perigosas de manifest/lock/policy cedo;
- manter tempo de execução curto e previsível;
- não depender de rede.

### Pre-push

Para reduzir falso negativo do pre-commit, o hook `pre-push` deve rodar:

```bash
./vendor/bin/sail artisan security:audit-deps --scan-vendor --include-dev --fail-on=high
./vendor/bin/sail composer audit
```

Se `vendor/` não existir ou estiver desatualizado, o comando deve falhar com instrução clara.

## CI gratuita obrigatória

No CI, a política mínima é:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
php artisan security:audit-deps --scan-vendor --include-dev --format=json --fail-on=high
composer audit --locked
```

Ferramentas gratuitas/open source recomendadas, fora do comando Artisan:

- `composer audit`
- `osv-scanner`
- `trivy fs`

Elas complementam o scanner local com advisories e visão externa, mas **não substituem** o comando.

## Workflow operacional

1. Atualizou ou adicionou package.
2. Rodou `./vendor/bin/sail composer update vendor/package --with-all-dependencies`.
3. Revisou diff de `composer.json` e `composer.lock`.
4. Passou no `pre-commit`.
5. Passou no `pre-push`.
6. CI revalida offline + advisories.

## Critérios de aceitação da implementação futura

1. Existe comando `security:audit-deps` com modos `--lock-only` e `--scan-vendor`.
2. Existe policy versionada em `config/dependency_audit.php`.
3. Existe baseline versionada com expiração.
4. O hook `pre-commit` bloqueia findings `high+` ao alterar manifest/lock/policy.
5. O hook `pre-push` adiciona `--scan-vendor` + `composer audit`.
6. O comando tem testes unitários/feature cobrindo plugins, `autoload.files`, providers,
   scripts perigosos, drift lock/vendor e baseline.
7. A documentação do repo ensina o fluxo via Sail.

## Referências externas que motivam esta spec

- Incidente `laravel-lang/*` (2026): tags comprometidas + `autoload.files` + payload automático.
- Composer docs: plugins, scripts, config, vendor binaries, schema.
- Laravel docs: package discovery / service providers.

O contrato desta spec é genérico: vale para `laravel-lang` **e para qualquer outro package**.
