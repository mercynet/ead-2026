---
domain: core-identity
parent: ../spec.md
resource: auth
last-reviewed: 2026-06-10
---

# Auth

Autenticação via Laravel Sanctum (tokens opacos, com device type). Detalhes de segurança em
[`../../00-architecture/security-privacy-lgpd.md`](../../00-architecture/security-privacy-lgpd.md).

## Rules

- **Login** requer contexto de tenant (header `X-Tenant-ID`/`X-Tenant-Domain` ou host), exceto
  para `developer`. Lookup tenant-scoped (usuário no tenant → fallback developer global). Rate
  limit de **5/min** por IP.
- Credenciais inválidas → `InvalidCredentialsException` (render centralizado).
- **Logout** invalida o token atual; requer autenticação.
- **Me** retorna o usuário autenticado + roles + permissions atrelados ao tenant atual
  (inclui `tenant_id` e `user_type`).
- **Esqueci a senha** (`password/forgot`, público, tenant-scoped): resposta sempre genérica
  (anti-enumeração); notifica por e-mail com token opaco (só o hash é persistido; expira em
  `PasswordReset::EXPIRES_IN_MINUTES`). Um novo pedido **invalida o token pendente anterior**
  (rotação: um único token válido por vez, tenant+email).
- **Redefinir senha** (`password/reset`, público, token-driven): tenant/email vêm do token; uso
  único, expiry; token inválido/expirado/usado → `PasswordResetInvalidException` (falha genérica).
- **Política de sessões na troca de senha:**
  - **Reset por token** revoga **todas** as sessões Sanctum do usuário (não há sessão de confiança).
  - **Troca autenticada** (`PATCH /users/me/password`) revoga **as outras** sessões, preservando a
    atual (a que fez a troca).
- **Rate limiters nomeados e separados por rota** (`login`, `password-forgot`, `password-reset`,
  `invitation-accept`, `invitation-create`) — evita que rotas anônimas dividam o mesmo bucket
  `domínio|IP` do throttle padrão. Sob proxy, garantir `TrustProxies` para o IP correto na chave.

## Endpoints

O contrato público canônico é `/api/v1/auth/*` (`TARGET_CANONICAL`), implementado pelo WS1. A
superfície `/api/v1/core/auth/*` permanece disponível como `CURRENT_IMPLEMENTED` +
`LEGACY_COMPATIBILITY` durante a v1, com o mesmo controller, middleware, throttling e semântica.
Remoção futura exige inventário de consumidores e decisão explícita; não há data artificial.

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/auth/login` | Autentica via email + senha, retorna token Sanctum | público (limiter `login`, 5/min) |
| POST | `/api/v1/auth/password/forgot` | Emite pedido de redefinição (e-mail com token) | público (limiter `password-forgot`, 5/min) |
| POST | `/api/v1/auth/password/reset` | Redefine a senha via token; revoga todas as sessões | público (limiter `password-reset`, 5/min) |
| POST | `/api/v1/auth/logout` | Revoga o token atual | autenticado |
| GET | `/api/v1/auth/me` | Usuário autenticado + roles + permissions | autenticado |

Os mesmos cinco endpoints continuam atendendo em `/api/v1/core/auth/*` somente para
compatibilidade legacy. A documentação Scribe primária exibe a superfície canônica.

## Permissions

Nenhuma permission específica — login é público; logout/me exigem apenas autenticação válida.

## Notes

- Body do login: `{ email, password }`; tenant vem por header/host.
- O token traz device type para gestão de sessões por dispositivo.
