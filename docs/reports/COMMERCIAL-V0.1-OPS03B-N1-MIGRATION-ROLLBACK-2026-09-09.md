# COMMERCIAL V0.1 — OPS-03B: N-1, Migration Discovery e Code Rollback

Data: 2026-09-09
Escopo: compatibilidade N-1/RC, discovery determinístico de migrations e rollback de código.
Fora do escopo: monitoring, mudança de produto funcional e nova capability.

## 1. Baseline

O baseline inicial era `18456c0`, com os veredictos `MIGRATION_REHEARSAL_FAILED`,
`ROLLBACK_NOT_READY`, `OPERATIONS_NOT_READY` e Paid Pilot `NOT_READY`. Os receipts anteriores
já comprovavam backup, restore, deploy rehearsal, persistência de storage, readiness, scheduler,
least privilege do runtime e negative canaries.

A RC operacional reproduzível é `655c939`. Ela contém Dockerfile, compose, scripts operacionais e
o layout completo de 73 migrations. A implementação deste trabalho está em `4879457`.

## 2. N-1 Candidates

| Commit | Classificação | Evidência |
|---|---|---|
| `9533b50` | `NOT_DEPLOYABLE_HISTORICALLY` | Capability funcional, sem Dockerfile/infra suficientes |
| `4e69cbc` | `NOT_DEPLOYABLE_HISTORICALLY` | Infra presente, build exato falha por `ext-exif` |
| `4019b9c` | `NOT_A_RELEASE` | Alteração de documentação e contrato de infraestrutura |
| `b045025` | `NOT_DEPLOYABLE_HISTORICALLY` | Checkpoint documental; build exato falha por `ext-exif` |
| `655c939` | `VALID_RC` | Primeira release operacional executável comprovada |
| `887ac94` | `NOT_A_RELEASE` | Rehearsal posterior à RC |

Não existe `VALID_N_MINUS_1` anterior à primeira RC operacional. Portanto, não foi promovido
`HEAD~1` artificialmente a rollback target. O N-1 válido passa a existir a partir da próxima
release, quando `655c939`/a RC efetivamente publicada puder ser usado como release anterior.

## 3. ext-exif Root Cause

O build exato de `b045025` falha no estágio Composer/vendor com:

- `spatie/image 3.9.5 requires ext-exif *`;
- `spatie/laravel-medialibrary 11.23.2 requires ext-exif *`.

A exigência já estava no lockfile desse commit; não foi introduzida pela RC e não houve mudança
relevante de `config.platform` no Composer. O Dockerfile histórico instalava `exif` somente no
estágio runtime, depois de `composer install`; o estágio vendor não tinha a extensão habilitada.
Não há evidência de extensão implícita na imagem histórica. O Dockerfile atual instala `exif`
antes do Composer no estágio vendor e no runtime.

`--ignore-platform-req=ext-exif` não foi usado. A causa é incompatibilidade de build do artefato
histórico, não um problema a ser mascarado no Composer.

## 4. Build Reproducibility

Cada candidato foi construído em worktree descartável, com checkout exato, lockfile exato,
Dockerfile exato e sem alteração do commit:

| Commit | Resultado |
|---|---|
| `4e69cbc` | `FAILED` — Composer/vendor, `ext-exif` |
| `b045025` | `FAILED` — Composer/vendor, `ext-exif` |
| `655c939` | `PASS` — imagem app construída exatamente |

Conclusão: `b045025` é `NOT_DEPLOYABLE_HISTORICALLY`, e não um rollback target executável.

## 5. Previous Image 500

O artefato/tag identificado como `ead2026-ops03/app:b045025` não tinha proveniência confiável:
o label dizia `b045025`, mas as camadas mostravam Dockerfile de geração posterior. Portanto,
ele não provava rollback do commit `b045025`.

Na reprodução da configuração histórica do web, o Caddyfile não definia o root
`/var/www/html/public`; o upstream FPM recebeu `GET /index.php` e respondeu `404`. Isso torna a
imagem web histórica inválida como rollback operacional.

