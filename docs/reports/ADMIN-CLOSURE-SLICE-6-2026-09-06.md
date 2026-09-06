# Admin Closure Slice 6 — Matrícula e confirmação manual — 2026-09-06

**Capability:** `ADM-03` — operação de matrícula Admin e jornada cash/manual.
**Verdict:** `ADMIN_ENROLLMENT_CONVERGED`
**Evidence:** `TEST_VERIFIED` + `RUNTIME_VERIFIED`

## Entrega

- Nova superfície canônica `GET/POST/GET/PATCH/DELETE /api/v1/admin/enrollments`.
- Controller Admin fino, reutilizando Actions de matrícula já tenant-scoped e Gates canônicos.
- `StoreAdminEnrollmentRequest` proíbe redefinição de tenant, status, billing e ownership.
- Matrícula Admin manual mantém `created_by_instructor_id = null`, status `active` e espelho
  financeiro zero-consideration idempotente.
- Confirmação `POST /api/v1/admin/orders/{id}/confirm-manual-payment` permaneceu na stack Admin
  existente: cash autoritativo, transação, outbox `OrderPaid` e listener de matrícula.

## Evidência

- RED: novo teste focal falhou com 404 antes da rota.
- GREEN: `AdminEnrollmentApiTest` — **4 passed (21 assertions)**.
- Financial focal — **25 passed (229 assertions)**, incluindo replay sem duplicar payment, outbox,
  auditoria ou matrícula.
- Learning completo — **289 passed (1.460 assertions)**.
- Architecture — **22 passed (709 assertions)**.
- PHPStan (`--memory-limit=1G`) — sem erros.
- Scribe — exit 0; as cinco rotas Admin de matrícula foram geradas/documentadas.
- E2E HTTP real em `ead2026_e2e` — **6/6**: criação Admin, confirmação cash/manual, outbox,
  matrícula, replay idempotente, isolamento tenant e personas negativas.

## Fora do slice

Matrícula externa (`billing_type=external`), webhooks, automação de gateway e qualquer decisão de
MediaProvider, quiz avançado ou lifecycle de plugins permanecem fora do escopo.

## Estado git

Alterações permanecem no working tree de `main`; nenhum commit, push ou merge foi realizado.
