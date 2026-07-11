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
- short_description, long_description     // categorias via pivô category_plugin (M:N), não coluna
- support_url, logo
- screenshots            // JSON array
- is_curated             // "escolhido pelo Mzrt" (curadoria manual do developer)
- status / visibility    // controlado pela master (draft/published/deprecated)

// Categorias: REUSA a tabela `categories` (is_system=true, taxonomia global do Mzrt) +
// pivô dedicado `category_plugin` — MESMO molde de cursos. NÃO há tabela `plugin_categories`.
// Hierarquia N níveis e antiduplicação já resolvidas em categories (ADR-002).
category_plugin          // pivô dedicado
- plugin_id, category_id, position, is_featured

plugin_versions          // versão + changelog
plugin_pricings          // config do dev: tier free | basic | premium; recorrente ou avulso (preço fixo)
plugin_features          // capabilities exibidas na vitrine
plugin_permissions       // permissions concedidas ao assinar (ver rbac.md §3)

plugin_ratings           // tenant avalia plugin (1-5, comment, status moderação)
plugin_rating_aggregates // rollup (avg, count) — alimenta Featured
```

## Rules

- **First-party only:** plugins vivem em `app/Plugins/`, desenvolvidos só pela master; discovery por DB.
- **Só o Mzrt provisiona:** `developer` cria/edita/ativa/desativa/depreca e edita preços/curadoria;
  `tenant_admin` só vê a vitrine e adere.
- **Categorias = a tabela `categories` compartilhada** (`is_system`, hierarquia N níveis por
  materialized path), igual a cursos — vínculo via pivô `category_plugin`. **Sem tabela própria.**
  Ver [`../../00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md`](../../00-architecture/decisions/002-categorias-tabela-unica-pivot-dedicado.md).
  **Filtros são ortogonais à categoria** (ver abaixo): a categoria organiza o catálogo; o filtro é a
  lente do tenant para achar o plugin.
- A "Store Page" carrega forte apelo de UX: preço, **rating**, datas, versão, descrição, logo,
  passo-a-passo e screenshots.

### Categorias × Filtros (não confundir)

- **Categorias** (recursivas): eixo de organização do catálogo, definido pelo dev (ex.: Pagamentos →
  PIX; Mídia → Vídeo). Uma só hierarquia.
- **Filtros** (lentes da vitrine, combináveis): `Instalados` (default) · `Disponíveis` · `Free` ·
  `Premium` · `Novos` · `Featured` (orientado por uso + `plugin_rating_aggregates`) · `Recomendados`
  · `Escolhidos por mim` (`is_curated`, curadoria Mzrt). No fim, categoria + filtro se combinam para
  o tenant achar o que quer.

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
| GET | `/api/v1/ecosystem/marketplace/plugins` | Vitrine (categorias + filtros: Instalados/Disponíveis/Free/Premium/Novos/Featured/Recomendados/Escolhidos-por-mim) | tenant_admin |
| POST | `/api/v1/ecosystem/marketplace/plugins/{slug}/ratings` | Tenant avalia plugin (1-5 + comentário) | tenant_admin |
| GET | `/api/v1/ecosystem/marketplace/plugins/{slug}` | Store page do plugin | tenant_admin |
| POST | `/api/v1/ecosystem/admin/plugins` | Cadastrar plugin | developer |
| PATCH | `/api/v1/ecosystem/admin/plugins/{id}` | Liberar/desativar/depreciar + curadoria (`is_curated`) (bloqueia compras futuras) | developer |

## Permissions

`developer` para `admin/plugins/*`; `tenant_admin` para `marketplace/*`. Permissions concedidas
por assinatura: ver [`../../00-architecture/rbac.md`](../../00-architecture/rbac.md) §3.

## Notes

- Aba "Instalados" é o default da vitrine.
