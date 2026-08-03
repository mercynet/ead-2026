#!/usr/bin/env bash
# SessionStart hook: orientação mínima e factual, sem o agente gastar turno pedindo.
# git é o árbitro: branch, sujeira do working tree, último commit + handoff do STATE.md.
set -uo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root="${CLAUDE_PROJECT_DIR:-$(cd "$script_dir/../.." && pwd)}"
cd "$root" 2>/dev/null || exit 0

echo "== EAD2026 — estado da sessão (contrato: AGENTS.md) =="

if command -v git >/dev/null 2>&1; then
    branch=$(git branch --show-current 2>/dev/null)
    dirty=$(git status --porcelain --untracked-files=all 2>/dev/null | wc -l | tr -d ' ')
    ahead=$(git rev-list --count '@{upstream}..HEAD' 2>/dev/null || echo '?')
    echo "branch=${branch:-?} · working tree=${dirty} arquivo(s) sujo(s) · commits à frente do upstream=${ahead}"
    echo "HEAD: $(git log -1 --pretty='%h %s' 2>/dev/null)"
fi

if [ -r docs/STATE.md ]; then
    echo
    echo "-- docs/STATE.md → próximos passos"
    awk '/^## Próximos passos/{flag=1;next} /^## /{flag=0} flag' docs/STATE.md | sed '/^[[:space:]]*$/d' | head -12
fi

if [ -x scripts/ai/skill-router.sh ]; then
    echo
    bash scripts/ai/skill-router.sh list
fi

echo
echo "Guardrails automáticos: skill router (prompt/edit), Pint pós-edit, invariantes do diff no fim do turno."

exit 0
