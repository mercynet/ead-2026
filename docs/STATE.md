# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- Auditoria segura consolidada em `docs/auditoria-e2e-http-2026-07-16.md`.
- Veredito: estratégia proposta é adequada, mas ambiente/runner atuais não cumprem isolamento,
  timeout, orçamento, circuit breaker, cleanup fatal e sanitização; HTTP mutante recebeu NO-GO.
- Inventário atual: 65 rotas `/api/v1`; 10 specs HTTP/55 casos, não 57.
- E2E-002 e E2E-003 estão resolvidos em `a99026b`; E2E-001 é condicional ao `APP_URL` no Sail.
- Stack trace com `APP_DEBUG=true` não foi classificada como vulnerabilidade. Reteste debug=false ficou
  bloqueado por falta de stack E2E exclusiva.
- Troca autenticada de senha mantém tokens, mas spec não define política; password reset do working
  tree revoga todos. Não tratar divergência contratual como vulnerabilidade automática.
- Architecture/Feature não foram confirmados nesta rodada: processos concorrentes disputaram o banco
  `testing`. Resíduos sintéticos conferidos: 0 tenants e 0 users com prefixo E2E.
- Working tree contém implementação de password reset e outros arquivos de sessão externa; auditoria
  não os alterou.

## Próximos passos (1-3)

1. **Stack E2E Sail exclusiva** (`APP_ENV=e2e`, DB novo/identificável, sem Pest concorrente); então
   rodar Architecture/Feature isolado (gate limpo) e a bateria HTTP mutante com `APP_DEBUG=false`.
2. **Operação de produção:** deploy/rollback, backup/restore, mail/worker/storage, observabilidade
   (requer contexto de infra).
3. **Gestão de convites (opcional):** listar pendentes / revogar / reenviar — só se o piloto exigir.

> Concluídos nesta sessão (eram passos 1-2 do snapshot anterior): política de sessão na troca de
> senha e endurecimento do `E2eRunCommand` (gate ambiente, timeout, circuit breaker, cleanup,
> sanitização — escopo proporcional). SEC-001 (bucket de throttle) corrigido com limiters nomeados.

## Decisões abertas

- ~~Política de sessões na troca autenticada de senha~~ **resolvida**: revoga as **outras**
  (mantém a atual); reset por token revoga todas. Implementado + na spec (`subspecs/auth.md`).
- Autorizar criação da stack E2E dedicada (`APP_ENV=e2e`, DB novo) para a próxima bateria HTTP
  mutante e o reteste com `APP_DEBUG=false`.

## Último commit

- `1925db7` em `main` (pushed). Fatias desta sessão, todas commitadas/pushed:
  `7407f45` invite-only + provisioning · `16cfcc1` identidade tenant-scoped ·
  `a99026b` fix specs e2e-http + contrato progresso · `fcf091b` password reset ·
  `1925db7` limiters nomeados + política de sessão + runner endurecido.
- STATE e o relatório de auditoria estão agora commitados (antes o snapshot dizia `a99026b`/não commitado).
