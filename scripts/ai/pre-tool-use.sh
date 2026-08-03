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

# Layout legado: código de produto vive em app/Modules/<M>/... (AGENTS.md → Arquitetura).
# app/Http e app/Financial existem vazias no disco e convidam ao erro.
case "$file" in
*/app/Http/* | */app/Models/* | */app/Actions/* | */app/Policies/* | */app/Financial/*)
    block "layout legado ($file): código de produto vive em app/Modules/<M>/{Http,Models,Actions,Policies}"
    ;;
*/routes/api.php)
    block "não existe routes/api.php global: a rota vive em app/Modules/<M>/Routes/{api,admin,mzrt}.php"
    ;;
esac

# Env local/segredo: agente não edita. Ajuste o .example e peça ao usuário.
case "$file" in
*/.env | */.env.e2e | */.env.local | */.env.production)
    block "não edite $file (config/segredo local): altere o .example correspondente e peça ao usuário"
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

# PHP toolchain roda no container. `php`, `pint`, `phpstan`, `pest` e `composer` a seco
# usam o binário do host (versão/extensões/DB diferentes) — force Sail ou docker exec.
# Heredoc fica de fora: o corpo é texto (ex.: mensagem de commit citando um comando).
if ! printf '%s' "$cmd" | grep -q '<<' &&
    { at_cmd_pos '(php|composer)[[:space:]]' || at_cmd_pos '(\./)?vendor/bin/(pint|phpstan|pest|phpinsights)'; }; then
    printf '%s' "$cmd" | grep -Eq '(sail|docker[[:space:]]+exec)' ||
        block "toolchain PHP no host: use ./vendor/bin/sail <cmd> (ou docker exec ead2026-laravel.test-1 <cmd>)"
fi

# Baseline do Larastan não é lixeira: regenerar esconde erro novo junto com o antigo.
printf '%s' "$cmd" | grep -q -- '--generate-baseline' &&
    block "não regenere o phpstan-baseline: corrija o erro ou adicione a entrada mínima à mão"

# --no-verify pula pre-commit (validate-harness + audit de deps) e pre-push (scan de vendor).
printf '%s' "$cmd" | grep -Eq 'git[[:space:]]+(commit|push)[^;&|]*--no-verify' &&
    block "--no-verify pula os git hooks do projeto (validate-harness, security:audit-deps)"

exit 0
