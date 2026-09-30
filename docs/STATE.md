# State — Sessão Atual

## Sessão

2026-09-30: entregues e publicados os adapters first-party Stripe, Mercado Pago, PagSeguro/PagBank e Asaas sobre o contrato agnóstico. A reconciliação Asaas agora consulta o estado autoritativo por `checkoutSession`; a retomada deixou PIX-nativo pendente por decisão de provedor/API e entregou os slices legados de Assessment para excluir questão, listar/anexar questões e revogar certificado na superfície Admin (`POST /api/v1/admin/certificates/{id}/revoke`), com isolamento de tenant e idempotência. Mercado Pago usa Orders API em
cents convertido para decimal string, Bearer token, `X-Idempotency-Key`, redirect de Checkout Pro,
status normalizado, assinatura `x-signature` com `data.id`/`x-request-id` e webhook nativo que consulta o estado autoritativo da Order. PagBank usa Checkout API em cents, `x-idempotency-key`, link `PAY` e validação ECDSA/SHA-256 do `x-payload-signature`.
Asaas usa Checkout PIX com `access_token`, `externalReference`, valor decimal JSON sem `float`, link hospedado e webhook `CHECKOUT_*` autenticado por `asaas-access-token`, respondendo HTTP 200 conforme a exigência operacional do provedor.

## Próximos passos (1-3)

1. Executar E2E HTTP em stack dedicada `APP_ENV=e2e` quando disponível para os slices de Assessment.
2. Escolher o próximo slice do Assessment sem transformar `assign/transfer` em implementação implícita; eventos de tentativa/certificado continuam pendentes.
3. Manter o adapter PIX-nativo pendente até haver decisão de provedor/API e contrato de confirmação; revisar os 77 sinais de dependências abaixo do limite quando houver janela operacional.

## Decisões abertas

Stripe foi escolhido pelo ADR-001 como primeiro adapter; Stripe, Mercado Pago, PagSeguro/PagBank e Asaas estão
entregues e PIX-nativo continua pendente. Falta decidir se “PIX-nativo” significa um PSP específico (com
webhook/consulta autoritativa) ou um fluxo manual local (chave/BR Code, sem confirmação automática). Orders API/Checkout API foram escolhidos conforme a
documentação atual do provedor; a validação HTTP real ainda depende de stack dedicada; RPO/RTO, owners/canal de alerta,
backup remoto, host/domínio/TLS/secrets, rollback e promoção comercial continuam decisões
operacionais humanas.

## Último commit

78043aa (main, enviado para origin/main) ancora este handoff após 22df273 e 581f352, que contêm os
adapters first-party, a reconciliação Asaas, hardening do runner/checkout/webhooks, migration/tests
financeiros e atualização do contrato de assinaturas de webhook. O working tree contém os slices locais
de Assessment e a atualização deste handoff; nada foi commitado.

## Evidência atual

- Asaas + webhook focalizado: 23 passed (88 assertions); Financial ampliado: 135 passed (841 assertions).
- Assessment `QuestionApiTest` + `QuestionnaireApiTest` + `AdminCertificateApiTest`: 23 passed (156 assertions); `CertificateApiTest`: 6 passed (22 assertions); PHPStan: 0 errors; Pint: pass.
- `scripts/ai/verify-changes.sh`: 6 arquivos de Architecture verdes no runtime Docker canônico; `git diff --check`: pass.
- Architecture pelo wrapper Sail: 43 passed (1422 assertions) e 1 falha ambiental de leitura; `ProductionInfrastructureContractTest` isolado via `docker exec` root: 6 passed (121 assertions).
- Rota nova confirmada por `route:list`: `POST api/v1/admin/certificates/{id}/revoke` com `auth:sanctum`, `area.guard:admin`, `api.context`, resolução/acesso de tenant.
- Specs E2E HTTP declarativos `tests/e2e-http/assessment/legacy-questionnaire-questions.php` e `admin-certificate-revoke.php`: sintaxe válida; execução recusada pelo runner em ambiente `local`, aguardando stack `APP_ENV=e2e` dedicada.
- Scribe reconheceu a rota Admin nova, mas não concluiu por ownership do cache `.scribe/endpoints.cache`; nenhum arquivo gerado rastreado foi alterado.
- Revisão source→sink das mudanças locais: nenhum finding confirmado; nenhuma mudança foi commitada ou enviada.
- E2E HTTP real continua sem stack dedicada; composer insights continua vermelho por findings
  legados. O hook de push reportou 77 sinais de dependências abaixo do limite, sem bloqueio.

## CONTEXT CHECKPOINT

- context: alto, estimado; handoff atualizado após quatro slices de Assessment.
- state: PIX-nativo continua pendente por decisão; os quatro slices locais de Assessment estão TEST_VERIFIED; E2E HTTP aguarda stack dedicada; working tree contém implementação e atualizações de tasks/spec/state, sem commit.
- recommendation: clear.
- reason: a fatia autônoma selecionada foi concluída e o handoff está fresco; retomar lendo AGENTS.md + docs/STATE.md antes de escolher o próximo backlog.
