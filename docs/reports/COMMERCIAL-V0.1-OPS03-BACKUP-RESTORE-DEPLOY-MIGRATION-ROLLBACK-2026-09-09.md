# COMMERCIAL V0.1 — OPS-03 Backup, Restore, Deploy, Migration and Rollback Rehearsal

Data: 2026-09-09  
Escopo: rehearsal descartável; nenhuma operação em produção real.

## 1. Baseline

- Branch: `main`
- HEAD no início do rehearsal: `655c939` (`feat(ops): add rehearsal backup restore and deploy guards`)
- Commits OPS-02 relevantes: `4e69cbc`, `4019b9c`, `b045025`
- Docker client/server: `29.2.1`
- Docker Compose: `v5.0.2`
- PHP image: `php:8.4-fpm-bookworm`
- MySQL image: `mysql:8.4` (custom init image)
- Caddy image: `caddy:2.9-alpine`
- Migrations: `73`
- `composer.lock` SHA-256: `f0bb8e6e0a85b488fd7ee5cea657613d1b2897c881a214d45bd9c3cbc8eb57df`
- Env contract: `/tmp/ead2026-ops03.env`, descartável, modo `0600`, fora do Git; nenhum valor secreto registrado.
- Compose project: `ead2026-ops03`
- Network privada: `ead2026-ops03_private` (`internal=true`)
- Network edge: `ead2026-ops03_edge`
- Portas da rehearsal: HTTP `18099`, HTTPS `18443`; DB sem porta publicada.
- Volumes exclusivos: `ead2026-ops03-db`, `ead2026-ops03-storage`, `ead2026-ops03-cache`, `ead2026-ops03-caddy-data`, `ead2026-ops03-caddy-config`.

## 2. Rehearsal Environment

Guardes exigiram `OPS03_REHEARSAL=true`, projeto contendo `ops03`, database contendo `ops03` e confirmação explícita para destruição. `APP_ENV=rehearsal`, `APP_DEBUG=false`, URL HTTPS local, credenciais sintéticas e nenhum segredo real de cliente foram usados.

## 3. Build

`PASS`. As imagens finais foram construídas com `APP_BUILD_SHA=655c939`:

- app: `sha256:2737fec22ec76a96006a3ab700c1bcfcd14796dcfbcce7dbd45a116a74404062`
- web: `sha256:ddf6ad9aa1cdfe39c67656aca5af1b3e41a609a2b1db351b4b603922a1a07e21`
- mysql: `sha256:0d1df6b4cb09e8538b524c43b40fbb6e70b7745adfd0b289b6809e1790c4f26a`

Composer instalou pelo lockfile sem dev dependencies de teste/Scribe no runtime; o canário confirmou ausência de `vendor/pestphp`, `vendor/knuckles/scribe` e `config/scribe.php`. O processo é PHP-FPM, não `artisan serve`; source não está bind-mounted. Mailpit não existe na topologia.

## 4. DB Privileges

`PASS` para o contrato de rehearsal:

- runtime user: SELECT, INSERT, UPDATE e DELETE verificados em canário; `DROP DATABASE`, `CREATE DATABASE` e CREATE TABLE falharam com exit code não zero.
- migration user: executou as migrations da release; grant registrado sem senha: `ALL PRIVILEGES` somente em `ead2026_ops03`.
- runtime grant registrado sem senha: `SELECT, INSERT, UPDATE, DELETE` somente em `ead2026_ops03`.
- nenhum usuário da aplicação é root.

## 5. Storage Persistence

`STORAGE_PERSISTENCE_VERIFIED`. Um arquivo canário foi escrito via Laravel Storage no volume privado, lido, o app foi recriado e o conteúdo permaneceu. `temporaryUrl` do adapter local foi suportada. Um usuário de tenant estrangeiro obteve lista vazia e `404 not_found` ao tentar baixar o material do tenant original.

## 6. Fresh Bootstrap