O receipt do `500` não deixou app log, Caddy log ou exception persistidos que permitam atribuir um
erro Laravel específico de schema, `APP_KEY`, cache ou storage. Na reprodução do stack, o tráfego
aberto antes de o app estar saudável produziu a sequência `000/500/200`. A causa fechável é,
portanto, artefato/proxy histórico inválido mais race de startup; não há base para declarar um
erro de schema. O compose foi endurecido com healthcheck do app e `web` aguardando
`service_healthy`; no rehearsal final o primeiro `/up` respondeu `200`.

## 6. Schema Compatibility

O inventário de migrations de `b045025` e `655c939` é idêntico: 73 arquivos, sem migration entre
eles. `Lesson.content` é `json nullable`, uma mudança
`ADDITIVE_BACKWARD_COMPATIBLE` introduzida antes desse intervalo. Não foram encontrados renames,
constraints removidas, backfills destrutivos ou migrations `UNKNOWN` entre os candidatos.

Classificação do intervalo:

- migrations novas: nenhuma;
- `ADDITIVE_BACKWARD_COMPATIBLE`: `Lesson.content` (já presente em ambos);
- `BACKWARD_INCOMPATIBLE`: nenhuma;
- `DATA_DESTRUCTIVE`: nenhuma;
- `UNKNOWN`: nenhuma.

O schema RC seria compatível com o código de `b045025` em teoria, mas isso não transforma o
artefato histórico não construível em N-1 válido.

## 7. Migration Discovery

O comportamento observado era inseguro: `migrate` default encontrou apenas as 8 migrations em
`database/migrations`, embora o produto tivesse 73. O Laravel combina o path default com os paths
registrados no migrator; os cinco Service Providers modulares registram seus diretórios via
`loadMigrationsFrom`.

Na imagem/configuração histórica, os providers `App\\Modules\\...` não estavam carregados e
`Migrator paths=[]`; por isso o default encontrou somente as 8 base. Na RC atual, os cinco
providers estão registrados e os 73 são descobertos. A causa foi bootstrap/provider discovery
incompleto no artefato histórico, agravado pela ausência de uma verificação de manifest no runbook.

Veredicto: `MIGRATION_DISCOVERY_HARDENED`.

## 8. Canonical Migration Contract

O comando canônico é:

```bash
php artisan ops:migrate --force --no-interaction
```

O comando deriva os paths do migrator/framework e do diretório modular real, valida que todo
diretório `app/Modules/*/Database/Migrations` está registrado, carrega o manifest da release,
compara nomes/count e valida migrations aplicadas antes e depois. Não há lista manual de seis
`--path`.

Opções operacionais adicionais:

- `--manifest-only`: valida somente o manifest empacotado;
- `--check-only`: exige schema já exatamente alinhado;
- `--write-manifest`: usado no build para gerar `release/migrations.manifest.json` a partir da
  descoberta efetiva;
- `--manifest=<path>`: permite apontar um manifest explícito.

## 9. Manifest Gate

O gate de build/deploy é derivado do release manifest, não de `73` hardcoded:

```text
expected=73 discovered=73 applied_before=73 applied_after=73 migration=PASS
```

No build final, o manifest foi gerado com 73 nomes descobertos. No deploy rehearsal, o comando
canônico passou com `expected=73`, `discovered=73` e `applied_after=73`. O deploy só sobe scheduler
e web depois desse gate; divergência aborta antes de abrir tráfego.

Resultado: `PASS`.

## 10. RED Evidence

`RED_CONFIRMED=YES`.

- A: reprodução histórica mostrou default discovery `8` e paths modulares ausentes; a regressão
  discriminante está em `OpsMigrateCommandTest`.
- B: manifest com um migration ausente falha com `MIGRATION_DISCOVERY_UNSAFE`.
- C: row aplicada fora do manifest falha com `MIGRATION_SCHEMA_UNSAFE`.
- D: manifest e schema exatamente alinhados passam.

