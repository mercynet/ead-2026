# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- Auditoria completa do projeto (2026-07-28, skill validate-ai-work): claims de todos os `tasks.md`
  verificados contra código com evidência `arquivo:linha`. Nenhum claim "Done" refutado.
  Suites verdes: Architecture (17), Feature (396), Unit (14), E2E (2).
- Fixes do pós-auditoria:
  1. `guzzlehttp/guzzle` 7.14.0 → 7.15.x (4 advisories médios publicados 2026-07-20 derrubavam o
     `qa:gate` no PHP Insights; `composer audit --locked` limpo).
  2. `phpinsights.php`: `disable-security-check => true` — auditoria de dependências deixa de ser
     duplicada no gate; fonte única passa a ser `composer qa:deps` (`security:audit-deps` +
     `composer audit --locked`). Causa raiz do "CI falha sempre": qualquer advisory novo quebrava
     o gate independente do código.
  3. Gate/controller padronizados para `core.users.view` (canônico); `core.users.show` removido
     (nunca existiu em `config/permissions.php` — drift de nomenclatura, sem impacto de authz
     porque `UserPolicy::show` faz a checagem real).
- Revisão anterior (range `7407f45^..bc12782`): os 8 findings foram verificados contra o código
  real e corrigidos no commit `1a329eb` (ver histórico).

## Próximos passos (1-3)

1. Rodar `composer qa:gate` completo (migrations + PHPStan + Insights + suite) antes de considerar fechado.
2. Atualizar `docs/specs/10-core-identity/subspecs/users.md` mencionando a coluna `tenant_scope` e a
   garantia de unicidade global de email (documentar a decisão do índice virtual).
3. Retomar o roadmap do MVP pago (gateway/reembolso/reconciliação).

## Decisões abertas

- Nenhuma pendente deste lote. `--promote` em `tenant:provision` é a via explícita para escalada de
  papel de usuário existente.

## Último commit

- `1a329eb` em `main` (hardening dos 8 findings). Fixes pós-auditoria (guzzle bump, insights
  security-check, `core.users.view`) no working tree — commit a seguir.
