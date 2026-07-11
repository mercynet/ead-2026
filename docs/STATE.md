# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 — **Emissão automática de certificado** implementada e pushada (`939f368`,
  `harness/specs-foundation`): `CourseCompletedEvent` (Learning, transição do enrollment para
  100% em `UpdateProgressAction`) → `IssueCertificateOnCourseCompletedListener` →
  `IssueCertificateAction` (Assessment), honrando `certificate_*` do curso, idempotente por
  enrollment. Fecha o restante do P1.3 da auditoria. Testes: 9 novos
  (`CertificateIssuanceTest`, `CourseCompletedEventTest`); Feature 307/307, Architecture 15/15.
  Gap registrado no `tasks.md` do Assessment: quiz aprovado **depois** do curso completo ainda
  não engatilha emissão (trigger complementar pendente).

## Próximos passos (1-3)

1. **Decisão de escopo** (piloto gratuito vs MVP pago) + **decisão P1.6** (global scope de tenant
   — discutir e registrar ADR via `create-adr`).
2. P2 da auditoria (recompra pós-cancelamento, vazamento de drafts na landing, slug único)
   e LGPD-03 (uniques tenant-scoped).
3. Assessment pendentes menores: trigger complementar de emissão (quiz por último),
   `CertificateIssuedEvent`, revoke, PDF — ver `docs/specs/30-assessment/tasks.md`.

## Decisões abertas

- **Escopo de lançamento:** piloto gratuito controlado ou MVP comercial pago?
- **P1.6:** adotar trait `BelongsToTenant` com global scope (+ `creating` hook) mantendo `where`
  explícito como defesa em profundidade, ou só ampliar `TenantIsolationSmokeTest`? (ADR pendente.)
- Caminho pago: gateway inicial, política de reembolso, modelo de reconciliação.
- Dívidas transversais nos `tasks.md`/roadmap: teto RBAC, LGPD-03, CI, LGPD operacional,
  PHPStan/advisories, fronteiras cross-module.

## Último commit

- `939f368` (emissão automática de certificado) — branch `harness/specs-foundation`,
  sincronizada com origin.
