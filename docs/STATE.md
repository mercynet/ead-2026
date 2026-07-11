# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 (noite) — **Validação independente do merge `harness/specs-foundation` + fixes CI
  (`78bf5ab..3794fdb`) concluída via `validate-ai-work`: APROVADO COM RESSALVAS.** Reproduzido
  verde: suite 333/1386, `composer analyse` (baseline 408 confirmado por soma de `count:`),
  `composer insights` exit 0 (thresholds 83/85/75/88), e2e `lessons-progress` 11/11 contra app
  real. **2 findings ALTA no fluxo novo de certificado** (confirmados no código): (1) certificado
  duplicável sob concorrência — check-then-create sem unique em `certificates(tenant_id,
  enrollment_id)` + enrollment lido sem lock em `UpdateProgressAction`; (2) listener de emissão
  síncrono disparado DENTRO da `DB::transaction` do progresso — exceção na emissão = 500 + rollback
  do progresso do aluno. Média: attempts `in_progress` legados sem backfill de snapshot ficam
  inutilizáveis; RBAC `own` de instructor em lessons/courses/modules não implementado (spec↔código
  divergem). Baixas registradas no relatório da sessão (colisão certificate_number, validação
  assimétrica de material path, access_days null→500, spec drift rbac.md, dedup de lógica de path).
  Nota operacional: `e2e:run` de dentro do container exige `APP_URL=http://localhost` (o `.env`
  aponta a porta 8099 do host).

## Próximos passos (1-3)

1. ~~Fix A1+A2~~ **feito e mergeado na main** (`35acbf0` → merge `7906e42`): unique
   `certificates(tenant_id, enrollment_id)` + catch da violação, enrollment `lockForUpdate` +
   count locking, eventos pós-commit, listener com try/catch+log. Validado: suite 335/1392,
   analyse verde (baseline 411), insights ok, e2e lessons-progress 11/11.
   **Workflow novo: solo dev — trabalhar direto na `main`, sem branches novas.**
2. **Decisão de escopo** (piloto gratuito vs MVP pago) + **decisão P1.6** (global scope de tenant —
   ADR via `create-adr`); a mesma fronteira decide o trigger complementar de certificado
   (`docs/specs/30-assessment/tasks.md`) e resolve a divergência RBAC `own` (implementar ownership
   ou corrigir `rbac.md`).
3. LGPD-03 (uniques tenant-scoped de cpf/email — hoje globais) + P2 da auditoria (P2.5
   `StartAttemptAction` 500 sem tenant confirmado; P2.4 abort(422) vs envelope) + ratchet de
   qualidade (pagar baseline PHPStan 411 via docblocks ide-helper; subir insights a 85/85/80/95).

## Decisões abertas

- **Escopo de lançamento:** piloto gratuito controlado ou MVP comercial pago?
- **P1.6:** trait `BelongsToTenant` com global scope vs `where` explícito + smoke test (ADR
  pendente) — 20 ocorrências de `where('tenant_id')` manual em app/ dependem dessa decisão.
- **RBAC `own` (instructor)**: implementar checagem de ownership em lessons/courses/modules ou
  rebaixar a matriz em `rbac.md` para `sim` tenant-wide (ADR curto).
- Caminho pago: gateway inicial, política de reembolso, modelo de reconciliação.
- Dívidas transversais nos `tasks.md`/roadmap: teto RBAC, LGPD-03, LGPD operacional, fronteiras
  cross-module.

## Último commit

- `7906e42` (merge `fix/certificate-concurrency` → main, pushado). Daqui em diante commits
  diretos na `main` (decisão do dono, solo dev).
