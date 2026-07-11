---
name: spec-task-planning
description: Planejar e atualizar tasks em specs de domínio — ativa ao iniciar trabalho num domínio, ao derivar próxima task, ou ao atualizar status de tarefa concluída. Lê fluxo canônico (AGENTS.md → specs → ROADMAP/STATE) e garante que tasks.md refletem o status fino e próximas ações alinhadas ao roadmap.
---

# Spec-Task-Planning

Disciplina de leitura, planejamento e rastreamento de tarefas alinhadas às especificações de domínio.

## Quando usar

- **Ao iniciar trabalho num domínio** — antes de qualquer implementação.
- **Ao derivar a próxima task** — sabendo qual é o menor incremento testável e qual task atacar.
- **Ao concluir uma task** — atualizar status fino em `tasks.md` + indicar próxima.

## Procedimento

### Leitura (fluxo canônico)

1. **`AGENTS.md`** — contrato do projeto (stack, invariantes, execução).
2. **`docs/specs/README.md`** — índice e estrutura de domínios.
3. **`docs/specs/<NN-domínio>/spec.md`** — o contrato durável (sem status).
4. **`docs/specs/<NN-domínio>/tasks.md`** — estado mutável (feito/progresso/pendente/revisão).
5. **`docs/specs/<NN-domínio>/subspecs/*.md`** — detalhe quando citado em tasks.
6. **`docs/ROADMAP.md`** — fases cross-domain e milestones.
7. **`docs/STATE.md`** — sessão atual e próximos passos (ponteiros, não duplicação).

### Derivar próxima task

1. Leia o `tasks.md` — entenda o que ficou em "Pending" ou "In Progress".
2. Escolha o **menor incremento vertical testável** que:
   - Respeita dependências (não tente publicar antes de criar).
   - Alinha às fases do `ROADMAP.md` (ex.: Fase 2 = CRUD catálogo antes de Fase 3 = fluxos aluno).
   - Tem **critério de aceite claro** (≥1 teste, rota ou model+migration).
   - Fica em **1 endpoint, 1 model+migration, ou 1 ação** (slice fino).
3. Registre em `tasks.md` (`## In Progress`) com uma linha descritiva.

### Atualizar ao concluir

1. Mude a task de `## In Progress` para `## Done` (checkbox `[x]`).
2. Se houver próxima candidata clara, mova-a para `## In Progress`.
3. Rode `tests/Architecture --compact` como árbitro de invariantes: `docker exec ead2026-laravel.test-1 php artisan test --testsuite=Architecture --compact`.
4. Chame `context-checkpoint` ao fim — atualiza `docs/STATE.md` e recomenda `continue | clear | waiting_for_user`.

## Regras

- **Economia de modelo** (ver `AGENTS.md`): ao planejar, classifique cada task — mecânica e bem
  especificada (boilerplate, varredura, rascunho de doc) → delegue a subagente de modelo barato
  (ex.: Haiku); julgamento (arquitetura, decisão de domínio, debugging) → modelo principal.
  Barato rascunha, caro revisa — nunca commitar saída de subagente sem revisão.
- **Spec.md é contrato**: nunca coloque checkbox ou status nela. Status vive só em `tasks.md`.
- **Código vence prosa**: se a implementação conflitar com spec, corrija a spec (e a task que a testava).
- **Invariantes como árbitro**: antes de marcar concluída, valide com `tests/Architecture`.
- **Não duplique**: STATE.md só faz ponteiros para tasks.md — não reescreva o status fino.
- **Domínio em PT-BR, código em EN**: specs e discussions em português; identificadores e permissões em inglês.
