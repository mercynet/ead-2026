#!/usr/bin/env bash
# PreToolUse hook: block destructive footguns. Exit 2 = block (message to stderr,
# shown to the agent). git push is intentionally NOT blocked (authorized per-call).
#
# Patterns are anchored to COMMAND POSITION (start of line or after ; & |) so that
# the same words appearing inside a quoted argument (e.g. a commit message that
# *describes* a footgun) do not trigger a false positive.
set -uo pipefail

input=$(cat)
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)

block() {
    echo "BLOCKED (footgun): $1" >&2
    exit 2
}

# Edits into generated/managed directories
case "$file" in
*/vendor/* | */node_modules/* | */bootstrap/cache/* | */.scribe/*)
    block "edit in generated/managed dir: $file"
    ;;
esac

[ -z "$cmd" ] && exit 0

# Matches a dangerous command only at command position (start, or after ; & |).
at_cmd_pos() { printf '%s' "$cmd" | grep -Eq "(^|[;&|][[:space:]]*)$1"; }

at_cmd_pos 'git[[:space:]]+reset[[:space:]]+--hard' && block "git reset --hard"
at_cmd_pos 'git[[:space:]]+clean[[:space:]]+-[a-zA-Z]*f' && block "git clean -f"
at_cmd_pos 'rm[[:space:]]+-[a-zA-Z]*r[a-zA-Z]*f|rm[[:space:]]+-[a-zA-Z]*f[a-zA-Z]*r' && block "rm -rf"
at_cmd_pos 'composer[[:space:]]+update([[:space:]]|$)' && block "composer update (use composer require/install)"

# DB wipe via artisan, only when NOT explicitly targeting the testing env.
if printf '%s' "$cmd" | grep -Eq 'artisan[[:space:]]+(migrate:(fresh|refresh)|db:wipe)'; then
    printf '%s' "$cmd" | grep -q -- '--env=testing' || block "migrate:fresh/refresh/db:wipe without --env=testing"
fi

exit 0
