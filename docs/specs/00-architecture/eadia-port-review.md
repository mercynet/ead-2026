---
title: "Revisão de porte — migrations eadIA → ead2026"
status: doc de trabalho/referência, NÃO é contrato
source: revisão das migrations reais de /home/paulo/www/eadIA/database/migrations (e app/Plugins/*/database/migrations)
generated: 2026-06-13
reviewer: análise crítica assistida
scope: decidir o que portar para ead2026 (API-first, modular monolith em app/Modules/<Domínio>, multi-tenant via spatie/laravel-multitenancy, centavos inteiros, soft deletes, conteúdo i18n traduzível)
---

# Revisão de porte — migrations eadIA → ead2026

> **Aviso:** este documento é de **trabalho/referência**, não é contrato. O contrato canônico
> continua sendo `AGENTS.md` + `docs/specs/<domínio>/spec.md` + código. Gerado em 2026-06-13 a
> partir da leitura das migrations reais do eadIA. Onde a spec do ead2026 já diverge do eadIA, a
> **spec do ead2026 vence** — aqui só registramos o que aproveitar e os problemas encontrados.

## Convenções de invariante (o que o ead2026 exige e o eadIA frequentemente viola)

1. **Multi-tenant único** via `spatie/laravel-multitenancy` — `tenant_id` é **FK inteiro** para
   `tenants`, nunca string. No eadIA há mistura (string em `recurring_plans`/`plan_subscriptions`/
   `advanced_discounts`).
2. **Centavos inteiros** (`*_price_cents` `unsignedInteger`/`unsignedBigInteger`) — nada de
   `decimal(10,2)`. O eadIA mistura: catálogo de plugins (2025_11_30) já usa cents, mas
   orders/payments/courses/revenue usam `decimal`.
3. **Soft deletes** em entidades de negócio com ciclo de vida (courses, enrollments, orders,
   subscriptions...). O eadIA aplica em courses/lessons/categories mas **esquece** em
   enrollments, orders, payments, certificates, ratings.
4. **FKs sempre com `onDelete` explícito**; pivots/agregados com `unique` e índices compostos
   `(tenant_id, ...)`.
5. **Conteúdo i18n traduzível**: campos de exibição (title, description, short_description) devem
   ser `json` traduzível **com fallback de locale**. O eadIA **não tem nada de i18n** em
   courses/modules/lessons/categories — é gap a criar no ead2026, não a portar.
6. **Enums hardcoded no schema** (`$table->enum(...)`) são frágeis para evolução; preferir
   `string` + enum PHP (cast), como já faz a maioria das tabelas de catálogo de plugins do eadIA.

---

## Domínio: Financial (`40-financial`)

Encaixa em `app/Modules/Financial` (a criar). Specs: `40-financial/spec.md`,
`subspecs/orders-payments.md`, `subspecs/webhooks-events.md`.

| Tabela eadIA | Decisão | O quê / Por quê | Problemas pegos (caminho:linha) | Onde encaixa |
|---|---|---|---|---|
| `orders` | **Adaptar** | Boa base, mas reescrever para o schema da spec. | `decimal` em subtotal/discount/tax/total (`2025_08_23_214325_create_orders_table.php:19-22`) → deve ser `*_cents` inteiros. `tenant_id` foi adicionado **depois e nullable** (`2025_11_27_183135:12`) → no ead2026 nasce `NOT NULL` FK. Sem `softDeletes`. Falta `origin_type` (Direct/Cart/Subscription/Renewal) que a spec exige. `status` enum hardcoded — manter como string+enum PHP. | orders-payments.md |
| `order_items` | **Adaptar (crítico)** | Tornar **polimórfico** `itemable_type/itemable_id` + `item_snapshot` (JSON). | **Acoplamento legado:** `course_id` FK fixo + `course_title` + `course_snapshot` (`2025_08_23_214357:17,18,22`) — só serve curso; a spec do ead2026 exige item polimórfico (Curso/Plano/Plugin). `decimal` em unit/total price (`:19-20`) → `price_cents`. `tenant_id` nullable tardio (`2025_11_27_183135:16`). **Rejeitar** as colunas `course_*` soltas. | orders-payments.md |
| `payments` | **Adaptar** | Manter `external_id`, `gateway_response` (JSON), `paid_at`. | `decimal('amount')` (`2025_08_23_214453:20`) → cents. `gateway`/`method`/`status` enums hardcoded — string+enum. **Sem `tenant_id`** (deriva de order, mas a spec do ead2026 escopa tudo por tenant — avaliar adicionar para filtro direto). Sem soft delete. | orders-payments.md |
| `price_history` | **Adaptar conceito; não adotar schema** | Não portar histórico genérico polimórfico. Como só `Course` tem preço canônico agora, adaptar o conceito para `course_price_histories` no Learning, específico de curso; Financial conserva preço/snapshot contratado imutável em `OrderItem`. | `2025_12_10_190200:25-26` usa cents e polimorfismo, mas este generaliza sem vendáveis canônicos adicionais. No ead2026, `tenant_id` e FK real para `courses` preservam isolamento tenant e integridade; `change_reason` livre fica fora por YAGNI. | courses-modules-lessons.md (`CoursePriceHistory`) |
| `tenant_payment_gateways` | **Adaptar** | Estrutura certa (1 por gateway/tenant, `is_default`). | `configuration` é `json` **em claro** (`2025_12_10_190300:24`) — a spec do ead2026 **exige `encrypted:json`**. Portar como encrypted cast. Renomear/confirmar `gateway_identifier` vs `gateway`. Sem soft delete (aceitável). | orders-payments.md (`TenantPaymentGateway`) |
| `instructor_revenue_tracking` | **Adaptar / repensar** | Conceito valioso (LTV, comissão, atribuição UTM), mas é **tabela-monstro denormalizada**. | `2025_09_09_085435`: ~12 colunas `decimal` (`:37-43,70-71`) → cents. Mistura snapshot de buyer/course + atribuição UTM + financeiro num só lugar. Para o ead2026, **quebrar**: o registro financeiro espelho vai em orders/payments; comissão de instrutor vira **`commission_log`/`commission_entries`** dedicada (gap, ver abaixo); UTM/atribuição é analytics (outro módulo). Não portar como está. | spec.md (auditoria/LTV) + gap commission |
| `recurring_plans` | **Rejeitar (legado)** | Sobreposto por `subscription_plans` (plugin) e pela spec de plugins. | `tenant_id` **string** (`2025_08_28_000640:16`), `decimal` price (`:19`), enum hardcoded, sem soft delete. Versão antiga; o plugin Subscriptions já tem `subscription_plans` melhor. | — (descartar) |
| `plan_subscriptions` | **Rejeitar (legado)** | Idem; substituído por `tenant_subscriptions` do plugin. | `tenant_id` **string** (`2025_08_28_000657:16`), `decimal` (`:20`). | — |
| `advanced_discounts` | **Rejeitar (legado)** | Vira plugin `DiscountCoupons` (`coupons`). | `tenant_id` **string** (`2025_08_28_000714:16`), `decimal` (`:21`), `applicable_courses` como JSON solto. O plugin `coupons` é mais limpo. | 50-ecosystem-plugins |

### Gaps Financial a CRIAR no ead2026 (não existem no eadIA de forma limpa)

- **`commission_entries` / `commission_log`** (repasse de instrutor): hoje o eadIA só tem o
  blob `instructor_revenue_tracking`. O ead2026 precisa de uma tabela enxuta:
  `order_item_id`/`payment_id`, `instructor_id`, `gross_cents`, `platform_fee_cents`,
  `net_cents`, `commission_rate`, `status` (pending/calculated/paid/withheld), `paid_at` —
  tudo em cents, FK inteiro, soft delete. Encaixa em `Financial`.
- **Registro financeiro espelho de matrícula gratuita** (spec exige "toda matrícula gera registro
  financeiro"). O eadIA não garante isso. É regra/Action no ead2026, mas a Order/Payment precisa
  aceitar método "Automático/Gratuito" (`amount_cents = 0`).

---

## Domínio: Catalog & Learning (`20-catalog-learning`)

Encaixa em `app/Modules/Learning` (já existe) e `Catalog`. Specs: `20-catalog-learning/*`.

| Tabela eadIA | Decisão | O quê / Por quê | Problemas pegos (caminho:linha) | Onde encaixa |
|---|---|---|---|---|
| `courses` | **Adaptar** | Já tem tenant FK, soft delete, índices bons. | `decimal('price')` (`2025_08_20_223400:28`) → `price_cents`. **Sem i18n**: `title`/`short_description`/`description`/`target_audience`/etc são string/text monolíngue (`:16-23`) → no ead2026 virar `json` traduzível com fallback. `status`/`level` string (ok). `certificate_template_id` + `auto_issue_certificate` + `certificate_fields` adicionados depois (`2025_09_03_215139`) — bom, portar. | courses-modules-lessons.md |
| `course_modules` | **Adaptar** | OK. `unique(tenant_id,slug,course_id)` razoável. | Sem i18n em `title`/`description` (`2025_08_20_223600:17,19`). Sem `price` (ok). | courses-modules-lessons.md |
| `lessons` | **Adaptar** | Boa base (tenant, soft delete, vehiculation dates). | Sem i18n (`2025_08_20_223700:17-19`). `video_path`/`content_type` string solta — no ead2026 a mídia vai para `lesson_media` (ver abaixo); avaliar deprecar `video_path`. | courses-modules-lessons.md |
| `categories` | **Adaptar** | Tenant FK, parent self-FK, soft delete, unique(tenant,slug) — bom. | Sem i18n em `name`/`short_description`/`description` (`2025_08_20_223200:15-19`). | catalog.md |
| `enrollments` | **Adaptar (atenção)** | Conceito ok, mas frágil. | **Sem `softDeletes`** (`2025_08_20_223900`). `progress_percentage` duplicado entre enrollment e `lesson_progress`. `unique(user_id,course_id)` **sem tenant_id** (`:25`) — em multi-tenant deveria ser `(tenant_id,user_id,course_id)`. Campos de matrícula manual/payment_link (`2025_09_30`, `2025_11_29`) — portar com cautela; `payment_link` de 2048 chars e expiração. | enrollment-progress.md |
| `lesson_progress` | **Adotar (com ajuste)** | Boa: unique(enrollment,lesson), índices. | `2025_09_03_221034`: carrega `user_id`+`course_id`+`tenant_id` redundantes (deriváveis de enrollment) — aceitável para índice/consulta. Sem soft delete (ok p/ progresso). | enrollment-progress.md |
| `lesson_media` | **Adotar (com ajuste)** | Excelente: `media_type` enum, `progress_strategy`, metadata JSON. | `2025_09_03_123037`: **sem `tenant_id`** (deriva de lesson; aceitável mas inconsistente com o resto). `media_type`/`progress_strategy` enums hardcoded (`:17,23`) — preferir enum PHP. Spec do ead2026 chama de `provider_ref` o `media_id` — alinhar nome. | media-ratings.md |
| `lesson_media_progress` | **Adotar** | Muito boa: unique(media,user), completion %, watch_sessions JSON. | `2025_09_03_123059`: `completion_percentage decimal(5,2)` (`:21`) — aceitável (não é dinheiro). Tem tenant FK. | media-ratings.md |
| `ratings` | **Adaptar** | Polimórfico `rateable`, like/dislike, comment/status moderação. | Original (`2025_09_01_224104`) **sem tenant_id** e **sem soft delete**; só ganhou tenant no alter polimórfico (`2025_11_30_132928:14-15`, nullable). No ead2026 nasce com `tenant_id` NOT NULL. `status` moderação string (ok). Portar a forma **pós-alter** (com title/comment/status/flagged_at/meta_json), não a original. | media-ratings.md |
| `rating_aggregates` (ex-`rating_stats`) | **Adotar** | Cache de rollup correto (distribuição stars_1..5, avg, likes). | `2025_09_01_224115`: `avg_rating decimal(3,2)` (`:21`) ok. **Atenção à dívida de rename**: eadIA tem migration de rename + **view de compat** `rating_stats` (`2025_11_30_133110`) — no ead2026 nasce já como `rating_aggregates`, **sem** a view de compat nem o no-op de rename. Não portar a bagunça de rename. | media-ratings.md |
| `course_materials` | **Adaptar** | Tenant FK, instructor FK, download_count. | `2025_09_01_234152`: `file_size` como **string** (`:24`) — deveria ser `unsignedBigInteger` (bytes). `course_id`/`lesson_id` ambos nullable sem `onDelete` explícito (`:16-17`). Sem soft delete. Sem i18n em `title`/`description`. | media-ratings.md |
| `material_downloads` | **Adotar** | Log de download granular ok. | `2025_09_01_235050`: `ip_address`/`user_agent` como `string` NOT NULL (`:19-20`) — tornar nullable; `user_agent` pode passar de 255 → `text`. | media-ratings.md |
| `material_stats` | **Adotar** | Cache de stats (unique material_id). | `2025_09_02_103919`: ok. Contadores `downloads_today/week/month` precisam de job de reset (regra, não schema). | media-ratings.md |
| `tenant_integrations` | **Adaptar** | unique(tenant,type), config JSON. | `2025_09_03_123117`: `configuration` **JSON em claro** (`:18`) — deve ser `encrypted:json` (credenciais de provider). A spec de plugins já prevê isso em `TenantIntegration`. | 50-ecosystem-plugins / subscriptions-billing.md |
| `media` (Spatie) | **Adotar como está** | Já idêntico no ead2026. | `2025_08_30_225600` == migration do ead2026. Nenhuma ação. | — |

### Gaps Catalog/Learning a CRIAR no ead2026

- **i18n traduzível**: nenhuma das tabelas do eadIA tem JSON i18n com fallback. O ead2026 precisa
  decidir a estratégia (colunas `json` traduzíveis vs tabela `translations`) e aplicá-la a
  courses/modules/lessons/categories/materials. **Gap puro — não há nada para portar.**
- **`lesson_views`** (replay/estatística de view): a spec do ead2026 (media-ratings.md) define
  `lesson_views` + `LessonViewedEvent`. **Não existe no eadIA** — criar.

---

## Domínio: Assessment / Certificates (`30-assessment`)

Encaixa em `app/Modules/Assessment` (já existe). Spec: `30-assessment/subspecs/certificates.md`.

| Tabela eadIA | Decisão | O quê / Por quê | Problemas pegos (caminho:linha) | Onde encaixa |
|---|---|---|---|---|
| `certificate_templates` | **Adotar (com ajuste)** | `tenant_id` nullable = template global (developer) — padrão válido. | `2025_09_03_215106`: ok. Sem soft delete (templates deveriam ter — evita quebrar certs emitidos ao "apagar"). | certificates.md |
| `certificates` | **Adaptar (consolidar)** | Boa base (number, verification_code, revogação, JSON data). | **Dívida de migrations:** o schema final só existe após **3 migrations** (`2025_09_03_215111` cria; `..._224429` adiciona `completion_date`/`qr_code_data`/`validation_hash`/`status`; `..._225023` recria o enum `status`). No ead2026 nascer **consolidado** numa migration só. `status` enum reduzido a `issued|revoked` (`..._225023:22`). Sem soft delete. | certificates.md |
| `certificate_validations` | **Adotar** | Log público de validação (hash, ip, ua). | `2025_09_03_223953`: `validation_hash` sem FK ao certificate além do `certificate_id` (ok); `user_agent` text (bom). | certificates.md |
| Custom certs (plugin) | **Avaliar** | `app/Plugins/CustomCertificates/.../create_certificate_templates_custom_table.php` — específico do plugin. | Não revisado em profundidade; pertence ao plugin `CustomCertificates`, não ao core. | 50-ecosystem-plugins |

---

## Domínio: Ecosystem / Plugins (`50-ecosystem-plugins`)

Encaixa em `app/Modules/Ecosystem` / `app/Plugins/`. O ead2026 **já tem** 4 migrations base
(`plugins`, `plugin_subscriptions`, `plugin_billing`, `plugin_usage_logs`) — porém na **forma
antiga** do eadIA (pré-catálogo). O conjunto `2025_11_30_*` é a evolução de catálogo e é o que
vale portar.

| Tabela eadIA (2025_11_30_*) | Decisão | O quê / Por quê | Problemas pegos (caminho:linha) | Onde encaixa |
|---|---|---|---|---|
| `plugins` (alter catálogo) | **Adaptar (não portar o alter; criar consolidado)** | O eadIA **dropa a PK string e troca por id** + adiciona slug/category/version refs. | `2025_11_30_132822` é um **alter destrutivo** sobre a tabela antiga (dropa price/billing_cycle/is_active...). **O ead2026 já criou `plugins` com PK string `name` e price_cents** (`2026_02_21_183000`) — isso **colide** com o modelo de catálogo (PK `id`, slug, status, visibility, version refs). **Decisão:** reescrever a migration `plugins` do ead2026 para o modelo de catálogo consolidado (id PK, slug unique, status, visibility, category_id, current_version_id) **antes** de empilhar o resto. Não portar o alter. | marketplace.md |
| `plugin_categories` | **Adotar** | Simples e correto (slug unique, position). | `2025_11_30_132831`: ok. Sem soft delete (aceitável). | marketplace.md |
| `plugin_subgroups` | **Adotar** | Subcategoria. | (não lido em detalhe; estrutura análoga a categories). | marketplace.md |
| `plugin_versions` | **Adotar** | semver, changelog, status, hash, signed artifact — bom p/ supply chain. | `2025_11_30_132904`: unique(plugin,semver). Bom. | marketplace.md |
| `plugin_features` | **Adotar** | unique(plugin,key), metadata JSON. | `2025_11_30_132913`: ok. | marketplace.md |
| `plugin_pricings` | **Adotar** | **Já em cents** (`price_cents` unsignedBigInteger), trial, seats, addons. | `2025_11_30_132923:19`: cents — ótimo. `billing_period` string (ok). | subscriptions-billing.md |
| `plugin_purchases` | **Adotar (com nota)** | cents, provider charge id, status, refund. | `2025_11_30_132948`: cents ok. **Sobreposição com Financial `orders`**: a spec do ead2026 diz que assinar plugin pago **gera um Order** (`origin_type: Subscription`). Decidir se `plugin_purchases` coexiste com `orders` ou é substituído por Order+OrderItem(itemable=Plugin). Risco de **dupla fonte de verdade** financeira. | subscriptions-billing.md (resolver overlap) |
| `plugin_purchase_items` | **Adotar (condicional)** | Itens da compra (feature/seats). | Só faz sentido se mantiver `plugin_purchases` separado de `orders`. | subscriptions-billing.md |
| `plugin_licenses` | **Adotar** | unique(tenant,plugin,key). | `2025_11_30_132958`: ok. | subscriptions-billing.md |
| `plugin_audit_financials` | **Adaptar** | cents, type (charge/refund/fee/payout), provider ref. | `2025_11_30_133012`: `bigInteger amount_cents` **com sinal** (`:18`) — proposital p/ refund negativo; ok, mas documentar. Sobrepõe com `commission`/`price_history` — alinhar como trilha de auditoria financeira de plugins. | subscriptions-billing.md / Financial |
| `plugin_installations` | **Adotar** | tenant/version/source/status. | `2025_11_30_133019`: ok. | subscriptions-billing.md |
| `plugin_activations` | **Adotar** | histórico ativação/desativação. | `2025_11_30_133024`: ok. | subscriptions-billing.md |
| `plugin_settings` | **Adotar** | unique(tenant,plugin,key,user), scope. | `2025_11_30_133035`: `value_json` em claro — **avaliar** se algum setting guarda secret (então encrypted). | subscriptions-billing.md |
| `plugin_attachments` | **Adotar** | screenshots/mídia da store page. | `2025_11_30_133040`: ok (tenant nullable p/ asset global). | marketplace.md |
| `plugin_logs` | **Adotar** | level/message/context. | `2025_11_30_133044`: só `created_at` (sem updated_at) — proposital (append-only). ok. | subscriptions-billing.md |
| `plugin_subscriptions` (ead2026 atual) | **Manter, mas revisar** | ead2026 já criou (`2026_02_21_183010`) atrelado a `plugin_name` string PK. | Se `plugins` migrar para PK `id`, a FK `plugin_name`→`plugins.name` **quebra**. **Realinhar** `plugin_subscriptions`/`plugin_billing`/`plugin_usage_logs` para `plugin_id` ao adotar o catálogo. | subscriptions-billing.md |

### Plugins de e-commerce (app/Plugins/*) — portar como **plugins**, não core

| Tabela | Decisão | Problemas pegos | Onde encaixa |
|---|---|---|---|
| `coupons` (DiscountCoupons) | **Adaptar** | `decimal('value')`/min/max (`create_coupons_table.php:19-21`) → cents; resto bom (tenant FK, per_user_limit). Substitui `advanced_discounts` legado. | plugin DiscountCoupons |
| `subscription_plans` + `tenant_subscriptions` (Subscriptions) | **Adaptar** | `decimal('price')` (`create_subscriptions_tables.php:24`) → cents; enums hardcoded; sem soft delete. Substitui `recurring_plans`/`plan_subscriptions` legados. Tem `stripe_price_id`/`mercadopago_*` (ok). | plugin Subscriptions |
| `affiliates`/`affiliate_links`/`affiliate_commissions` (Affiliates) | **Adaptar** | Vários `decimal` (`create_affiliates_tables.php:22-24,59,61`) → cents; sem soft delete. Conceito de comissão de afiliado é **distinto** da comissão de instrutor (não confundir). | plugin Affiliates |
| `carts`/`cart_items` (Cart) | **Adaptar** | Não lido em detalhe; Cart é free/default na spec. Itens devem ser **polimórficos** (alinhar com order_items). | plugin Cart |

---

## Tabelas a IGNORAR (infra/tooling, não portar)

- `telescope_entries` (`2025_08_30_113935`) — Telescope é dev-only; ead2026 não deve carregar.
- `landlord_tenants` original (`2025_08_20_214558`) — o ead2026 já tem seu modelo de tenants via
  spatie/laravel-multitenancy; **não portar** o schema antigo (note que lá `database` é unique →
  multi-database; confirmar se o ead2026 é single-DB com scoping, o que muda tudo).
- `permission_tables`, `activity_log`, `cache`, `jobs`, `media`, `personal_access_tokens` — já
  existem no ead2026.
- `quiz_*`, `question_banks`, `advanced_questions`, `course_trails`, `trail_enrollments`,
  `course_analytics_daily`, `engagement_metrics`, `instructor_announcements`, `lesson_questions` —
  fora do escopo desta revisão (Assessment/Analytics/Community); revisar em rodada própria.

---

## Top problemas concretos (resumo priorizado)

1. **`order_items` acoplado a curso** (`2025_08_23_214357:17-18,22`): `course_id` FK + `course_title`
   + `course_snapshot`. ead2026 exige `itemable` polimórfico + `item_snapshot`. **Rejeitar colunas legadas.**
2. **`decimal` em todo o Financial** (orders, payments, instructor_revenue, recurring_plans,
   coupons, subscriptions, affiliates) → converter **tudo** para cents inteiros.
3. **`tenant_id` como string** em `recurring_plans:16`, `plan_subscriptions:16`, `advanced_discounts:16`
   → no ead2026 é FK inteiro (essas 3 tabelas são legado, descartar de qualquer forma).
4. **`tenant_id` adicionado tarde e nullable** em orders/order_items (`2025_11_27_183135`) e ratings
   (`2025_11_30_132928:15`) → nascer NOT NULL no ead2026.
5. **Credenciais em claro**: `tenant_payment_gateways.configuration` (`2025_12_10_190300:24`) e
   `tenant_integrations.configuration` (`2025_09_03_123117:18`) → `encrypted:json` obrigatório.
6. **Falta de soft deletes** em enrollments, orders, payments, certificates, ratings,
   certificate_templates, course_materials.
7. **`enrollments` unique sem tenant** (`2025_08_20_223900:25`) → `(tenant_id,user_id,course_id)`.
8. **PK do `plugins` muda de string→id** num alter destrutivo (`2025_11_30_132822`) que colide com
   o `plugins` PK-string já criado no ead2026 (`2026_02_21_183000`) e quebra FKs `plugin_name`.
9. **Dívida de migrations encadeadas** (certificates em 3 passos; rating_stats→rating_aggregates com
   view de compat) → nascer consolidado no ead2026, sem views/no-ops de compat.
10. **`course_materials.file_size` como string** (`2025_09_01_234152:24`) → bytes inteiros.

## Gaps (criar no ead2026, sem origem no eadIA)

- **i18n traduzível** (JSON com fallback) em courses/modules/lessons/categories/materials.
- **`commission_entries`/`commission_log`** enxuta para repasse de instrutor (hoje só o blob
  `instructor_revenue_tracking`).
- **`lesson_views`** (replay) + `LessonViewedEvent`.
- **Registro financeiro espelho** de matrícula gratuita (Order/Payment com método "Gratuito",
  `amount_cents=0`).
- ~~**Resolver overlap** `plugin_purchases` × `orders`~~ — **RESOLVIDO (2026-06-13):** dois ledgers
  irmãos — `PlatformOrder*` (Mzrt→tenant, gateway do Mozart) ≠ `Order*` (tenant→aluno). `plugin_purchases`
  descartado. Ver `decisions/003-billing-dois-ledgers-itemable-seam.md` e `40-financial/subspecs/orders-payments.md`.
