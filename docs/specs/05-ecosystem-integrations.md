# Domain Spec: Ecosystem & Integrations (API-First)

## Visão Geral
O EAD opera em um modelo sofisticado de Plugins, cobrados como Assinaturas SaaS-like. A Landlord (empresa master, developer) detém controle de módulos pagos que são distribuídos para a base Multi-Tenancy (clientes). Este domínio rastreia a vitrine, as cobranças dos lojistas e o status dessas assinaturas dinâmicas.

## 1. Padrões Arquiteturais
- **Modelagem Fechada (First-party Only):** Não existe upload de plugins por terceiros. Os plugins (incluindo Mídia, Pagamentos, Analytics, Recompensas) residem fisicamente na pasta `app/Plugins/` do core da plataforma base e são desenvolvidos apenas pela própria empresa mantenedora (Master).
- **Billing Recorrente:** Diferente do Financial (Compra spot), as tabelas de Plugins interagem com lógicas recursivas (`PluginSubscription` e `PluginBilling`). Requer rotinas de Cron/Scheduler nativo do Laravel varrendo `due_date` ou integrações automáticas recorrentes (ex: Stripe Subscriptions). O Pagamento das assinaturas do Master para o Tenant roda em Gateways Master (ex: Stripe) definidos no código global, enquanto o tenant opera localmente seu Shopping Cart via Plugin.
- **Ativação Dinâmica:** Quando um plugin perde validade financeira ou é desinstalado via API, o Landlord emite evento para desligar features contextuais daquele Inquilino.
- **Separação Landlord vs Tenant:** Endpoints desse domínio terão forte verificação: `isDeveloper()` pode ver catálogo completo, modificar preços; `TenantAdmin()` apenas enxerga a vitrine, adere via pagamento (ou ativação de gratuitos), e usa. Administradores podem, inclusive, escolher desabilitar plugins gratuitos da plataforma caso não queiram usar (ex: Desligar o plugin default de "Carrinho de Compras").

## 2. Entidades Principais
### Catálogo de Plugins (`Plugin`, `PluginCategory`, `PluginSubgroup`, `PluginVersion`, `PluginPricing`, `PluginFeature`)
- **Plugin:** Modelo raiz com info de mercado (`name`, `slug`, `short_description`, `long_description`, `support_url`, `status`, `logo`, `screenshots`). Agrega versões, categorias e preços.
- **PluginCategory:** Agrupamento principal (ex: Pagamentos, Mídia, Analytics, Pedagógico).
- **PluginSubgroup:** Subcategoria dentro de cada categoria.
- **PluginVersion:** Controle de versões do plugin com changelog.
- **PluginPricing:** Estrutura preços recorrentes ou avulsos (tier: free, basic, premium) criados pela master.
- **PluginFeature:** Lista de features/capabilities de cada plugin para exibição na vitrine.

### Consumo do Tenant (`PluginInstallation`, `PluginActivation`, `PluginSubscription`, `PluginBilling`, `PluginUsageLog`, `PluginLicense`, `PluginSetting`)
- **PluginInstallation:** Registro de instalação do plugin em um tenant.
- **PluginActivation:** Histórico de ativações/desativações.
- **PluginSubscription:** Similar ao histórico de um SaaS. Tem `status` (active, suspended, cancelled), limites de `metadata` e a data de recorrência (`next_billing_date`). Suportará períodos de Teste Grátis (`is_trial`, `trial_ends_at`).
- **PluginBilling:** O extrato recorrente das mensalidades pagas pelo Tenant Admin ao Master. Possui lógica complexa de refugo (`retry_count`, `next_retry_at`) para estorno automático.
- **PluginUsageLog:** Log de uso de recursos premium (quota tracking).
- **PluginLicense:** Licenças de uso para plugins específicos.
- **PluginSetting:** Configurações específicas do plugin por tenant.
- **TenantIntegration:** Para os plugins que exigem Tokens externos da mão do lojista/Tenant. A injeção dessas integrações é *Event-Driven* (ex: o plugin VIMEO dispara evento registrando seus campos de auth na tab do tenant). As chaves ficarão cifradas via Eloquent Casts Estritos (`encrypted:json`).
- **Cupons de Desconto (`PluginCoupon`):** Suporte a cupons fixos ou percentuais aplicáveis nativamente à cobrança SaaS.

