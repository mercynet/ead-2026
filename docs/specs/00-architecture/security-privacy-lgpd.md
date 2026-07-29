---
layer: architecture
applies-to: all-domains
last-reviewed: 2026-07-28
---

# Segurança, Privacidade e LGPD

## Autenticação

- Tokens opacos via Laravel Sanctum (com device type no token).
- Login com rate limit de **5 tentativas/minuto** (`throttle:5,1`).
- Credenciais inválidas retornam exceção de domínio e não vazam existência de email.
- Impersonação segura é diferida: Abilities Sanctum `impersonating`.

## Autorização

- Rota protegida usa `auth:sanctum` + `tenant.access`.
- Endpoint valida Gate/Policy antes da Action.

## Segredos e Credenciais

Credenciais e configurações sensíveis externas nunca são texto puro. `TenantPluginConfig.config` é store canônico por tenant+plugin e usa cast `encrypted:array` + `$hidden`; inclui credenciais de gateways e integrações.

APIs de gateway aceitam escrita de segredo, mas redigem valores sensíveis na resposta. Nunca serializar, logar ou expor `config` sensível.

## Inventário de PII (User)

| Campo | Sensibilidade | Observação |
|-------|---------------|------------|
| `cpf` | Alta | Identificador sensível, único por tenant. |
| `email` | Média | Login; único por tenant. |
| `name` | Média | Nome do usuário. |
| `bio` / `headline` | Baixa | Perfil público. |
| `avatar` | Baixa | Imagem de perfil. |
| `linkedin` / `twitter` | Baixa | Links sociais opcionais. |

## Direitos e controles LGPD

Identidade é tenant-scoped; export/erasure operam por tenant.

### MVP (desde já)

- Trilha de auditoria de PII via `spatie/laravel-activitylog`; campos registrados em `config/lgpd.php`.
- Minimização e cifragem: segredos sempre `encrypted:array`; nunca logar PII em claro.

### Pré-lançamento público

- Consentimento de `terms_url` / `privacy_url` por tenant, com registro de aceite por usuário.

### Pós-MVP

- Exportação de dados.
- Apagamento/anonimização de PII, preservando integridade de relatórios.

Priorização em `docs/ROADMAP.md`.
