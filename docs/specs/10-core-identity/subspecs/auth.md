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
  para `developer`. Rate limit de **5 tentativas/minuto** (`throttle:5,1`).
- Credenciais inválidas → `InvalidCredentialsException` (render centralizado).
- **Logout** invalida o token atual; requer autenticação.
- **Me** retorna o usuário autenticado + roles + permissions atrelados ao tenant atual
  (inclui `tenant_id` e `user_type`).

## Endpoints

| Método | Path | Descrição | Permission |
|--------|------|-----------|------------|
| POST | `/api/v1/core/auth/login` | Autentica via email + senha, retorna token Sanctum | público (rate limit 5/min) |
| POST | `/api/v1/core/auth/logout` | Revoga o token atual | autenticado |
| GET | `/api/v1/core/auth/me` | Usuário autenticado + roles + permissions | autenticado |

## Permissions

Nenhuma permission específica — login é público; logout/me exigem apenas autenticação válida.

## Notes

- Body do login: `{ email, password }`; tenant vem por header/host.
- O token traz device type para gestão de sessões por dispositivo.
