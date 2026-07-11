---
name: coupling-analysis
description: Auditoria PONTUAL de acoplamento entre módulos (app/Modules/*) usando o modelo tridimensional de Khononov (strength × distance × volatility). Ativa quando o usuário pedir "analisa o acoplamento", "essa dívida da allowlist vale converter?", "esses módulos estão acoplados demais?", "relatório de acoplamento". NÃO é skill permanente de desenvolvimento — é ferramenta de auditoria; a fronteira do dia-a-dia é o ModuleBoundaryTest.
license: CC-BY-4.0 (adaptado de tech-leads-club/agent-skills; baseado em "Balancing Coupling in Software Design", Vlad Khononov)
---

# Coupling Analysis (auditoria pontual)

Modelo tridimensional de Khononov:

1. **Integration Strength** — *o que* é compartilhado entre módulos
2. **Distance** — *onde* o acoplamento vive (mesmo namespace ↔ serviços distintos)
3. **Volatility** — *com que frequência* cada lado muda

```
MAINTENANCE_EFFORT = STRENGTH × DISTANCE × VOLATILITY   (0 em qualquer dimensão = barato)
```

Design equilibrado: forte+perto (coesão) ou fraco+longe (loose coupling); estável tolera
acoplamento forte.

## Como aplicar NESTE repo

- **Ponto de partida do grafo**: a allowlist `$knownDebt` do `ModuleBoundaryTest`
  (`tests/Architecture/ModuleBoundaryTest.php`) — é a dívida cross-module já congelada — mais os
  imports permitidos (shared kernel `Core\Models|Enums`, `Events`, `Contracts`).
- **Distance é BAIXA em tudo**: monólito modular, um time. Logo o eixo decisivo aqui é
  **strength × volatility** — a pergunta real é "isso devia virar Event/Contract ou pode
  continuar relação Eloquent direta?".
- **Volatility por git** (rode no host):
  ```bash
  git log --since="6 months ago" --format="" --name-only -- app/Modules | sort | uniq -c | sort -rn | head -20
  ```
  Subdomínios: Learning/Assessment = core (alta volatilidade esperada); Core (identidade/tenancy)
  = supporting/generic (baixa).
- **Saída**: relatório + recomendação por aresta. Se a conclusão for "converter pra
  Event/Contract", vira task no `tasks.md` do domínio; se for "manter Eloquent direto",
  registre o porquê (candidato a ADR via `create-adr`).

## Níveis de Integration Strength (forte → fraco)

1. **Intrusive** — acessa interno não projetado pra integração (ler tabela de outro módulo
   direto, reflection, depender de estrutura interna). *Sinal Laravel*: query builder de um
   módulo montando `join` na tabela de outro. → refatorar sempre.
2. **Functional** — lógica de negócio inter-relacionada:
   - *sequential*: ordem obrigatória de execução entre módulos;
   - *transactional*: precisa commitar junto (`DB::transaction` cruzando módulos);
   - *symmetric* (pior): mesma regra duplicada nos dois lados ("lembrar de atualizar X quando
     mudar Y", validação copiada). Não exige import — é semântico, grep não pega.
3. **Model** — módulo consome o *model interno* do outro como interface pública.
   *Sinal Laravel*: `Certificate belongsTo Course` — Assessment conhece o Eloquent model
   inteiro de Learning (todas as colunas, casts, mutators), quando talvez precise de 3 campos.
4. **Contract** (ideal entre módulos) — DTO/evento de integração dedicado, estável, versionável.
   *Sinal Laravel*: Domain Event com payload primitivo (`LessonCompletedEvent`), interface em
   `Shared/Contracts`, Anti-Corruption Layer.

## Diagnóstico (strength × volatility; distance ≈ baixa no monólito)

| Strength | Volatility | Veredicto |
|---|---|---|
| Alta (intrusive/functional/model) | Alta (dois lados core, mudam juntos sempre) | 🟠 ou é coesão mal particionada (considere fundir/repensar fronteira) ou converta para Contract |
| Alta | Baixa (lado upstream estável) | 🟡 aceitável — documente e congele (allowlist) |
| Contract | qualquer | 🟢 padrão correto |
| Baixa, mas regra duplicada (symmetric) | Alta | 🔴 prioridade — extrair regra para um dono único |

## Formato do relatório

```
ARESTA: Assessment/Models/Certificate.php → Learning\Models\Course
Strength:   MODEL (belongsTo do model interno)
Distance:   baixa (mesmo monólito, mesmo time)
Volatility: Course = core, alta | Certificate = core, alta
Veredicto:  🟠 candidata a contrato — Certificate só lê {title, slug}; snapshot/DTO
            no momento da emissão eliminaria o acoplamento e ainda congela o dado
            historicamente correto (certificado não deve mudar se o curso mudar).
Ação:       task em docs/specs/30-assessment/tasks.md (ou ADR se decidir manter).
```

Feche com: resumo executivo (N arestas, N críticas), padrões positivos encontrados e
recomendações priorizadas (alta = bloqueia evolução; baixa = incremental).

## Heurísticas rápidas

- "Se eu mudar um detalhe interno de X, quantos módulos quebram?" → strength.
- "Esses dois mudam sempre no mesmo PR?" → ou coesão (aproxime) ou symmetric coupling (extraia).
- "O dado consumido devia ser *snapshot histórico* ou *referência viva*?" → snapshot ⇒ contrato
  é melhoria funcional, não só estética (caso típico: certificados, pedidos, notas).

## Limitações

- Volatility real exige git history; estimativa estática é chute — marque como estimativa.
- Symmetric coupling exige leitura semântica (não aparece em import nem no ModuleBoundaryTest).
- A análise é ponto de partida; contexto de negócio refina a conclusão.
