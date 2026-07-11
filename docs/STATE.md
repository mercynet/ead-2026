# State — Sessão Atual

> Arquivo **efêmero**: reflete o foco da sessão atual e os próximos 1-3 passos. Não é histórico;
> sobrescreva à vontade. Status detalhado por domínio vive nos `tasks.md`. Atualizado pela skill
> `context-checkpoint` ao fim de cada task. Após um `clear`, retome lendo `AGENTS.md` + este arquivo.

## Sessão

- 2026-07-11 — Criada `.agents/skills/validate-ai-work/SKILL.md` para revisão independente de
  entregas, diffs, commits e PRs produzidos por outra IA. Fonte canônica disponível a Claude Code,
  Codex e OpenCode pelos symlinks de `skills`. Frontmatter e links passaram no validator; harness
  geral continua bloqueado por path absoluto preexistente em `.claude/settings.json`.
- 2026-07-11 — **E2E da emissão de certificado validado contra o app rodando** (`11dc10e`):
  spec `tests/e2e-http/learning/lessons-progress.php`, 11/11 casos verdes via
  `php artisan e2e:run learning/lessons-progress --base=http://localhost` (dentro do container a
  porta é 80; `APP_URL` 8099 é o mapeamento do host). Achado incorporado ao spec: primeiro report
  de progresso responde **201** (`wasRecentlyCreated`), updates seguintes 200. Banco dev migrado
  (faltavam `questions_snapshot` + `course_id` de certificates).

## Próximos passos (1-3)

1. Revisar e, se desejado, commitar a nova skill e este checkpoint.
2. Corrigir separadamente o path absoluto preexistente em `.claude/settings.json` para deixar
   `python3 scripts/ai/validate-harness.py` totalmente verde.
3. Retomar prioridades de domínio apontadas nos respectivos `tasks.md`.

## Decisões abertas

- Corrigir agora ou depois o path absoluto preexistente em `.claude/settings.json`?

## Último commit

- `11dc10e` (spec E2E lessons-progress) — branch `harness/specs-foundation`, sincronizada
  com origin.
