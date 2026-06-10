---
domain: ecosystem-plugins
parent: ../spec.md
resource: marketplace
last-reviewed: 2026-06-10
---

# Marketplace (Catálogo de Plugins)

## Model / Schema

```
plugins
- id, name, slug
- short_description, long_description
- support_url, logo
- screenshots            // JSON array
- (status de mercado controlado pela master)

plugin_categories        // Pagamentos, Mídia, Analytics, Pedagógico
plugin_subgroups         // subcategoria dentro de cada categoria
plugin_versions          // versão + changelog
plugin_pricings          // tier: free | basic | premium; recorrente ou avulso
plugin_features          // capabilities exibidas na vitrine
plugin_permissions       // permissions concedidas ao assinar (ver rbac.md §3)
```

## Rules

- **First-party only:** plugins vivem em `app/Plugins/`, desenvolvidos só pela master.
- `developer` vê o catálogo completo e edita preços/disponibilidade; `tenant_admin` vê só a vitrine.
- A "Store Page" carrega forte apelo de UX: preço, rating, datas, versão, descrição, logo,
  passo-a-passo e screenshots.

## Plugins First-Party (catálogo planejado)

O **estágio de implementação** de cada plugin (funcional/parcial/estrutura/vazio) é estado mutável
e vive em [`../tasks.md`](../tasks.md), não aqui.

| Cluster | Plugins |
|---------|---------|
| Pagamento | Stripe, PixPayments |
| E-commerce | Cart (free, default), DiscountCoupons, Subscriptions, Affiliates |
| Mídia/Conteúdo | Comments, Community (fórum), CourseReviews, CustomCertificates |
| Analytics/Marketing | EmailMarketing, SalesIntelligence, PerformanceReportsEnterprise |
| Gamificação | GamificationRewards |

## Endpoints

| Método | Path | Descrição | Acesso |
|--------|------|-----------|--------|
| GET | `/api/v1/ecosystem/marketplace/plugins` | Vitrine (clusters + filtros: Todos/Premium/Free/Recomendados/Novos/Instalados) | tenant_admin |
| GET | `/api/v1/ecosystem/marketplace/plugins/{slug}` | Store page do plugin | tenant_admin |
| POST | `/api/v1/ecosystem/admin/plugins` | Cadastrar plugin | developer |
| PATCH | `/api/v1/ecosystem/admin/plugins/{id}` | Liberar/depreciar (bloqueia compras futuras) | developer |

## Permissions

`developer` para `admin/plugins/*`; `tenant_admin` para `marketplace/*`. Permissions concedidas
por assinatura: ver [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §3.

## Notes

- Aba "Instalados" é o default da vitrine.
