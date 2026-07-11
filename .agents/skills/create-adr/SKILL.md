---
name: create-adr
description: Cria Architecture Decision Records (ADRs) em docs/specs/00-architecture/decisions/ para registrar decisões arquiteturais e seu porquê. Ativa quando o usuário pedir "escreva um ADR", "documente essa decisão", "registre por que escolhemos X". NÃO usar para decisão ainda não tomada (discuta antes), nem para regra de negócio durável (isso vive na spec.md do domínio).
license: CC-BY-4.0 (adaptado de tech-leads-club/agent-skills)
---

# ADR Creator

ADR = registro curto e **imutável** do contexto, decisão e consequências de uma escolha
arquitetural significativa — para quem chegar daqui a anos entender *por quê*.

## Onde isto se encaixa neste repo

- **Saída canônica**: `docs/specs/00-architecture/decisions/NNN-titulo-kebab.md` (zero-padded,
  sequencial — escaneie o diretório antes de numerar; crie-o no primeiro ADR).
- **ADR ≠ spec**: a *regra durável* vive na `spec.md` do domínio (contrato, sem status); o ADR
  registra a *decisão pontual* e linka a spec atualizada. Se a decisão muda regra de negócio,
  atualize a spec no mesmo PR.
- **Código vence prosa**: ADR registra intenção histórica; se o código divergir depois, escreva
  novo ADR superseding — nunca edite o antigo.
- Idioma: **PT-BR** (termos técnicos em inglês), como todo `docs/specs/`.

## Quando usar / não usar

Use: decisão tomada (ou sendo selada) que merece memória — escolha de pacote, padrão
arquitetural, exceção a invariante, trade-off de tenancy/segurança.

Não use: decisão ainda aberta (discuta primeiro; ADR vem depois), escolhas triviais de
configuração, planejamento de implementação (isso é `tasks.md`).

## Campos obrigatórios (pergunte se faltar)

1. **Título** — frase nominal da decisão ("Usar X para Y"), nunca pergunta.
2. **Data** e **Status** — `Aceito | Proposto | Deprecado | Superseded por ADR-NNN`.
3. **Contexto** — as *forças* (restrições técnicas/negócio) que exigiram a decisão.
4. **Decisão** — o que foi escolhido e por quê **não** as alternativas (o "why not" é o valor).
5. **Consequências** — o que fica melhor E o que fica pior. ADR sem trade-off honesto não
   tem credibilidade.

Recomendados: drivers da decisão, opções consideradas com prós/cons, links (spec, PR, issue,
ADR relacionado).

## Template (MADR enxuto — default)

```markdown
# ADR-{NNN}: {Título}

- **Data**: YYYY-MM-DD
- **Status**: Aceito
- **Decisores**: {quem}

## Contexto e problema

{2-4 frases: que situação forçou a escolha; restrições em jogo.}

## Drivers da decisão

- {restrição/critério 1}
- {restrição/critério 2}

## Opções consideradas

- {Opção A} ✅ escolhida
- {Opção B}
- {Não fazer nada / status quo, quando relevante}

## Decisão

Escolhemos **{Opção A}** porque {racional amarrado aos drivers, incluindo por que B perdeu}.

## Consequências

- ✅ {o que melhora}
- ❌ {trade-off honesto}

## Links

- Spec afetada: {docs/specs/...}
- {PR / issue / ADR relacionado; Supersedes/Superseded by quando aplicável}
```

Para decisões muito simples, formato Y-Statement em um parágrafo é aceitável:
"No contexto de {situação}, diante de {restrição}, decidimos {escolha} para {objetivo},
aceitando {trade-off}."

## Checklist antes de finalizar

- [ ] Título é decisão, não pergunta; arquivo `NNN-kebab.md` com número sequencial.
- [ ] Contexto explica forças, não só o que foi feito.
- [ ] Consequências incluem o lado ruim.
- [ ] ≥2 opções reais consideradas (ou justifique por que só havia uma).
- [ ] Spec do domínio atualizada se a decisão muda contrato; link cruzado.
- [ ] 200-500 palavras — detalhe maior vai pra spec/subspec linkada.

## Anti-padrões

- **Editar ADR antigo** para mudar decisão → crie novo com `Superseded por ADR-NNN` no velho.
- **Contexto vago** ("precisávamos de X e escolhemos Y") → explique por que a alternativa não
  era obviamente melhor.
- **Duplicar a spec** → ADR conta a história; a regra viva fica na spec, linkada.
