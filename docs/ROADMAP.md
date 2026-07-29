# Roadmap — EAD 2026

Área/persona define valor e jornada; domínio limitado define ownership de código. Endpoint é unidade
de execução; jornada é unidade de sucesso. Contrato: [Áreas & Superfícies](specs/00-architecture/areas-surfaces.md).
Decisão: [ADR-006](specs/00-architecture/decisions/006-planejamento-por-jornadas-de-area.md).

## Sequência de jornadas

| ID | Status | Fatias mínimas / outcome | Saída objetiva |
|---|---|---|---|
| `FOUNDATION-0` | `partial` | normalizar contrato e gate universal | invariantes abaixo testados; E2E do primeiro slice de área |
| `MZRT-SKELETON` | `not-started` | tenant, primeiro admin, status e limits/entitlements | E2E provisiona tenant e administra status; `tenant:provision` isolado já existe, não fecha jornada |
| `ADMIN-OPS` | `partial` | catálogo/conteúdo, usuários e operação tenant | E2E Admin opera mínimo no próprio tenant; slice de curso atual não conforma até remediar override developer |
| `STUDENT-PAID` | `partial` | orders list/show, webhook assíncrono, pagamento→matrícula→acesso | E2E integrado checkout→payment→enrollment→access; checkout, matrícula e progresso já existem separadamente |
| `INSTRUCTOR-OWN` | `partial` | conteúdo próprio, alunos e avaliação necessária | E2E ownership sem contrato Admin |
| `MZRT-PLATFORM` | `not-started` | marketplace, SaaS Mzrt→tenant, `PlatformOrder*`, plugins | E2E entitlement/billing de plataforma; não é pré-requisito de Admin/Student |
| `HOME-PUBLIC` | `not-started` | vitrine, catálogo e funil público | E2E público, sem vazar contrato de área autenticada |

Executar uma jornada principal por vez, em fatias verticais. Itens sem bloquear jornada são
just-in-time: gateways adicionais, PDF, analytics/RabbitMQ, i18n/SEO, comissões, quotas e catálogo
amplo de plugins.

## Gates

**`FOUNDATION-0` universal** vale para cada nova rota de produto por área; não exige terminar itens
de outras jornadas antes do primeiro slice. Evidência atual é cautelosa:

| Gate universal | Estado | Remediação / evidência exigida |
|---|---|---|
| `area.guard` + invariante de área | `partial` | Primeiro slice Admin aceita developer por hierarquia; bloquear: remover bypass implícito **ou** documentar override endpoint-específico e auditado. Alvo continua persona exata. |
| teto de permissions | `not-started` | Clamp efetivo por `UserType`, com teste. |
| isolamento de tenant | `partial` | Middleware e testes existem; cada slice de área prova isolamento. |
| envelopes API + Resources | `partial` | Padrão existe; cada rota nova usa Resource/envelope e teste. |

**Gates por jornada:** capability runtime antes de fluxo Admin dependente de plugin; auditoria/segredos
antes da primeira operação sensível; idempotência/outbox antes de `STUDENT-PAID`. Checkout e outbox
já têm fundação, mas webhook e E2E integrado ainda não fecham jornada paga.

## DoD de jornada

Contrato área-first/persona explícita; auth e tenant isolation; feature tests; E2E da jornada;
eventos/efeitos idempotentes quando aplicáveis; Scribe; docs consistentes; endpoint legado mapeado ou
depreciado.

## Snapshot — área × capability

Status: `not-started` | `partial` | `usable` | `hardened`. Snapshot 2026-07-29; cobertura de
contrato/jornada, não certificação global.

| Área | Control plane / configuração | Operar / criar | Comprar / consumir | Superfície área-first |
|---|---|---|---|---|
| Mzrt | `not-started` (só comando `tenant:provision`) | `not-started` | `not-started` | `not-started` |
| Admin | `partial` (gateway tenant) | `partial` (curso publish) | n/a | `partial` — override developer pendente |
| Instructor | n/a | `partial` (ownership legado) | n/a | `not-started` |
| Student | n/a | n/a | `partial` (checkout, acesso/progresso) | `partial` (checkout) |
| Home | n/a | n/a | `not-started` | `not-started` |

### Prontidão de domínio

| Domínio | Estado | Evidência / lacuna |
|---|---|---|
| Financial | `partial` | ledger, checkout e outbox; orders/webhooks/E2E pago pendentes |
| Ecosystem | `partial` | entitlement/config de gateway; marketplace e billing plataforma pendentes |

Financial e Ecosystem não estão “não iniciados”; expandem conforme jornada demandar.

## Inventário agrupado de migração legada

`/api/v1/{area}/*` é rota de produto por persona. Rotas técnicas/cross-area podem ser neutras e
explícitas: `/api/v1/auth/*`, `/api/v1/webhooks/*`, `/api/v1/public/*`. Prefixos atuais
`/core`, `/learning` e `/assessment` são **legado**, não conformidade implícita.

| Legacy route/group | Classificação | Target | Status | Owner/source | Condição remoção/depreciação |
|---|---|---|---|---|---|
| `/core/auth/*` | técnica cross-area | `/auth/*` | legado | Core / inventory | neutral substituto documentado e clientes migrados |
| `/core/invitations` | create Admin; accept pública | split Admin + neutral/public | legado | Core / inventory | ambos contratos entregues |
| `/core/users*`, self | Admin/Mzrt/self misto | decisão: áreas + possível account neutral | decisão-needed | Core / inventory | decisão e substitutos por caso |
| `/learning/catalog/courses*` | público | Home | legado | Learning / inventory | Home E2E público |
| `/learning/catalog/categories*` | system/tenant | Mzrt + Admin | legado | Learning / inventory | split e isolation E2E |
| learning courses/modules/materials/lesson/media management | Admin/Instructor provável | decisão-needed | legado | Learning / inventory | contratos por persona decididos |
| `/learning/ratings` | Student | Student | legado | Learning / inventory | Student Resource/E2E |
| learning enrollments + lesson/progress | Admin/Instructor/Student misto | decisão-needed | legado | Learning / inventory | split por fluxo e E2E |
| assessment questionnaires/questions | Admin/Instructor | decisão-needed | legado | Assessment / inventory | ownership/contrato decidido |
| assessment attempts | Student | Student | legado | Assessment / inventory | Student E2E |
| assessment certificates list/show | Student/Admin; verify pública | decisão-needed + public neutral | legado | Assessment / inventory | split e verify neutro |
| `/admin/courses*`, payment-gateways*, orders/*/confirm-manual-payment | produto Admin | Admin | área-first parcial | multi / inventory | guard persona exata + jornada Admin E2E |
| `/student/checkout` | produto Student | Student | área-first parcial | Financial / inventory | orders/webhook/E2E pago |
| planned `/webhooks/gateways/{slug}` | técnica | `/webhooks/gateways/{slug}` | planejada | Financial / inventory | idempotência/outbox + testes |

## Diferidos

- Matrícula `invite_only` / `sales`: MVP `open`; ver `specs/20-catalog-learning/subspecs/courses-modules-lessons.md`.
- LGPD produção e supply chain: após gate de auditoria; ver `specs/00-architecture/security-privacy-lgpd.md` e `specs/00-architecture/dependency-supply-chain-security.md`.
- Laravel 13 + PHP 8.5: branch dedicada quando `composer why-not laravel/framework ^13` estiver limpo; não misturar com jornada.