DB vazio foi inicializado; migrations de `database/migrations` e dos cinco módulos foram executadas com a credencial de migration. Resultado: `73/73`. O provisioning mínimo criou tenant, Admin, Instructor e Student sintéticos.

## 7. Golden Dataset

Dataset sintético discriminante, sem PII real: tenant `1`; users Admin/Instructor/Student; Course `1`; Module `1`; Lesson `1` com `content`; LessonMedia `1`; CourseMaterial `1` em storage privado; Enrollment `1`; LessonProgress `1` em 40%; e financial mirror/order `1` determinístico. O dataset foi preservado no backup e encontrado após restore.

## 8. Backup

`BACKUP_VERIFIED`.

Manifest válido: `/tmp/ead2026-ops03-backups/20260909T125405Z-2075/manifest.txt`

- backup id: `20260909T125405Z-2075`
- timestamp: `2026-09-09T12:54:06Z`
- RC SHA: `655c939`
- migrations: `73`
- DB payload SHA-256: `e17416265180b1bb46e27bd787c9ce8e129bba9ad7c6073b678325cea6713639`
- storage payload SHA-256: `5a86fdccb3403b444a596b2c1b6a380609a25d37b0a68ef56b9c0c1e324b33e2`
- destino: `/tmp`, separado dos volumes destruídos.

## 9. Backup Failure Canary

`PASS`. Com o DB parado, o dump retornou exit code `1`; o backup gerou manifest `FAIL`, sem payload parcial válido, e não foi promovido a backup PASS. O arquivo de falha registrou `database dump command failed` sem segredo.

## 10. Controlled Destruction

`PASS`. Foram parados os serviços e removidos somente os volumes explícitos `ead2026-ops03-db`, `ead2026-ops03-storage` e `ead2026-ops03-cache`, sob os guardes de rehearsal. O backup permaneceu fora desses volumes. `docker volume inspect` confirmou a ausência pós-destruição.

## 11. Restore

`RESTORE_VERIFIED`. O DB e o storage foram recriados a partir do manifest PASS; checksum foi validado antes da restauração. Após correção de espera por health do MySQL e do pipeline de senha do import, o restore terminou com `restore=PASS`.

## 12. HTTP Smoke

`PASS` após restore e deploy final via HTTPS real em `https://localhost:18443`:

- auth Student/Admin/Instructor: `200/200/200`
- `/up`: `200`
- Student My Courses: `200`
- Student Course: `200`
- Student Lesson/content/media/progress: `200`
- Student Material: `200`
- Instructor roster/progress: `200`; LessonProgress retornado em `40%`
- Admin Course: `200`
- foreign tenant material list/download: `404/404`, erro `not_found`

## 13. RPO/RTO Measurement

- backup: aproximadamente `1s` de wall-clock na segunda execução, com payload já disponível; a primeira execução incluiu pull do helper Alpine.
- restore DB + storage: `3s` medidos.
- recovery cold da stack final: aproximadamente `31s` na recriação completa com dependência de health do DB; com DB já saudável, o app respondeu imediatamente no ensaio final.
- comparação operacional: os tempos observados são plausíveis frente à proposta de RPO `≤24h` e RTO `≤4h úteis`, mas não constituem SLA.

## 14. Migration Rehearsal

`MIGRATION_REHEARSAL_FAILED`. O bootstrap de 73 migrations e o comando de migration no clone restaurado passaram sem migrations pendentes. Porém, `b045025` e `655c939` possuem as mesmas 73 migrations, e o build exato do N-1 falhou por `ext-exif` ausente; portanto a transição executável N-1→RC não foi comprovada.

## 15. N-1 → RC

`N-1=b045025` e `RC=655c939`, ambos commits reais. O inventário confirmou `73` migrations em ambos. O build exato do N-1 foi tentado em worktree descartável e falhou por requisito de `ext-exif` no Composer. Não houve promoção falsa desse ensaio como upgrade concluído.

## 16. Code Rollback

