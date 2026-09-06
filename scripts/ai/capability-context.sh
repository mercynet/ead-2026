#!/usr/bin/env bash
# Advisory resolver for domain capability context.
#
# Modes:
#   prompt  stdin = UserPromptSubmit JSON
#   tool    stdin = PreToolUse JSON
#   list    print every declared bundle
set -uo pipefail

script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
root="${CLAUDE_PROJECT_DIR:-$(cd "$script_dir/../.." && pwd)}"
manifest="$root/.agents/capability-context.json"
mode="${1:-prompt}"

command -v jq >/dev/null 2>&1 || exit 0
[ -r "$manifest" ] || exit 0

if [ "$mode" = "list" ]; then
    echo "Capability context bundles (.agents/capability-context.json):"
    jq -r '.bundles[] | "- \(.id) — \(.domain): \(.summary)"' "$manifest"
    echo "Resolver: scripts/ai/capability-context.sh (advisory; não bloqueia implementação)"
    exit 0
fi

input=$(cat)
case "$mode" in
prompt)
    haystack=$(printf '%s' "$input" | jq -r '.prompt // empty' 2>/dev/null)
    ;;
tool)
    haystack=$(printf '%s' "$input" | jq -r '[.tool_input.file_path // empty, .tool_input.command // empty] | join(" ")' 2>/dev/null)
    ;;
*)
    exit 0
    ;;
esac

[ -z "${haystack//[[:space:]]/}" ] && exit 0

matches=''
matches=$(jq -c --arg haystack "$haystack" '
    def matches($patterns):
        any(($patterns // [])[]; . as $pattern | ($haystack | test($pattern; "i")));
    .bundles[]
    | select(matches(.match.prompt) or matches(.match.paths))
' "$manifest" 2>/dev/null) || exit 0

[ -z "$matches" ] && exit 0

echo "CAPABILITY CONTEXT ROUTER — advisory (não bloqueia):"
while IFS= read -r bundle; do
    [ -z "$bundle" ] && continue
    id=$(printf '%s' "$bundle" | jq -r '.id')
    echo ""
    echo "[$id] $(printf '%s' "$bundle" | jq -r '.domain')"
    printf '%s\n' "$bundle" | jq -r '.summary, ("personas: " + (.personas | join(", "))), "classification: " + .classification.layer'

    echo "specs obrigatórias:"
    printf '%s\n' "$bundle" | jq -r '.specs[] | "- " + .'

    echo "skills/rules relevantes:"
    printf '%s\n' "$bundle" | jq -r '.skills[] | "- skill: " + .'
    printf '%s\n' "$bundle" | jq -r '.rules[] | "- rule: " + .'

    echo "invariantes críticas:"
    printf '%s\n' "$bundle" | jq -r '.invariants[] | "- " + .'

    echo "testes/gates relacionados:"
    printf '%s\n' "$bundle" | jq -r '.tests[] | "- " + .'

    gates=$(printf '%s' "$bundle" | jq -r --arg haystack "$haystack" '
        def matches($patterns):
            any(($patterns // [])[]; . as $pattern | ($haystack | test($pattern; "i")));
        .decision_gates[]?
        | select(matches(.match.prompt) or matches(.match.paths))
        | "- " + .status + ": " + .id + " — " + .summary
    ' 2>/dev/null)
    if [ -n "$gates" ]; then
        echo "decisões humanas aplicáveis:"
        printf '%s\n' "$gates"
    fi
done <<<"$matches"

echo ""
echo "Leia as specs listadas antes de decidir. HUMAN_DECISION_REQUIRED é um gate advisory: a ausência de decisão não autoriza inferência."
