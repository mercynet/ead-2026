# State — Sessão Atual

> Efêmero: handoff e próximos passos. Reconstruído em 2026-07-16 sem usar versão anterior como
> evidência. Status fino permanece nos `docs/specs/*/tasks.md`.

## Sessão

- **Baseline restabelecido:** stack de containers no ar; suite (395 passed), Architecture (16),
  Larastan (0 erros) e Pint verdes contra o HEAD atual.
- **Fatia entregue — onboarding invite-only** (fecha o passo 3 e contém o cadastro público):
  - `POST /invitations` (admin, tenant-bound, token opaco só-hash, expiry, rate limit) +
    `POST /invitations/accept` (público; cria usuário/papel com tenant/email/role fixos, uso único,
    falha genérica `invitation_invalid`).
  - Cadastro público antigo (`POST /users`) **removido** — criação agora só via aceite de convite.
  - Cobertura: Feature (happy, 401, 403, 422, IDOR, replay, expiry, escalada) + E2E
    (convite → aceite → login → catálogo). Specs 10-core-identity atualizadas.
  - **Endurecimento pós-review:** aceite serializado sob `lockForUpdate()` (uso-único à prova de
    corrida) + guard de email ocupado entre emissão/aceite (falha genérica, sem 500); checagem de
    unicidade movida para a Action (após o Gate) — sem oráculo de enumeração de PII; renderer 429
    no envelope canônico; Resources tipados via `@mixin` (User agora com `@property`), removendo
    ~11 supressões PHPStan em vez de adicionar. Gate final: suite 397, Architecture 16, Larastan 0.
- **Veredito:** ainda não expor publicamente. Gargalo remanescente até receita: provisioning de
  tenant/primeiro admin, operação (deploy/rollback/backup) e evidência de release.
- Caminho comercial: piloto pago controlado, operação Mzrt assistida e cobrança manual.

## Inconsistências prioritárias confirmadas

- **Segurança:** `POST /api/v1/core/users` público cria student no tenant resolvido sem convite,
  aprovação ou throttle; tokens podem não expirar e troca de senha não revoga sessões.
- **Identidade/LGPD:** email/CPF são únicos globalmente apesar do contrato tenant-scoped;
  cadastro não persiste aceite/versionamento de termos.
- **Operação:** sem evidência atual de deploy, rollback, backup/restore, mail, worker, storage e
  observabilidade de produção. Container local parado impediu validar HEAD.
- **Produto:** não há provisioning de tenant/primeiro admin, convite ou recuperação de senha.
  Financial/Ecosystem têm fundações, mas nenhuma rota operacional; cobrança deve ficar manual.
- **API:** contrato area-first diverge de rotas domain-first atuais; não bloquear piloto com migração
  ampla. Admin area-first cobre principalmente curso, não operação de tenant/membros.
- **Docs:** ROADMAP chama Financial/Ecosystem de não iniciados; specs ainda carregam entidades
  anteriores ao ADR-005; `CHECKLIST-VERIFICACAO.md` e auditoria `*-pending.md` estão obsoletos/mistos;
  README/composer continuam skeleton Laravel.
- **Release:** CI existe, mas dependency audit usa `continue-on-error`; `tests/e2e-http/*` e geração
  Scribe ficam fora do `qa:gate`.

## Próximos passos (1-3)

1. **Dívida de schema tenant-scoped:** trocar `unique` global de `users.email`/`cpf` por compostos
   `(tenant_id, …)` + login tenant-scoped — hoje o convite valida email por tenant na app, mas o DB
   ainda é global (o aceite falha genericamente em vez de 500, mas dois tenants não podem compartilhar email).
2. **Operação de produção:** deploy/rollback, backup/restore, revogação de sessão/token, mail/worker/storage,
   observabilidade. Sem isso não expor publicamente.
3. **Endpoint de gestão de convites (opcional):** listar pendentes / revogar / reenviar. Só se o piloto
   exigir — o par create/accept + `tenant:provision` já cobrem o fluxo mínimo.

## Provisioning (concluído) — runbook

```bash
php artisan tenant:provision \
  --name="Escola Piloto" --domain=piloto.example.com \
  --admin-name="Nome Admin" --admin-email=admin@piloto.example.com
# senha omitida → gerada e exibida uma vez. Reexecução é idempotente (não duplica, não troca senha).
```

## Concluído nesta fatia (critério de saída atingido)

- ✅ Admin convida somente no próprio tenant (`tenant.access` 403 cross-tenant; Action fixa tenant do contexto).
- ✅ Request não troca tenant/role (tenant do contexto; role restrita a `student|instructor`).
- ✅ Token expirado/adulterado/reutilizado falha genericamente; aceite cria usuário/role → login.
- ✅ Testes cobrem happy, IDOR, replay, expiry, escalada; E2E cobre convite → login → catálogo.
- ✅ Cadastro público antigo removido — sem criação sem convite.

## Adiar

- Checkout B2C, gateways/webhooks, marketplace, painel admin amplo e provisioning self-service.
- Migração geral area-first, white-label avançado, RabbitMQ/microservices, analytics e app mobile.

## Decisões abertas

- Dono: autorizar política **invite-only** e escolher associação piloto. Branding mínimo pode ser
  manual no primeiro piloto; cobrança da licença permanece manual.

## Último commit

- `d7279b1` em `main`. Auditoria/STATE estão no working tree, sem commit/push.