`ROLLBACK_NOT_READY`. A troca para a imagem previamente construída com tag `b045025` foi simulada; o app respondeu `500` no `/up` e no smoke Student. Como o build exato de `b045025` também falha, compatibilidade de rollback para o N-1 real permanece não comprovada.

## 17. Data Recovery

`PASS` para o caminho de recuperação ensaiado: stop traffic, destruição controlada, restore íntegro de DB/storage, boot da app, readiness e smoke HTTP. Não foi executado rollback destrutivo de migration; a inconsistência foi simulada pelo caminho seguro de restauração pré-release.

## 18. Deploy Runbook

`PASS` para o RC. `scripts/ops/ops03-deploy.sh` validou backup PASS, iniciou serviços, limpou caches e executou os seis caminhos de migration com credencial separada; todos retornaram `Nothing to migrate`, e o script terminou `deploy=PASS sha=655c939`.

## 19. Readiness

`PASS`. O script validou DB/app/scheduler em execução, volume privado existente e gravável e `migrations=73`. DB parado e volume de storage inexistente produziram exit code `1`.

## 20. Scheduler

`PASS`. O processo do container é `php artisan schedule:work`; `schedule:list` expôs `financial:drain-order-paid-outbox`, o comando executou com `0/0`, comando inexistente retornou exit code `1`, e o scheduler permaneceu running após restart.

## 21. Security

HTTPS interno ativo; debug off; DB e PHP-FPM sem portas públicas; storage privado; source sem bind mount; runtime user limitado; migration user separado; nenhum segredo foi adicionado ao Git ou ao relatório; Caddy proxyu corretamente após fixar o root PHP-FPM para `/var/www/html/public`.

## 22. Logs

App, Caddy, scheduler e MySQL foram inspecionados. Não foram encontrados tokens, Authorization headers, APP_KEY, DB password ou signed URLs. Houve um warning genérico de `password` em logs históricos de canário MySQL, sem valor secreto; o healthcheck/init final foi ajustado para usar `MYSQL_PWD` e evitar esse padrão em runtime.

## 23. Negative Canaries

Todos os canários obrigatórios executados:

1. backup com DB down: falhou e marcou `FAIL`;
2. runtime DROP/CREATE DB e DDL: falharam;
3. readiness DB down: falhou;
4. readiness storage inexistente: falhou;
5. migration path com runtime user: falhou, e o runbook usa migration user separado;
6. restore com storage checksum adulterado: falhou antes de tocar o alvo;
7. wrong environment no destroy: falhou antes de executar Docker.

## 24. Functional Regression

Smoke comercial pós-restore/deploy passou em MZRT provisioning + entitlements (`201/200`), auth/tenant context, Admin, Instructor, Student, course, lesson content/media, material, progress e isolamento cross-tenant. `git diff --check` passou. A regressão de rollback N-1 falhou e permanece blocker; não reabriu produto.

## 25. Commits

Commit funcional local de encerramento: `887ac94` (`feat(ops): rehearse backup restore deploy and rollback`), contendo as correções e automação. O checkpoint documental segue localmente; não houve push nem tag de release.

## 26. Remaining Blockers

- corrigir/reconstruir o N-1 real com `ext-exif` e repetir N-1→RC;
- obter rollback de código N-1 real com smoke HTTP verde;
- revisar a descoberta padrão de migrations: o runbook agora explicita os seis caminhos, pois o comando default reportou somente as oito migrations base;
- OPS-04: monitoring/error alerting;
- paid pilot ainda requer synthetic tenant/pilot formal, domínio/TLS real, secrets reais e aceite humano final.

## 27. Verdicts

- Backup: `BACKUP_VERIFIED`
- Restore: `RESTORE_VERIFIED`
- Migration: `MIGRATION_REHEARSAL_FAILED`
- Deploy: `DEPLOY_REHEARSAL_VERIFIED`
- Rollback: `ROLLBACK_NOT_READY`
- Storage persistence: `STORAGE_PERSISTENCE_VERIFIED`
- Operations: `OPERATIONS_NOT_READY`
- Paid Pilot: `NOT_READY`
