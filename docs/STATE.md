# State — Sessão Atual

## Sessão

2026-09-09: OPS-03 executado exclusivamente na rehearsal Docker descartável `ead2026-ops03`.
Backup, restore DB+storage, deploy RC, readiness, scheduler, storage persistence e smoke HTTPS
foram executados. Relatório:
`docs/reports/COMMERCIAL-V0.1-OPS03-BACKUP-RESTORE-DEPLOY-MIGRATION-ROLLBACK-2026-09-09.md`.

## Próximos passos (1-3)

1. Corrigir/reconstruir o N-1 real `b045025` com `ext-exif` e repetir o ensaio N-1 → RC.
2. Repetir code rollback com a imagem N-1 exata e obter smoke HTTP verde; depois tratar OPS-04.
3. Manter Paid Pilot como `NOT_READY` até monitoring/error alerting, synthetic pilot formal,
   domínio/TLS e secrets reais e aceite humano.

## Decisões abertas

O build exato de `b045025` falhou por `ext-exif` ausente; a imagem anterior disponível retornou
500 no rollback smoke. Portanto `MIGRATION_REHEARSAL_FAILED`, `ROLLBACK_NOT_READY` e
`OPERATIONS_NOT_READY` são os veredictos atuais. Hosting real, domínio/DNS, provider de
backup/storage, SMTP e decisão `MediaProvider` permanecem abertos.

## Último commit

HEAD local: `901435e` em `main`, commit local do OPS-03; não houve push nem tag.

## Evidência atual

- Build final PASS: imagens app/web/mysql criadas com `APP_BUILD_SHA=655c939`; runtime PHP-FPM,
  sem Pest/Scribe e sem source bind-mounted.
- Fresh bootstrap e restore confirmaram `73/73` migrations, tenant/personas, Course/Module/Lesson
  content/media/material, Enrollment, progress e financial mirror.
- Backup PASS: manifest `20260909T125405Z-2075`, DB checksum
  `e17416265180b1bb46e27bd787c9ce8e129bba9ad7c6073b678325cea6713639`, storage checksum
  `5a86fdccb3403b444a596b2c1b6a380609a25d37b0a68ef56b9c0c1e324b33e2`.
- Backup failure, invalid restore checksum, wrong environment, runtime DDL, DB-down readiness e
  storage-missing readiness falharam fechados como esperado.
- Storage persistence sobreviveu à recriação do app; foreign tenant recebeu `404 not_found` no
  acesso ao material.
- HTTP HTTPS smoke final: auth `200/200/200`, MZRT provisioning/entitlements `201/200`, Student,
  Instructor e Admin `200`, `/up` `200`.
- Scheduler `schedule:work` running; outbox command `0/0`; falha de comando inexistente retornou
  exit code `1` e restart preservou configuração.
- N-1 → RC e code rollback exatos não foram confirmados; ver relatório para a evidência.
- `git diff --check` e `bash -n` dos scripts OPS-03 passaram; não houve alteração de capability
  funcional do produto.

## CONTEXT CHECKPOINT

- context: alto, mas com evidência OPS-03 consolidada no relatório.
- state: `docs/STATE.md` atualizado com fatos comprovados.
- recommendation: `waiting_for_user`.
- reason: OPS-03 terminou com blockers explícitos de N-1/rollback; próxima ação é uma decisão de
  priorização antes de avançar para a correção do release anterior ou OPS-04.
