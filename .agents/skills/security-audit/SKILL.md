---
name: security-audit
description: Auditoria de segurança source-to-sink de fluxos da API (endpoints, Actions, uploads). Ativa quando a tarefa toca auth/Sanctum, isolamento de tenant, IDOR, permissions, input/upload, redirects, ou exposição de dado sensível em resposta. NÃO é checklist OWASP genérico — toda finding precisa de caminho source→sink no código local.
license: adaptado de guisoft/ogdevel (security-audit), reescrito p/ este repo API-first
---

# Security Audit

Audita risco de segurança com **evidência de código local**. Finding só é válido com caminho
provado: source (dado/acesso não confiável) → controle esperado → gap → sink (asset sensível) →
impacto → mitigação mínima. Sem esse encadeamento, classifique `não-verificável`, não invente.

## Princípio deste repo: invariante primeiro, prosa depois

A superfície de segurança aqui é **executável**. Antes de revisar à mão, rode os invariantes —
eles são autoritativos e cobrem a maior parte da regressão. Só faça review manual do que o teste
**não** consegue pegar (IDOR dentro do mesmo tenant, mass assignment, allowlist de filtro/sort,
upload, redirect, secret em resposta).

```bash
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact
```

Invariantes relevantes (`tests/Architecture/`):

| Invariante | Garante |
|---|---|
| `RouteSecuritySurfaceTest` | toda rota `api/v1` tem `auth:sanctum` salvo allowlist pública explícita |
| `TenantIsolationSmokeTest` | leitura/escrita escopada por tenant |
| `PermissionDriftTest` | permissions usadas existem (sem drift) |
| `PiiAuditTest` | PII de `config/lgpd.php` logada via `LogsActivity` ([[logging-security]]) |
| `ErrorEnvelopeShapeTest` | erro não vaza shape/detalhe fora do envelope |
| `ScribeAuthAnnotationMatchesMiddlewareTest` | doc de auth bate com middleware real |

Se um invariante já cobre a finding, **não duplique em prosa** — aponte o teste. Achou buraco que
nenhum invariante pega? Proponha um novo invariante além de reportar.

## Regra central (cadeia obrigatória)

1. **source** — onde começa dado não confiável ou acesso não autorizado (body, query, route param, header, upload, payload de fila, callback externo).
2. **controle esperado** — validação (FormRequest), autorização (Gate/permission), escopo de tenant, allowlist, normalização.
3. **gap** — o controle ausente ou insuficiente.
4. **sink/asset** — SQL, escrita de model, resposta JSON, leitura/escrita de arquivo, mídia, redirect, log, chamada externa.
5. **impacto** — o que um ator não autorizado consegue fazer.
6. **evidência** — `arquivo:linha` que prova o caminho.
7. **mitigação** — o menor fix seguro, específico.

## Onde o risco mora neste repo

- **Tenant/IDOR** — ID vindo do request precisa ser escopado pelo tenant **antes** do fetch. ORM não previne IDOR sozinho. Middleware: `app/Modules/Core/Http/Middleware/{ResolveTenant,EnsureTenantAccess,InjectApiContext}`. Bulk: escopo por item, não só na lista.
- **Auth** — rota nova sem `auth:sanctum` só passa se entrar no allowlist do `RouteSecuritySurfaceTest` no **mesmo PR** (ato explícito).
- **Autorização ≠ autenticação ≠ validação** — `Gate::forUser()->authorize` no Action/Controller; FormRequest valida shape, não autoriza.
- **Filtro/sort** (spatie/query-builder) — `allowedFilters`/`allowedSorts` precisam ser allowlist; nunca expor coluna arbitrária.
- **Mass assignment** — fluxo é `Controller fino → Action`; campos explícitos no FormRequest/Action, não `$request->all()`.
- **Upload/mídia** — passa pelo port `MediaProvider`; validar extensão, MIME, tamanho, e que o nome é gerado server-side.
- **Resposta/erro** — envelope padrão (`app/Shared/Exceptions`); nunca vazar stack, SQL, token, ou PII. Erro de tenant → `TenantContextRequiredException`; acesso → `AccessDeniedException`.
- **Logs** — exposição de PII/secret em log é domínio da skill [[logging-security]]; cruze quando o fluxo logar.

## Classificação

- **Exploitabilidade**: `confirmado` (código prova) | `provável` (precisa runtime) | `teórico` | `não-verificável`.
- **Severidade** (impacto **local** real, não categoria genérica): `crítico` (cross-tenant, RCE, leak de credencial, escrita/leitura arbitrária de arquivo) · `alto` (bypass de privilégio, IDOR em registro sensível, SQLi com impacto) · `médio` (XSS/CSRF em área autenticada, redirect inseguro) · `baixo` (disclosure limitado) · `info` (hardening).

Mitigação ruim: "sanitize input", "use OWASP", "valide melhor". Mitigação boa: "escope query por `tenant_id` no `XAction` antes do `find`", "allowliste `sort` em `XQueryBuilder`".

## Saída

Relatório enxuto em prosa + tabela de findings: `id · título · severidade · exploitabilidade ·
arquivo:linha · source→sink (1 linha) · mitigação`. Auditoria limpa lista o que foi examinado e
por que passou (cite o invariante). Termine com `recommended_action` e confiança.

## Guardrails

- Não ler `.env`, `.env.*`, `secrets/**`. Não acessar produção/staging.
- Não reportar pelo nome de função "perigosa" — leia o código.
- Não marcar `confirmado` sem caminho source→sink provado; na dúvida, `não-verificável` + o que falta validar em runtime.
- Não assumir que ORM/validação/autenticação substituem autorização ou escopo de tenant.
- Não vazar secret/token/PII na própria saída da auditoria.
