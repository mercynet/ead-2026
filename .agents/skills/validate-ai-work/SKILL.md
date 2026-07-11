---
name: validate-ai-work
description: Revise, valide ou audite entrega, diff, commit, PR ou trabalho feito por outra IA. Ative ao revisar/validar/auditar implementação alheia, antes de aprovar PR, ou ao conferir mudanças não acompanhadas.
---

# Validate AI Work

Revisão independente e baseada em evidência. Git é árbitro; resumo da IA anterior não é prova.

## Quando usar

- Revisar entrega, diff, commit ou PR de outra IA.
- Validar implementação antes de aprovar, integrar ou pedir correção.
- Auditar mudança não acompanhada no working tree ou staged.

## Quando não usar

- Implementar mudança nova ou corrigir durante a revisão.
- Revisão de arquitetura sem escopo de alteração: use skill especializada aplicável.
- Consulta simples sem código, diff ou requisito a validar.

## Fluxo

1. **Fixe escopo e base.** Rode `git status`; inspecione somente alvo pedido:
   - working tree: `git diff`;
   - staged: `git diff --staged`;
   - commit: `git show <commit>` e diff contra pai/base;
   - PR: diff da branch/PR contra base declarada.
   Não revise arquivos fora desse escopo. Distinga working tree, staged, committed e pushed.
2. **Leia fontes de verdade na ordem:** `AGENTS.md` → specs relevantes em `docs/specs/` → código e testes afetados. Diferencie contrato-alvo de estado atual; código vence prosa em conflito.
3. **Compare requisitos e diff antes de executar testes.** Confirme cobertura de cada requisito, efeitos colaterais, migrações/configuração e mudanças de contrato.
4. **Procure riscos no escopo:** regressões, bugs, segurança, tenant/IDOR, LGPD/PII, contrato API, arquitetura e testes ausentes. Ative somente skill aplicável e disponível:
   - `security-audit`: auth, autorização, input, upload, tenant/IDOR, secrets;
   - `logging-security`: logs, auditoria, PII/segredos;
   - `coupling-analysis`: fronteiras de módulos, dependências/ciclos;
   - `pest-api-tests` ou `pest-testing`: testes API/Pest, cobertura ou falhas;
   - `endpoint-e2e`: fluxo HTTP cross-module ou crítico.
5. **Valide mínimo relevante.** Rode teste, análise ou formatter proporcional ao risco e mudança. Nunca alegue sucesso sem comando e resultado observados. Falha, bloqueio ou validação não executada = `não confirmado` no ponto afetado.
6. **Não edite durante revisão.** Registre correções propostas; aplique-as somente após autorização explícita. Não confie no resumo da IA anterior.
7. **Finalize com `context-checkpoint`** conforme contrato do repositório.

## Checklist

- [ ] Base, escopo e tipo de diff confirmados.
- [ ] Requisitos, contrato, specs, código e testes comparados.
- [ ] API: versão, Resource/envelope, auth/autz, Scribe quando aplicável.
- [ ] Tenant, PII/LGPD, permissões e isolamento verificados quando aplicável.
- [ ] Fronteiras modulares, regressões e cobertura avaliadas.
- [ ] Validação mínima executada ou lacuna declarada com motivo.
- [ ] Nenhum arquivo alterado durante revisão sem autorização.

## Saída obrigatória

**Findings primeiro**, ordenados por severidade: `bloqueante`, `alta`, `média`, `baixa`. Cada finding inclui `arquivo:linha`, impacto, evidência e correção proposta. Sem finding, declare explicitamente `nenhum finding confirmado` e o escopo examinado.

Depois, nesta ordem:

1. **Dúvidas e assunções** — o que depende de requisito, runtime ou acesso ausente.
2. **Comandos executados e resultados** — comando literal, status e resumo observável.
3. **Veredito** — somente `aprovado`, `aprovado com ressalvas`, `reprovado` ou `não confirmado`.

Use `aprovado` apenas com escopo suficiente e evidência verde; `não confirmado` quando não houver evidência necessária.
