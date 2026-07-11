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
| `cpf` | Alta | **Identificador sensível, único POR tenant** (`unique(tenant_id, cpf)`). Ver `10-core-identity/subspecs/users.md` |
| `email` | Média | Login; único por tenant (`unique(tenant_id, email)`) |
| `name` | Média | Nome do usuário |
| `bio` / `headline` | Baixa | Perfil público |
| `avatar` | Baixa | Imagem de perfil |
| `linkedin` / `twitter` (social) | Baixa | Links sociais opcionais |

## Direitos e controles LGPD

Identidade é **tenant-scoped** → cada tenant é controlador dos seus próprios dados; export/erasure
operam por tenant, sem cruzar fronteira.

### MVP (desde já)

- **Trilha de auditoria de PII** — alterações/acessos a dados pessoais auditados via
  `spatie/laravel-activitylog`. É a **invariante #9** do contrato, guardada por `PiiAuditTest`:
  modelos com PII usam o trait `LogsActivity`; campos PII registrados em `config/lgpd.php`.
- **Minimização e cifragem** — segredos sempre `encrypted:json` (ver acima); nunca logar PII em claro.

### Pré-lançamento público (obrigatório antes de abrir ao público; não no 1º sprint)

- **Consentimento** — aceite de `terms_url` / `privacy_url` (por tenant, em `TenantCustomization`),
  com registro de aceite por usuário.

### Pós-MVP (diferido)

- **Exportação de dados** (portabilidade) — endpoint do titular exportar seus dados.
- **Apagamento / anonimização** (right to erasure) — anonimizar PII preservando integridade de
  relatórios (ex.: manter `Order`, anonimizar o titular).

Priorização em `docs/ROADMAP.md`.
