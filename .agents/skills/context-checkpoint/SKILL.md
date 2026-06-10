---
name: context-checkpoint
description: Rode ao concluir uma task ou em qualquer limite de task, antes de decidir seguir. Mede a pressão da janela de contexto, persiste o estado da sessão em docs/STATE.md e recomenda continue | clear | waiting_for_user. Ative depois de terminar uma task, quando o contexto parecer grande, ou quando o usuário perguntar se deve continuar/limpar.
---

# Context Checkpoint

Disciplina cross-agent (Claude Code, OpenCode, Codex) para gerenciar a janela de contexto entre
tasks, usando `docs/STATE.md` como memória de handoff otimizada.

## Quando usar

- Ao **concluir uma task** (commit feito, testes verdes).
- Quando o contexto parecer **grande/pesado**.
- Quando o usuário perguntar "continuo / limpo / paro?".

## Procedimento

1. **Atualize `docs/STATE.md`** (efêmero — sobrescreva, não acumule histórico). Mantenha enxuto:
   - `Sessão`: 1 linha do que acabou de ser feito.
   - `Próximos passos (1-3)`: o que vem a seguir, acionável.
   - `Decisões abertas`: o que falta decidir (ou "nenhuma").
   - `Último commit`: hash + branch (âncora para retomar).
   - NUNCA duplique o status fino dos `tasks.md` — só ponteiros.

2. **Meça a pressão de contexto.**
   - Se o tool expõe o indicador (Claude Code mostra % de contexto usado), use o número.
   - Se não expõe, **estime** por heurística e diga que é estimativa: muitas tool calls,
     leituras/saídas grandes acumuladas, sessão longa, releitura repetida de contexto.

3. **Recomende** uma das três:
   - `continue` — há folga de contexto **e** o próximo passo é autônomo (definido, sem depender
     do usuário). Siga sem limpar.
   - `clear` — contexto **alto/perto do limite** **e** o `STATE.md` está fresco (handoff completo).
     Recomende limpar/compactar o contexto e **retomar lendo `AGENTS.md` + `docs/STATE.md`** — é
     barato porque o STATE carrega o essencial. Em dúvida entre `continue` e `clear` com STATE
     fresco, prefira `clear`.
   - `waiting_for_user` — precisa de **validação, decisão ou aprovação** do usuário (decisão de
     produto, revisão de PR, escolha de rumo, algo irreversível).

4. **Emita o bloco no fim da resposta:**

   ```
   CONTEXT CHECKPOINT
   context: <baixo | médio | alto | ~N%>   (marque "estimado" se não houver número)
   state: docs/STATE.md atualizado
   recommendation: continue | clear | waiting_for_user
   reason: <1 linha>
   ```

## Regras

- `STATE.md` é a memória de retomada — depois de um `clear`, reconstruir o contexto = ler
  `AGENTS.md` (contrato) + `docs/STATE.md` (onde paramos). Mantenha-o suficiente para isso.
- Seja honesto: se não há número real de tokens, marque `estimado`.
- Não invente urgência: `continue` é o default quando há folga e trabalho autônomo claro.
