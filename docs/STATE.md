# State — Sessão Atual

> Efêmero: handoff e próximos passos. Status fino permanece em `docs/specs/*/tasks.md`.

## Sessão

- Preset gratuito de gateway implementado: `tenant:provision` usa contrato público do Ecosystem
  para criar idempotentemente plugin `cash` (`gateway.cash`), activation e config cifrada/habilitada;
  reexecução preserva desativação/config do Admin. Adapter Financial sem credenciais retorna
  cobrança `pending` para confirmação manual. Sem Cashier; billing Mzrt permanece separado.
- Evidência verde: Cash adapter (3 testes/12 asserts), TenantProvisionCommand (11/62),
  TenantGatewayResolver (7/14), Architecture (17/68), PHPStan sem erros e Insights acima dos gates
  com 0 advisories. Specs/ADR-005 alinhados a area-first; status fino em `40-financial/tasks.md` e
  `50-ecosystem-plugins/tasks.md`.

## Próximos passos (1-3)

1. Implementar endpoint Admin area-first para listar/configurar/ativar gateways do tenant, validando
   schema na persistência e nunca expondo segredos.
2. Depois: confirmação manual idempotente de pagamentos offline → Student checkout → webhook.
3. Retropreencher `PluginInstallation`/`PlatformOrder` de valor zero para presets `cash` quando o
   marketplace/ledger Plataforma existirem.

## Decisões abertas

- Nenhuma: `cash` é preset free; gateways adicionais podem ser free/pagos; sem Cashier; ledger
  Mzrt→tenant é independente do ledger tenant→aluno.

## Último commit

- `1d7f8e7` em `main`, pushed. Slice `cash` commitado; `qa:gate` verde (434 testes / 1889 asserts).
