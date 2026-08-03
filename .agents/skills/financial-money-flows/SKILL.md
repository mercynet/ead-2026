---
name: financial-money-flows
description: Mexe em fluxo de dinheiro do módulo Financial — checkout, idempotência, máquina de estado do charge, adapter de gateway, confirmação manual, outbox OrderPaid, novo vendável (itemable), reembolso/reconciliação. Ativa ao tocar app/Modules/Financial, valor monetário, order/payment/ledger, gateway/PSP, ou ao ver 409 de checkout, 503 gateway_unavailable, matrícula que não aconteceu depois do pagamento. Não é skill de CRUD genérico — é das regras que o dinheiro impõe.
---

# Financial Money Flows

Dinheiro erra caro e erra calado: duplica cobrança, perde matrícula, deixa pagamento órfão.
Este módulo já resolveu isso com padrões específicos — **siga-os em vez de reinventar**.

Contrato: `AGENTS.md` → invariante 7 (cents) e 11 (cross-module). Porquê do desenho:
`docs/specs/00-architecture/decisions/003-billing-dois-ledgers-itemable-seam.md` (canônico) e
ADR-001/ADR-005. Estado fino: `docs/specs/40-financial/tasks.md`.

## 1. Valor sempre em cents inteiro

Coluna monetária = `integer`/`unsignedBigInteger` com sufixo `_cents`. `MoneyNeverFloatTest` varre as
migrations e reprova `float|double|decimal|unsignedDecimal` em qualquer coluna cujo nome case com
`price|amount|cost|fee|balance|subtotal|discount|total|cents`. Nada de arredondar em PHP: soma,
desconto e imposto são aritmética inteira. Formatação é problema do cliente da API.

## 2. Dois ledgers irmãos — nunca a mesma tabela

| Plano | Quem cobra quem | Tabelas | Escopo | Estado no repo |
|-------|-----------------|---------|--------|----------------|
| **Venda** | tenant → aluno | `orders` / `order_items` / `payments` | `tenant_id` | **implementado** |
| **Plataforma** | Mzrt → tenant | `platform_orders` / … | global (landlord) | **não implementado** (só `platform_payment_gateways` + `PlatformGatewayResolver`) |

Proibido: colapsar os dois num `orders` com discriminador de plano; consultar um ledger a partir do
controller do outro; usar `tenant_id` do plano Plataforma como eixo de isolamento (lá ele é **o
pagador**). Comissão de instrutor **não** é ledger — é repasse derivado de venda confirmada.

## 3. Novo vendável = morph map, zero migration

`OrderItem` aponta para o vendável por `itemable_type` + `itemable_id`, com `item_snapshot`
(cópia imutável do que foi vendido) e `price_cents`. O tipo é **string de morph map**, registrada no
provider do módulo dono (`Relation::morphMap(['course' => Course::class])` no
`LearningServiceProvider`) — é assim que Financial não importa model de outro módulo e o
`ModuleBoundaryTest` continua verde. Novo vendável: entrada no morph map (+ model se preciso).
**Nunca** FK dedicada (`course_id`) em `orders`/`order_items`.

Catálogo/elegibilidade vem por contrato, não por Eloquent cross-module:
`App\Modules\Learning\Contracts\CourseCheckoutCatalog` → `CourseCheckoutOffering`
(`priceCents`, `purchaseCycleKey`, `isEligible`, `snapshot`).

## 4. Idempotência do checkout (duas chaves, papéis distintos)

- `orders.idempotency_key` — chave **do cliente** (header/payload). Mesma chave + mesma compra =
  mesma order; mesma chave + compra diferente = `idempotency_conflict`.
- `orders.source_key` — `purchaseCycleKey` do offering: identifica **o ciclo de compra**. Segunda
  order ativa (`pending|paid`) para o mesmo ciclo = `checkout_already_exists`.
- `payments.psp_idempotency_key` — `sale-order:<order_number>`, o que vai para o PSP.

Todos os conflitos saem como `CheckoutConflictException` → **409** com código estável:
`idempotency_conflict`, `already_enrolled`, `checkout_already_exists`, `checkout_in_progress`,
`payment_reconciliation_required`. Códigos são contrato — não invente sinônimo.

Leitura sob `lockForUpdate()` dentro da transação é obrigatória em order/payment/items: é o que
impede duplo checkout em corrida.

## 5. Máquina de estado do charge (`PaymentChargeState`)

```
created ──claim──▶ processing ──resposta do PSP──▶ resolved
                        │
                        ├── falha antes de cobrar (release)  ──▶ created
                        ├── falha depois/durante a cobrança  ──▶ unknown
                        └── claim expirado (> 5 min)         ──▶ unknown
```

- **Claim** = `charge_state=processing` + `charge_claim_token` (uuid) + `charge_claimed_at`. Só quem
  tem o token grava o resultado (`ownsClaim()` compara token **e** identidade do gateway:
  `tenant_plugin_config_id`, `gateway_configuration_version`, `gateway_slug`, `psp_idempotency_key`).
- **`unknown` é terminal para a request**: significa "pode ter cobrado". Nunca "conserte" para
  `created`/`resolved` por conveniência — a saída é reconciliação
  (`payment_reconciliation_required`, 409).
- Erro de gateway (resolução ou cobrança) → `markUnknown()` + `GatewayUnavailableException` → **503**
  `gateway_unavailable`. Nunca vaze mensagem do PSP na resposta.
