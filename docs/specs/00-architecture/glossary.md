---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# Glossário

| Termo | Definição |
|-------|-----------|
| **Tenant** | Inquilino — uma instância isolada da plataforma (uma escola/empresa cliente). Âncora de isolamento: `tenant_id`. |
| **Landlord** | A empresa master (developer) que opera a plataforma e vende plugins aos tenants. |
| **UserType** | Tipo base do usuário (`developer`, `admin`, `instructor`, `student`) — define o teto de acesso. Ver `rbac.md`. |
| **Role** | Papel atribuível via spatie/laravel-permission; pode ser global ou de tenant (`scope`). |
| **Permission** | Capacidade nomeada (`<domain>.<resource>.<action>`); definida só em código. |
| **ApiContext** | Value Object (`$user` + `$tenant`) injetado por middleware. Ver `api-conventions.md`. |
| **Action** | Classe de regra de negócio (Command ou Query) em `app/Actions/<Domain>/<Resource>/`. |
| **own** | Escopo de autorização em que o usuário só acessa recursos que ele próprio criou/possui. |
| **Enrollment** | Matrícula de um student em um curso; aggregate root do cálculo de conclusão. |
| **Quizable** | Alvo polimórfico de um questionário (`lesson`, `course` ou `standalone`). |
| **Snapshot** | Cópia congelada (JSON) de questionário/questões no momento da tentativa, para integridade histórica. |
| **Pre-signed URL** | URL temporária para mídia no storage do tenant/provider, sem proxy binário pelo backend. |
| **Plugin** | Módulo opcional first-party que adiciona features/permissions; cobrado por assinatura SaaS. |
| **PluginSubscription** | Assinatura de um plugin por um tenant; concede as permissions do plugin. |
| **Order / Payment** | Intenção de compra e transação confirmada (Financial); valores sempre em centavos. |
| **Dados frios / quentes** | Catálogo (cacheável) vs. progresso pessoal (consultado no banco). Ver `performance-scalability.md`. |

## Legado eadIA

`/home/paulo/www/eadIA` é o sistema legado (49 models, 97 migrations, 5 painéis Filament,
140+ testes). É a **fonte de referência de regras de negócio e estrutura de dados** para esta
reconstrução API-first — não é código a portar literalmente.
