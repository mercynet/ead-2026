#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
window="${OPS04_ERROR_WINDOW:-15m}"
threshold="${OPS04_5XX_THRESHOLD:-5}"
log_file="${OPS04_LOG_FILE:-}"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

if [[ -n "$log_file" ]]; then
    [[ -f "$log_file" ]] || { printf 'error_scan=NOT_CONFIGURED reason=log_file_missing\n' >&2; exit 1; }
    cp "$log_file" "$tmp_dir/log"
elif [[ -n "${OPS04_COMPOSE_PROJECT_NAME:-${COMPOSE_PROJECT_NAME:-}}" ]]; then
    project="${OPS04_COMPOSE_PROJECT_NAME:-${COMPOSE_PROJECT_NAME}}"
    compose_file="${OPS04_COMPOSE_FILE:-${COMPOSE_FILE:-compose.production.yaml}}"
    docker compose -f "$compose_file" -p "$project" logs --no-color --since "$window" app web scheduler db > "$tmp_dir/log"
else
    if [[ -n "${OPS04_ALERT_OWNER:-}" ]]; then
        "$script_dir/ops04-alert.sh" monitoring_unconfigured critical 'provide a log file or Compose log source'
    fi
    printf 'error_scan=NOT_CONFIGURED reason=no_log_or_compose_context\n' >&2
    exit 1
fi

five_xx="$(grep -Eo '"status"[[:space:]]*:[[:space:]]*5[0-9]{2}' "$tmp_dir/log" || true)"
five_xx="$(printf '%s\n' "$five_xx" | sed '/^$/d' | wc -l | tr -d ' ')"
critical="$(grep -Eic '"level_name"[[:space:]]*:[[:space:]]*"CRITICAL"|exception\.unhandled' "$tmp_dir/log" || true)"
if (( five_xx >= threshold )); then
    printf 'error_scan=FAIL five_xx=%s critical=%s threshold=%s reason=http_5xx_spike\n' "$five_xx" "$critical" "$threshold" >&2
    "$script_dir/ops04-alert.sh" http_5xx_spike critical 'contain traffic and inspect the failing route'
    exit 1
fi
if (( critical > 0 )); then
    printf 'error_scan=FAIL five_xx=%s critical=%s threshold=%s reason=critical_exception\n' "$five_xx" "$critical" "$threshold" >&2
    "$script_dir/ops04-alert.sh" critical_exception critical 'inspect the exception and correlate the request id'
    exit 1
fi

printf 'error_scan=PASS five_xx=%s critical=%s threshold=%s\n' "$five_xx" "$critical" "$threshold"