Os quatro testes da ferramenta passaram: `4 passed (11 assertions)`. B e C são canaries
negativos deliberados e permanecem como provas de abort.

## 11. N-1 → RC

Veredicto: `N_MINUS_1_TO_RC_NOT_APPLICABLE_FIRST_RELEASE`.

Não foi fabricado um rehearsal de upgrade usando `b045025`, porque não existe N-1
production-grade executável anterior à primeira RC operacional. A RC em si foi construída,
deployada com o gate canônico, validada por readiness, smoke HTTP comercial e preservação de
checksums/dados.

## 12. Code Rollback

Veredicto: `CODE_ROLLBACK_REQUIRES_RESTORE`.

O código anterior mais próximo não é um artefato reproduzível; logo não há code rollback histórico
verificado. A compatibilidade de schema, isoladamente, não resolve a inexistência do binário.

## 13. Restore Fallback

Veredicto: `ROLLBACK_VIA_RESTORE_VERIFIED`.

O fallback operacional comprovado é: backup pré-release, stop traffic, restore de DB/storage,
deploy do release compatível e smoke. O receipt anterior de restore continua válido; o rehearsal
OPS-03B também gerou backup `20260909T133721Z-14594`, com status `PASS`, e o deploy/readiness final
passaram. Este caminho é explicitamente restore rollback, não code rollback.

## 14. First Release Decision

Esta é a primeira release operacional real. A decisão aceitável para o primeiro deploy é recovery
por restore de baseline pré-deploy/synthetic, sem alegar que existe N-1 histórico. A partir da
próxima release, a RC anterior será o N-1 legítimo e deverá ser construída, subida e exercitada
como code rollback quando o schema permitir.

Essa decisão não autoriza o Paid Pilot enquanto os demais gates comerciais/operacionais não
estiverem fechados.

## 15. Regression

Executado:

- build exato da RC: `PASS`;
- manifest em imagem: `expected=73 discovered=73`;
- deploy rehearsal: `PASS`;
- readiness, scheduler e storage: `PASS`;
- backup: `PASS`;
- primeiro `/up` após healthcheck: HTTP `200`;
- tooling: `4 passed (11 assertions)`;
- infraestrutura + tooling: `9 passed (58 assertions)`;
- checkout comercial: `16 passed (183 assertions)`;
- S02 performance: `2 passed (8 assertions)`;
- `bash -n scripts/ops/*.sh`: `PASS`;
- `git diff --check`: `PASS`.

Uma execução paralela de dois testes funcionais falhou por corrida no banco compartilhado de
testing durante `RefreshDatabase`; após recriação do banco, as mesmas suites executadas
sequencialmente passaram. Isso foi classificado como falha do harness concorrente, não falha
funcional.

## 16. Remaining Blockers

- não existe code rollback N-1 histórico; o primeiro deploy depende do restore fallback;
- monitoring/alerting e demais gates operacionais não foram abertos por escopo;
- ainda faltam decisões/evidências de ambiente real, domínio/TLS/segredos e aceitação do piloto;
- OPS-04 não deve começar até os gates definidos pelo contrato estarem aprovados.

## 17. Verdicts

| Área | Veredicto |
|---|---|
| Migration discovery | `MIGRATION_DISCOVERY_HARDENED` |
| Migration rehearsal | `MIGRATION_REHEARSAL_NOT_APPLICABLE_FIRST_RELEASE` |
| Code rollback | `CODE_ROLLBACK_REQUIRES_RESTORE` |
| Rollback | `ROLLBACK_VIA_RESTORE_VERIFIED` |
| Operations | `OPERATIONS_NEAR_READY` |
| Paid Pilot | `NOT_READY` |

Commits relevantes:

- `4879457 feat(ops): harden migration discovery and rollback gates`;
- commit documental deste relatório e do handoff `docs/STATE.md`, criado ao selar esta task.
