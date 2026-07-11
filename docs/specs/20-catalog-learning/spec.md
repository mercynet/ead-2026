---
domain: catalog-learning
maturity: stable
last-reviewed: 2026-07-01
owners: [paulo]
related:
  - ../00-architecture/api-conventions.md
  - ../00-architecture/rbac.md
  - ../00-architecture/performance-scalability.md
  - subspecs/catalog.md
  - subspecs/courses-modules-lessons.md
  - subspecs/enrollment-progress.md
  - subspecs/media-ratings.md
---

# Catalog & Learning

## Intent / Why

É o coração do produto: organiza a vitrine de cursos (catálogo), a montagem estrutural do
conteúdo (cursos → módulos → aulas → materiais) e a jornada do aluno (matrícula, consumo,
progresso). O objetivo é entregar uma experiência de aprendizado fluida, com catálogo rico para
landing pages e tracking confiável de progresso que alimenta certificação e estatísticas.

## Overview

Padrões transversais (Command/Query Actions, cursorPaginate, controller lean, error envelope,
guardrails de payload) estão em
[`../00-architecture/api-conventions.md`](../00-architecture/api-conventions.md). A mecânica
assíncrona de eventos/estatísticas e a estratégia de cache (frio vs. quente) e mídia (pre-signed
URLs) estão em [`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md).

Recursos detalhados nas subspecs:

- [`subspecs/catalog.md`](subspecs/catalog.md) — categorias, vitrine de cursos.
- [`subspecs/courses-modules-lessons.md`](subspecs/courses-modules-lessons.md) — estrutura e CRUD admin.
- [`subspecs/enrollment-progress.md`](subspecs/enrollment-progress.md) — matrícula e progresso.
- [`subspecs/media-ratings.md`](subspecs/media-ratings.md) — mídia, materiais, avaliações.

## Entities

| Model | Tabela | Invariantes |
|-------|--------|-------------|
| `Category` | `categories` | Tabela única; hierárquica (`parent_id`+materialized `path`/`depth`, parent de mesmo escopo); system (`tenant_id=null,is_system=true`, só developer) vs. tenant; unicidade `UNIQUE(tenant_key=COALESCE(tenant_id,0), normalized_name)` + guard app (tenant ≠ nome de sistema); soft delete com proteção. Ver ADR-002. |
| `Course` | `courses` | Agrupador raiz; soft deletes; status publicação; preço em centavos; config de certificado. |
| `CourseModule` | `course_modules` | Pertence a um único curso; reordenável (`sort_order`). |
| `Lesson` | `lessons` | Conteúdo da aula; pode ser gratuita (`is_free`); reordenável. |
| `Enrollment` | `enrollments` | Vínculo de acesso/participação no curso; no máximo uma matrícula `active` por aluno/curso, permitindo histórico inativo (`cancelled`/`expired`). |
| `LessonProgress` | `lesson_progress` | Tracking granular por aula. |
| `LessonView` | `lesson_views` | Estatística: 1 registro por acesso (replay). |
| `Rating` / `RatingStats` | `ratings` / `rating_stats` | Avaliação 1-5 + like/dislike; rollup agregado em cache. |
| `CourseMaterial` / `MaterialDownload` | — | Materiais extras (≤50MB) com tracking de download. |
| `category_course` (pivot) | `category_course` | Pivô **dedicado** (não polimórfico): `tenant_id, course_id, category_id, order, is_featured`; FK reais; nunca cruza tenants. Produtos futuros = `category_product`. |

## Business Rules

- **Catálogo vs. progresso (dados frios vs. quentes):** payloads de leitura separam catálogo
  (cacheável) do progresso pessoal (banco). Ver
  [`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md).
- **Categorias — tabela única, pivô dedicado, materialized path, soft delete:** system (developer/Mzrt)
  vs. tenant (Admin); tenant usa as de sistema por seleção (banco de categorias), não as edita;
  antiduplicação por `normalized_name` (system único global, tenant único interno, livre entre
  tenants, nunca colide com sistema); delete com proteção (system com cursos bloqueia; tenant com
  cursos exige confirm + detach). Desenho em ADR-002; detalhe em
  [`subspecs/catalog.md`](subspecs/catalog.md).
