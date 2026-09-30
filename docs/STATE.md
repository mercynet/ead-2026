# State — Sessão Atual

## Sessão

2026-09-30: entregues os adapters first-party Stripe, Mercado Pago, PagSeguro/PagBank e Asaas sobre o contrato agnóstico. A inspeção do próximo slice confirmou que “PIX-nativo” ainda não tem provedor, biblioteca de BR Code/QR, credenciais, endpoint de emissão ou contrato de confirmação neste repositório. Mercado Pago usa Orders API em
cents convertido para decimal string, Bearer token, `X-Idempotency-Key`, redirect de Checkout Pro,
status normalizado, assinatura `x-signature` com `data.id`/`x-request-id` e webhook nativo que consulta o estado autoritativo da Order. PagBank usa Checkout API em cents, `x-idempotency-key`, link `PAY` e validação ECDSA/SHA-256 do `x-payload-signature`.
Asaas usa Checkout PIX com `access_token`, `externalReference`, valor decimal JSON sem `float`, link hospedado e webhook `CHECKOUT_*` autenticado por `asaas-access-token`, respondendo HTTP 200 conforme a exigência operacional do provedor.

## Próximos passos (1-3)

1. Definir provedor/API e contrato de confirmação do adapter first-party PIX-nativo; depois implementar o slice com documentação oficial e testes.
2. Mediante pedido explícito, criar commit/push das correções atuais.
3. Executar E2E HTTP em stack dedicada `APP_ENV=e2e` quando disponível.

## Decisões abertas

Stripe foi escolhido pelo ADR-001 como primeiro adapter; Stripe, Mercado Pago, PagSeguro/PagBank e Asaas estão
entregues e PIX-nativo continua pendente. Falta decidir se “PIX-nativo” significa um PSP específico (com
webhook/consulta autoritativa) ou um fluxo manual local (chave/BR Code, sem confirmação automática). Orders API/Checkout API foram escolhidos conforme a
documentação atual do provedor; a validação HTTP real ainda depende de stack dedicada; RPO/RTO, owners/canal de alerta,
backup remoto, host/domínio/TLS/secrets, rollback e promoção comercial continuam decisões
operacionais humanas.

## Último commit

b9d4055 (main, publicado em origin/main) contém o hardening anterior. Há alterações locais
não commitadas no runner, checkout, migration/tests financeiros, tradução das exceções, adapters Stripe,
Mercado Pago e PagSeguro e o contexto/normalização de assinaturas de webhook.

## Evidência atual

- Asaas unitário pós-hardening: 7 passed (15 assertions); a bateria financeira ampliada pós-Asaas soma 90 passed (555 assertions); PagSeguro + manager + webhook focalizado anterior: 23 passed (113 assertions).
- Architecture atual: 44 passed (1478 assertions); invariantes do diff atuais: 5 arquivos de Architecture verdes; PHPStan: 0 errors; Pint: pass.
- Suíte completa anterior: 804 passed (6261 assertions), duração 662,92 s.
- `scripts/ai/verify-changes.sh`: invariantes do diff verdes no runtime Docker canônico.
- `git diff --check`: pass; revisão adversarial: nenhum finding confirmado.
- E2E HTTP real continua sem stack dedicada; composer insights continua vermelho por findings
  legados. As alterações permanecem somente no working tree; não há commit/push desta retomada.

## CONTEXT CHECKPOINT

- context: alto, estimado; handoff atualizado após a inspeção do slice PIX-nativo.
- state: working tree validado; Stripe, Mercado Pago, PagSeguro/PagBank e Asaas estão entregues localmente. Commit/push e E2E HTTP continuam pendentes.
- recommendation: waiting_for_user.
- reason: implementar PIX-nativo exige escolher provedor ou autorizar semântica manual local; o repositório não define essa decisão.
