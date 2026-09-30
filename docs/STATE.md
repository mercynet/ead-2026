# State — Sessão Atual

## Sessão

2026-09-30: entregues e publicados os adapters first-party Stripe, Mercado Pago, PagSeguro/PagBank e Asaas sobre o contrato agnóstico. A reconciliação Asaas agora consulta o estado autoritativo por `checkoutSession`; a inspeção do próximo slice confirmou que “PIX-nativo” ainda não tem provedor, biblioteca de BR Code/QR, credenciais, endpoint de emissão ou contrato de confirmação neste repositório. Mercado Pago usa Orders API em
cents convertido para decimal string, Bearer token, `X-Idempotency-Key`, redirect de Checkout Pro,
status normalizado, assinatura `x-signature` com `data.id`/`x-request-id` e webhook nativo que consulta o estado autoritativo da Order. PagBank usa Checkout API em cents, `x-idempotency-key`, link `PAY` e validação ECDSA/SHA-256 do `x-payload-signature`.
Asaas usa Checkout PIX com `access_token`, `externalReference`, valor decimal JSON sem `float`, link hospedado e webhook `CHECKOUT_*` autenticado por `asaas-access-token`, respondendo HTTP 200 conforme a exigência operacional do provedor.

## Próximos passos (1-3)

1. Definir provedor/API e contrato de confirmação do adapter first-party PIX-nativo; depois implementar o slice com documentação oficial e testes.
2. Executar E2E HTTP em stack dedicada `APP_ENV=e2e` quando disponível.
3. Revisar os 77 sinais de dependências abaixo do limite quando houver janela operacional.

## Decisões abertas

Stripe foi escolhido pelo ADR-001 como primeiro adapter; Stripe, Mercado Pago, PagSeguro/PagBank e Asaas estão
entregues e PIX-nativo continua pendente. Falta decidir se “PIX-nativo” significa um PSP específico (com
webhook/consulta autoritativa) ou um fluxo manual local (chave/BR Code, sem confirmação automática). Orders API/Checkout API foram escolhidos conforme a
documentação atual do provedor; a validação HTTP real ainda depende de stack dedicada; RPO/RTO, owners/canal de alerta,
backup remoto, host/domínio/TLS/secrets, rollback e promoção comercial continuam decisões
operacionais humanas.

## Último commit

abcb116 (main, enviado para origin/main) ancora este handoff após 22df273 e 581f352, que contêm os
adapters first-party, a reconciliação Asaas, hardening do runner/checkout/webhooks, migration/tests
financeiros e atualização do contrato de assinaturas de webhook. O working tree está limpo após o commit.

## Evidência atual

- Asaas + webhook focalizado: 23 passed (88 assertions); Financial ampliado: 135 passed (841 assertions).
- Architecture: 44 passed (1478 assertions); invariantes do diff: 5 arquivos de Architecture verdes; PHPStan: 0 errors; Pint: pass.
- `scripts/ai/verify-changes.sh`: invariantes do diff verdes no runtime Docker canônico; `git diff --check`: pass.
- Revisão source→sink: nenhum finding confirmado; push aceito por `origin/main`.
- E2E HTTP real continua sem stack dedicada; composer insights continua vermelho por findings
  legados. O hook de push reportou 77 sinais de dependências abaixo do limite, sem bloqueio.

## CONTEXT CHECKPOINT

- context: alto, estimado; handoff atualizado após commit/push e reconciliação Asaas.
- state: working tree limpo; Stripe, Mercado Pago, PagSeguro/PagBank e Asaas estão committed e publicados. E2E HTTP e PIX-nativo continuam pendentes.
- recommendation: waiting_for_user.
- reason: implementar PIX-nativo exige escolher provedor ou autorizar semântica manual local; o repositório não define essa decisão.
