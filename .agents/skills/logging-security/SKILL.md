---
name: logging-security
description: Revisa segurança de logging e do audit trail LGPD — evita vazar PII/secret em logs e falha silenciosa. Ativa ao escrever/revisar logs, exceções, catch, ou trilha de auditoria (activitylog), e ao introduzir PII novo. Cruza com a invariante PiiAuditTest e config/lgpd.php.
license: adaptado de guisoft/ogdevel (logging-security), reescrito p/ este repo + LGPD
---

# Logging Security

Previne dois problemas: **vazar dado sensível** em log/exceção e **engolir erro** sem contexto.
Neste repo há um terceiro eixo de primeira classe: o **audit trail LGPD** via `spatie/activitylog`.

## Invariante primeiro

O trilho LGPD é **executável** — rode antes de revisar à mão:

```bash
docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact --filter=PiiAudit
```

`tests/Architecture/PiiAuditTest` garante (invariante #9): todo model em `config/lgpd.php` usa o
trait `LogsActivity` **e** loga cada campo PII declarado. `config/lgpd.php` é o **inventário
canônico** de PII (model => campos). Contrato em `docs/specs/00-architecture/security-privacy-lgpd.md`.

**Ao introduzir PII novo** (campo pessoal num model): registre em `config/lgpd.php` no mesmo PR, ou
o invariante quebra — e deve quebrar. Não é opcional.

## O que revisar

1. **Audit trail (LGPD)** — PII novo está em `config/lgpd.php`? O model loga os campos certos? Eventos de acesso/alteração de dado pessoal ficam rastreáveis sem expor o valor sensível em claro onde não deve?
2. **Vazamento em log/exceção** — varra logger calls, `catch`, contexto de erro. Nunca logar:
   - senha, token (Sanctum), API key, header `Authorization`, cookie;
   - CPF, email e demais PII de `config/lgpd.php` em texto livre fora do canal de auditoria;
   - payload bruto de request, conteúdo/caminho de upload;
   - dump de SQL ou stack em resposta (cruza com `ErrorEnvelopeShapeTest` e [[security-audit]]).
3. **Falha silenciosa** — `catch` que engole exceção sem re-throw nem log com contexto suficiente (tenant/user/request id) é bug, não robustez.
4. **Log injection** — entrada não confiável indo pra log sem neutralizar CRLF.

## Boas práticas deste repo

- Use o canal de log estruturado do Laravel; contexto mínimo útil (tenant, user id, request id), nunca o dado sensível em si.
- PII auditável vai pelo `LogsActivity`/activitylog (canal controlado, com retenção), não por `Log::info` solto.
- Não adicionar log ruidoso em loop quente sem razão de performance.
- Erro de domínio → exceção própria em `app/Shared/Exceptions` (envelope padrão), não `Log::error` + retorno mudo.

## Saída

Prosa enxuta + lista de findings: `arquivo:linha · o que vaza / o que engole · fix`. Se tocou PII,
declare explicitamente se `config/lgpd.php` precisa de update e se `PiiAuditTest` passou.

## Guardrails

- Não ler `.env`, `.env.*`, `secrets/**`.
- Não logar payload completo de auth, upload, pagamento ou chamada externa.
- Preservar contexto suficiente p/ debugar tenant/user/request — redação ≠ apagar contexto.
- Não silenciar `PiiAuditTest` para "passar" — o fix é registrar o PII, não burlar o invariante.