## 2.1 Plugins Disponíveis (First-Party)

### Plugins de Pagamento
| Plugin | Status | Descrição |
|--------|--------|-----------|
| **Stripe** | Funcional | Gateway de pagamento internacional. Webhooks, checkout redirect. |
| **PixPayments** | Parcial | Pagamentos via PIX brasileiro. |

### Plugins de E-commerce
| Plugin | Status | Descrição |
|--------|--------|-----------|
| **Cart** | Funcional | Carrinho de compras. Gratuito, incluído por padrão. |
| **DiscountCoupons** | Estrutura | Cupons de desconto para checkout. |
| **Subscriptions** | Estrutura | Planos de assinatura recorrente para cursos. |
| **Affiliates** | Estrutura | Sistema de afiliados com tracking de comissões. |

### Plugins de Mídia e Conteúdo
| Plugin | Status | Descrição |
|--------|--------|-----------|
| **Comments** | Estrutura | Comentários em aulas. |
| **Community** | Estrutura | Fórum/comunidade por curso. Fóruns, Tópicos, Posts. |
| **CourseReviews** | Estrutura | Reviews/avaliações detalhadas de cursos. |
| **CustomCertificates** | Estrutura | Templates customizados de certificado por tenant. |

### Plugins de Analytics e Marketing
| Plugin | Status | Descrição |
|--------|--------|-----------|
| **EmailMarketing** | Estrutura | Automação de emails, logs de envio. |
| **SalesIntelligence** | Parcial | Analytics de vendas avançado. |
| **PerformanceReportsEnterprise** | Vazio | Relatórios enterprise de performance. |

### Plugins de Gamificação
| Plugin | Status | Descrição |
|--------|--------|-----------|
| **GamificationRewards** | Vazio | Sistema de pontos, badges, conquistas. |

## 3. Endpoints Principais (JSON)
*Base URL: `api/v1/ecosystem`*

### Vitrine SaaS para o Tenant_Admin (Logado e Identificado como Tenant)
- `GET /marketplace/plugins`: Retorna todos os plugins catalogados (visíveis) ativos. Suporta agrupamento por "Clusters" (Pagamentos, Mídia, Analytics, Pedagógico) e filtros consolidados (Todos, Premium, Free, Recomendados, Novos, Instalados). A aba "Instalados" é default.
- `GET /marketplace/plugins/{slug}`: Retorna a "Store Page" contendo fortíssimo apelo de UX: `price`, `rating`, data de criação, versão (`plugin_version`), data de última atualização, `description`, logotipo isolado, passo-a-passo e array de `screenshots`.
- `POST /marketplace/subscriptions`: Intenção de assinar um plugin. Mapeia um `Order` do Financial passando `origin_type: Subscription`, e gera o `PluginSubscription` atrelado ao tenant. Para plugins *Free*, o fluxo é um bypass direto que já retorna a ativação sem onerar o cart.

### Central do Desenvolvedor (Logado como `Developer`, Landlord level)
- `POST /admin/plugins`: Cadastro de nova ferramenta para vender.
- `PATCH /admin/plugins/{id}`: Liberação/Depreciação de plugin, bloqueando compras futuras.
- `GET /admin/subscriptions`: Dashboard analítico de assinaturas ativas de toda a base EAD multi-tenant.

## 4. Regras Sensíveis e Assincronismo
- **Suspensão Automatizada:** Diariamente, o CRON (`schedule()`) chamará um Action `SuspendOverduePluginSubscriptionsAction`. Se um `PluginBilling` excedeu o retry count ou o `next_billing_date` prescreveu sem confirmação via Webhook, ele fará o downgrade do inquilino.
- **Isolamento Total no Contexto de Configurações:** Toda vez que um tenant comprar ou desativar uma feature, o cache de Config/Integrations daquele host deverá ser limpo. Uma camada Singleton provida por `AppServiceProvider` resolverá se a funcionalidade solicitada pelas outras camadas da API está ativa checando contra `tenant.customizations`/`plugin.subscriptions`.
- **Rate Limiting Dinâmico:** Recursos premium providos por plugins também poderão controlar quotas (ex: disparos de e-mails em massa, acessos de rede). Middlewares da API devem ler a Subscription tier (`basic`, `premium`) e alocar o Rate Limiter do Laravel condizentemente (ex: 100/hour vs 5000/hour).
