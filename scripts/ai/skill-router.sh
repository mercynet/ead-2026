#!/usr/bin/env bash
# Skill router: mapeia caminho editado / comando / texto do pedido para as skills
# obrigatórias daquele domínio. Fonte única: .agents/skills/routing.json.
#
# Modos:
#   tool    (default) stdin = JSON de hook PreToolUse  → casa .tool_input.file_path + .command
#   prompt            stdin = JSON de hook UserPromptSubmit → casa .prompt
#   list              sem stdin; imprime a tabela inteira (agentes sem hook)
#
# Sempre exit 0: o router informa, nunca bloqueia.
set -uo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root="${CLAUDE_PROJECT_DIR:-$(cd "$script_dir/../.." && pwd)}"
map="$root/.agents/skills/routing.json"
mode="${1:-tool}"

command -v jq >/dev/null 2>&1 || exit 0
[ -r "$map" ] || exit 0

if [ "$mode" = "list" ]; then
    echo "Skills roteadas automaticamente (.agents/skills/routing.json):"
    jq -r '.rules[] | "- \(.skill) — \(.why)"' "$map"
    echo "Sob demanda (sem gatilho automático): $(jq -r '.manual | join(", ")' "$map")"
    exit 0
fi

input=$(cat)

case "$mode" in
prompt)
    field=prompt
    haystack=$(printf '%s' "$input" | jq -r '.prompt // empty' 2>/dev/null)
    ;;
*)
    field=paths
    haystack=$(printf '%s' "$input" | jq -r '[.tool_input.file_path // empty, .tool_input.command // empty] | join(" ")' 2>/dev/null)
    ;;
esac

[ -z "${haystack//[[:space:]]/}" ] && exit 0

rules=$(jq -r --arg field "$field" '
    .rules[]
    | select(((.[$field] // []) | length) > 0)
    | [.skill, .why, ((.[$field]) | join("|"))]
    | @tsv
' "$map" 2>/dev/null)

[ -z "$rules" ] && exit 0

hits=()

while IFS=$'\t' read -r skill why pattern; do
    [ -z "${skill:-}" ] && continue
    [ -z "${pattern:-}" ] && continue

    if printf '%s' "$haystack" | grep -Eiq -- "$pattern" 2>/dev/null; then
        hits+=("- ${skill} — ${why} → .agents/skills/${skill}/SKILL.md")
    fi
done <<<"$rules"

[ ${#hits[@]} -eq 0 ] && exit 0

echo "SKILL ROUTER — skills obrigatórias para este trabalho (leia o SKILL.md ANTES de escrever/decidir; vale para subagentes):"
printf '%s\n' "${hits[@]}"

exit 0