- **Catálogo esconde cursos já comprados** pelo aluno logado.
- **Access control na lição:** acesso à mídia se o curso for gratuito, ou a aula for degustação
  (`is_free`), ou houver `Enrollment` `active` não expirado. Matrícula `expired` ainda vê a vitrine
  (`canViewCourse`), mas não consome conteúdo pago (`canAccessPaidContent`).
- **Drafts:** cursos `draft` só acessíveis em rota de preview por instrutor dono, tenant_admin ou developer.
- **Reassistir:** aula concluída permanece `is_completed=true`; cada acesso gera `LessonView`.
- **Progresso assíncrono:** `LessonCompletedEvent` recalcula a grade em background e pode
  engatilhar certificado. Conclusão do curso é atributo de **progresso**, não status da matrícula.
- **Lifecycle da matrícula:** `pending` (aguardando confirmação/pagamento), `active`, `cancelled`,
  `expired`. `completed` não faz parte do status da matrícula; pertence ao domínio de progresso.
- **Rematrícula:** permitida quando a matrícula anterior estiver `cancelled` ou `expired`; não faz
  sentido enquanto houver matrícula `pending` ou `active`.
- **Matrículas manuais por instrutor** (switch do tenant_admin); cobrança `external` pode cair
  como `pending` para aprovação.
- **Auditoria financeira:** toda matrícula (mesmo gratuita) origina registro espelho no Financial.

## Domain Boundaries

- **Consome (planejado):** `OrderPaidEvent` (Financial) → matrícula automática via `EnrollService`.
- **Emite:** `LessonCompletedEvent` (recalcula progresso; pode engatilhar Assessment/Certificate),
  `EnrollmentCreated`, `LessonViewedEvent`.
- Mecânica de transporte (RabbitMQ → MariaDB stats) em
  [`../00-architecture/performance-scalability.md`](../00-architecture/performance-scalability.md).

## Authorization

Matriz completa em [`../00-architecture/rbac.md`](../00-architecture/rbac.md) §4 (Learning).
Permissions do domínio:

```
learning.categories.{list,create,view,update,delete} · learning.categories.system.manage
learning.courses.{list,create,view,update,delete,publish}
learning.modules.{list,create,view,update,delete,reorder}
learning.lessons.{list,create,view,update,delete}
learning.enrollments.{list,create,view,update,delete}
learning.progress.view
```

## Events

- `LessonCompletedEvent` — aula concluída (recalcula progresso, engatilha certificado).
- `EnrollmentCreated` — matrícula criada.
- `LessonViewedEvent` — cada acesso à aula (estatística de replay).

## Quick Reference

| Recurso | Endpoint | Permission |
|---------|----------|------------|
| Listar catálogo | `GET /api/v1/learning/catalog/courses` | público/auth (flag do tenant) |
| Ver curso (landing) | `GET /api/v1/learning/catalog/courses/{slug}` | público/auth |
| Listar categorias | `GET /api/v1/learning/catalog/categories` | `learning.categories.list` |
| Criar categoria | `POST /api/v1/learning/catalog/categories` | `learning.categories.create` |
| Atualizar categoria | `PUT /api/v1/learning/catalog/categories/{id}` | `learning.categories.update` |
| Deletar categoria | `DELETE /api/v1/learning/catalog/categories/{id}` | `learning.categories.delete` |
| Atualizar curso | `PATCH /api/v1/learning/courses/{id}` | `learning.courses.update` |
| Deletar curso | `DELETE /api/v1/learning/courses/{id}` | `learning.courses.delete` |
| Módulos do curso | `GET /api/v1/learning/courses/{id}/modules` | `learning.courses.view` |
| Minha matrícula | `GET /api/v1/learning/courses/{id}/enrollment` | auth (own) |
| Ver aula | `GET /api/v1/learning/lessons/{id}` | `learning.lessons.view` / acesso |
| Progresso (heartbeat) | `POST /api/v1/learning/lessons/{id}/progress` | auth (own) |
