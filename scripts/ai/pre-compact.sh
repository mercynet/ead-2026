#!/usr/bin/env bash
# PreCompact hook: contexto vai ser comprimido — persista o handoff antes de perder detalhe.
# O repo já tem o formato canônico (docs/STATE.md) e a skill que o mantém (context-checkpoint).
set -uo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root="${CLAUDE_PROJECT_DIR:-$(cd "$script_dir/../.." && pwd)}"
cd "$root" 2>/dev/null || exit 0

echo "PRE-COMPACT: antes de comprimir, persista o handoff em docs/STATE.md (skill context-checkpoint):"
echo "- Sessão: o que foi entregue, com evidência (commit/teste), não narrativa."
echo "- Próximos passos (1-3), acionáveis."
echo "- Decisões abertas / diferidos."
echo "- Distinga committed / staged / working tree — git é o árbitro."

if command -v git >/dev/null 2>&1; then
    dirty=$(git status --porcelain --untracked-files=all 2>/dev/null | wc -l | tr -d ' ')
    [ "$dirty" != "0" ] && echo "Atenção: ${dirty} arquivo(s) não commitados — descreva-os como working tree, não como pronto."
fi

exit 0