- Timeout do claim = 5 min (`PROCESSING_TIMEOUT_MINUTES`). Mudou? Mude a constante, não duplique.
- **Free** (`priceCents === 0`): order já nasce `paid`, payment `gateway_slug='free'`,
  `confirmation_mode=automatic`, `charge_state=resolved` — e **grava outbox** (o caminho de matrícula
  é o mesmo do pago).

## 6. Costura de gateway (adapter novo)

- `Gateways/Contracts/PaymentGatewayInterface` — adapter **stateless singleton**, agnóstico de ledger:
  `charge(array $credentials, ChargeIntent $intent): ChargeResult`, mais `identifier()`, `label()`,
  `confirmationMode()`, `configurationSchema()`, `validateConfiguration()`.
- `ChargeIntent` = intenção neutra (`amountCents` int, `currency` ISO minúsculo `'brl'`, `reference` =
  `order_number`, `idempotencyKey`, `description`, `metadata`). O adapter **nunca** vê model de ledger.
- `ResolvedGateway` = adapter + credenciais como unidade atômica; `->charge($intent)` injeta as
  credenciais certas. Nunca combine adapter de um gateway com config de outro tenant/versão.
- `PaymentGatewayManager::register()` no boot registra o adapter; `TenantGatewayResolver::resolve()`
  (entitlement + config vindos do Ecosystem por contrato) e `resolveExact()` (revalida a identidade
  exata gravada no payment); `PlatformGatewayResolver` resolve o gateway do Mzrt (global, sem
  entitlement).
- **Modo `manual`** (ex.: `CashPaymentGateway`): `charge()` só pode devolver `Pending`. Retorno
  diferente é bug do adapter — o Action rejeita.

Adapter novo = implementar o contrato + registrar no manager + schema de config + testes. Não leia
config cifrada no Action, não chame PSP fora do adapter.

## 7. Confirmação manual (dinheiro fora do PSP)

`Actions/Admin/ConfirmManualPaymentAction`: order `pending` com **exatamente um** payment
`gateway_slug='cash'` + `confirmation_mode='manual'` e `status='pending'` → payment `completed`,
`charge_state='resolved'`, order `paid`, outbox gravado. Order já `paid` é **idempotente**: revalida
que existe o pagamento manual confirmado e regrava o outbox (não erra, não duplica cobrança).
Violação de pré-condição = `ValidationException` (422), não 409.

## 8. Outbox — side effect cross-module nunca no mesmo passo

Padrão obrigatório para "pagou → outro módulo age":

1. **Dentro** da transação: `OrderPaidOutboxService::record($event)` — `firstOrCreate` por
   `order_id + event_type` (tolera colisão 23000), então gravar duas vezes é inofensivo.
2. **Fora** da transação: `publish($id)` — claim com token + lease de 5 min, `dispatch` do
   `OrderPaidEvent`, marca `dispatched_at`. Falha incrementa `attempt_count`, grava `last_failed_at` /
   `last_error_class` e **libera** o claim.
3. Falha de publish **não** derruba a request (log `warning` com `order_id`/`outbox_id`) — a corrida
   pendente é drenada depois: `OrderPaidOutboxService::drain()` via
   `app/Console/Commands/DrainOrderPaidOutboxCommand.php`.

Consumidor vive no outro módulo (`EnrollStudentFromOrderPaidListener` no Learning, registrado no
`LearningServiceProvider`). Financial **não** matricula ninguém; Learning **não** lê `orders`.
Evento novo = novo tipo no outbox, mesmo padrão record/publish/drain.

## Checklist

- [ ] Coluna monetária `_cents` inteira; nenhuma aritmética em float.
- [ ] Ledger correto (Venda × Plataforma); nada de discriminador de plano.
- [ ] Vendável por morph map + `item_snapshot`; sem FK nova em `orders`.
- [ ] Transação com `lockForUpdate()` em order/payment/items; conflito → código 409 existente.
- [ ] Transição de `charge_state` explícita; falha de PSP → `unknown` + 503, jamais 500 cru.
- [ ] Cobrança só via `ResolvedGateway`; adapter stateless e sem model de ledger.
- [ ] Side effect cross-module via outbox (record dentro, publish fora) + listener no outro módulo.
- [ ] Erro do PSP não aparece na resposta nem no log com credencial (skill `logging-security`).

## Verificar

```bash
./vendor/bin/sail artisan test --compact --filter=Checkout
./vendor/bin/sail artisan test --compact --filter=ManualPayment
./vendor/bin/sail artisan test --compact --filter=Outbox
./vendor/bin/sail artisan test --compact --testsuite=Architecture
```

Fluxo de dinheiro fecha com E2E HTTP real (`tests/e2e-http/`, skill `endpoint-e2e`): confira no banco
`orders.status`, `payments.charge_state`, `order_paid_outbox.dispatched_at` e a matrícula criada —
teste in-process verde não prova que o outbox drenou.

## Armadilhas específicas

- **Publicar evento dentro da transação**: se o commit falha depois do dispatch, o outro módulo já
  agiu. Sempre `record` dentro, `publish` fora.
- **Segunda cobrança "só pra garantir"**: repetir `charge()` sem `psp_idempotency_key` estável
  duplica no PSP.
- **Reusar `already_enrolled` para outro conflito**: cada código 409 tem significado de contrato; o
  cliente decide o que mostrar por ele.
- **Confiar em `payments.status` para saber se cobrou**: `status` é o resultado; quem diz se a
  cobrança foi tentada é `charge_state`.
