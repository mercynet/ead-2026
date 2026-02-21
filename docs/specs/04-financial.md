# Domain Spec: Financial (API-First)

## Visão Geral
Domínio focado na monetização da plataforma. O Financial cuidará das intenções de compra (Orders), cálculos de itens no carrinho (OrderItems com suporte polimórfico) e transações confirmadas enviadas pelos gateways (Payments).

## 1. Regras Arquiteturais
- **Gateway Agnostic & Localization:** Hoje os modelos de `Payment` contêm o tipo do gateway (ex: Stripe, MercadoPago, Pagar.me). A API não deve acoplar a lógica de checkout no core. O Backend deve apenas gerar a `Order`, submeter a intenção via Factory pattern nativo do Laravel, e injetar Webhooks (via API pública) para receber os status do gateway. Exceções geradas pelos Gateways devem ser capturadas e traduzidas (`Localization`) para PT-BR — não repassar exceptions cruas em inglês para a tela do aluno.
- **Preços e Casas Decimais:** Valores devem transitar em API em inteiros (`price_cents`) para evitar floating math issues, ou, caso seja legada na base decimal, trafegar strict types decimais garantindo precisão `X.YY`.
- **Checkout Desacoplado:** Como haverá um SPA frontend, o Backend apenas enviará as intenções de pagamentos e receberá `client_secret` ou URLs de checkout redirect, que o frontend executará em IFRAME ou redirect.
- **Auditoria de Transações:** Toda matrícula, *mesmo em cursos gratuitos*, deverá originar um registro espelho na tabela financeira explicitando o método (ex: "Automático/Gratuito") para fins de consistência e metrificação de LTV/Auditoria nos Relatórios. Extrema atenção ao `RelationNotFoundException` ao carregar orders antigas, mantendo polimorfismo (`itemable`) sempre válido.

## 2. Entidades Principais
### Order (`Order`, `OrderItem`, `PriceHistory`)
- **Order:** Agregação de compra. Possui `order_number`, `subtotal`, taxas, `metadata` e um `origin_type` (Direct, Cart, Subscription, Renewal). Status: pending, paid, failed, cancelled, refunded.
- **OrderItem:** Linha da fatura. Extremamente flexível via polimorfismo (`itemable_type` e `itemable_id`), sendo capaz de apontar para um Curso, um Plano Fixo ou um Plugin do Tenant. Salva um `item_snapshot` (json) para manter o histórico caso o nome/preço do produto mude depois de anos.
- **PriceHistory:** Histórico de alterações de preço de cursos para auditoria.

### Payment (`Payment`, `TenantPaymentGateway`)
- **Payment:** Transação atrelada a uma `Order` e respondendo via Webhook. Contém Payload cru do Gateway em `gateway_response`, `external_id` do gateway. Status: pending, completed, failed.
- **TenantPaymentGateway:** Configuração de gateway de pagamento por tenant. Armazena credenciais cifradas para múltiplos gateways (Stripe, MercadoPago, Pagarme, etc).

### Carrinho e Cupons (Plugins)
- **Cart:** Carrinho de compras por usuário (sessão ou autenticado). Plugin gratuito incluído.
- **CartItem:** Itens do carrinho. Polimórfico (cursos, planos).
- **Coupon:** Cupons de desconto (percentual ou valor fixo). Validade configurável, limite de uso.

## 3. Endpoints Principais (JSON)
*Base URL: `api/v1/financial`*

### Checkouts (Authenticated)
- `POST /checkout`: Submete itens (Cursos/Planos). O Checkout pode tratar 1 item direto ou N itens caso o Inquilino utilize o **Plugin Free de Carrinho de Compras (Cart)**. A API calculará o preço em servidor, aplicará Cupons de Desconto se houver (futuro) e gerará a `Order` e `OrderItems`. Retornará o ID da `Order` e chave de sessão do Gateway Ativo do Tenant (`TenantPaymentGateway`).
- `GET /orders`: Lista o histórico financeiro do aluno.
- `GET /orders/{id}`: Detalhamento da compra em questão.

### Webhooks (Public)
- `POST /webhooks/gateway/{gateway_slug}`: Rota cega. O Gateway de pagamento pinga essa rota sinalizando que `PAY-XXXXX` mudou de status para `paid` ou `failed`. 
- Esse webhook joga um Objeto Laravel Job para Fila (Queue) e quem o processa de fato é um Worker (`ProcessPaymentWebhookJob`), que fará a virada de *Pending* para *Completed* na `Order` e vai disparar *Eventos de Domínio* `OrderPaidEvent`.
- **Event Driven:** Quando `OrderPaidEvent` for acionado, o domínio *Catalog/Learning* escuta de volta, e invoca o `EnrollService` (Matrícula automática). Isso garante total isolamento de código. Não teremos código de Matrícula espalhado dentro das rotas Financeiras da API.
