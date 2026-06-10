---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# Performance e Escalabilidade

## Paginação

Toda listagem usa `cursorPaginate` (não offset) — estável sob inserção concorrente e barato em
tabelas grandes. Ver `api-conventions.md`.

## Estratégia de Cache

Separar **dados frios** de **dados quentes**:

- **Catálogo frio** (título do curso, grade curricular, preço) → cacheável no **Redis**.
- **Progresso quente** (progresso pessoal do aluno logado) → consultado no **banco**, não cacheado.

Essa divisão guia também o formato dos DTOs de leitura (catálogo vs. progresso pessoal).

## Eventos Assíncronos para Estatísticas

```
Action dispara Domain Event → fila (RabbitMQ) → Worker/Consumer → MariaDB de estatísticas
```

- Eventos de domínio (`LessonCompletedEvent`, `LessonViewedEvent`, `EnrollmentCreated`,
  `QuizAttemptFinished`, `CourseCompletedEvent`, `CertificateIssuedEvent`, `OrderPaidEvent`, etc.)
  são processados de forma assíncrona via Laravel Queue sobre RabbitMQ.
- Dados de estatística/histórico vão para uma instância **MariaDB de stats**, preservando o
  histórico mesmo quando entidades operacionais mudam.
- Os **nomes** dos eventos pertencem à seção `## Events` de cada `spec.md`; aqui mora a mecânica.

## Mídia (sem proxy binário)

Vídeos e materiais resolvem **pre-signed URLs** apontando para o storage do tenant (AWS S3) ou
provider externo (Vimeo). O backend nunca faz proxy binário de arquivos grandes — alivia a carga
do servidor de API. Integrações devolvem IDs; a camada Laravel envelopa em Player URL configurável
(chaves globais ou do tenant, conforme plugin "Private External Storage").

## Rate Limiting Dinâmico

Recursos premium de plugins controlam quotas via tier de subscription. Middleware lê o tier
(`basic`, `premium`) e configura o Rate Limiter do Laravel (ex.: 100/hour vs. 5000/hour).

## Invalidação de Cache por Tenant

Quando um tenant assina/desativa um plugin ou muda configurações, o cache de
config/integrations/features daquele host é limpo. Uma camada singleton (em `AppServiceProvider`)
resolve se uma feature está ativa checando contra `plugin.subscriptions` / `tenant.customizations`.

## Billing Recorrente (Cron)

Assinaturas de plugin usam Scheduler nativo do Laravel varrendo `next_billing_date`/`due_date`.
Diariamente, `SuspendOverduePluginSubscriptionsAction` faz downgrade de tenants com `PluginBilling`
vencido (estourou retry count ou prazo sem confirmação por webhook).

## Diferido

- Sharding / read replicas / multi-DB físico por tenant — fora do escopo inicial; o isolamento
  atual é por `tenant_id` em banco único.
