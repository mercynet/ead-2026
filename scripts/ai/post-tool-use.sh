#!/usr/bin/env bash
# PostToolUse hook: auto-format edited PHP via Pint (dirty) inside the app container.
# Best-effort, never blocks. Pint only touches PHP, so non-PHP edits are ignored.
set -uo pipefail

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)

case "$file" in
*.php)
    docker exec ead2026-laravel.test-1 vendor/bin/pint --dirty --format agent >/dev/null 2>&1 || true
    ;;
esac

exit 0
