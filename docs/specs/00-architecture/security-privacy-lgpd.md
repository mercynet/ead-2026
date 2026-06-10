---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-06-10
---

# Segurança, Privacidade e LGPD

## Autenticação

- Tokens opacos via Laravel Sanctum (com device type no token).
- Login com rate limit de **5 tentativas/minuto** (`throttle:5,1`).
- Credenciais inválidas retornam exceção de domínio (`InvalidCredentialsException`), não vazam
  se o email existe.
- **Impersonação segura** (diferido): tokens especiais com Sanctum Abilities (`impersonating`)
  para developer analisar tenants ou tenant_admin analisar students.

## Autorização

- Toda rota protegida por `auth:sanctum` + `tenant.access`.
- Cada endpoint valida Gate/Policy antes da Action (ver `rbac.md`).

## Segredos e Credenciais

Credenciais de integrações externas nunca são armazenadas em texto puro. Usar Eloquent Cast
`encrypted:json`:

- `TenantIntegration.credentials` — tokens de integrações (Vimeo, analytics, etc.).
- `TenantPaymentGateway` — credenciais de gateways de pagamento (Stripe, MercadoPago, Pagarme).

## Inventário de PII (User)

| Campo | Sensibilidade | Observação |
|-------|---------------|------------|
| `cpf` | Alta | **Identificador sensível, único cross-tenant.** Ver regra de CPF em `10-core-identity/subspecs/users.md` |
| `email` | Média | Login; pode repetir entre tenants |
| `name` | Média | Nome do usuário |
| `bio` / `headline` | Baixa | Perfil público |
| `avatar` | Baixa | Imagem de perfil |
| `linkedin` / `twitter` (social) | Baixa | Links sociais opcionais |

## Direitos LGPD a Semear (TODO — diferido)

Marcado como diferido; ainda não implementado. Listado aqui para que o design já contemple:

- **Exportação de dados** (data portability) — endpoint para o titular exportar seus dados. _TODO_
- **Apagamento / anonimização** (right to erasure) — anonimizar PII preservando integridade de
  relatórios (ex.: manter `Order` mas anonimizar o titular). _TODO_
- **Consentimento** — `terms_url` / `privacy_url` por tenant (em `TenantCustomization`),
  registro de aceite por usuário. _TODO_
- **Trilha de auditoria** (audit trail) — log de acessos e alterações a dados pessoais. _TODO_

Nenhum destes itens está no escopo das fases iniciais (ver `docs/ROADMAP.md`); são pré-requisitos
de produção.
