#!/usr/bin/env bash

set -euo pipefail

condition="${1:?condition is required}"
severity="${2:?severity is required}"
action="${3:?immediate action is required}"
context="${4:-${OPS04_ALERT_CONTEXT:-none}}"
owner="${OPS04_ALERT_OWNER:-unassigned}"
channel="${OPS04_ALERT_CHANNEL:-stdout-exit-code}"
safe_text_pattern='^[A-Za-z0-9._:/ -]+$'
email_pattern='^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
dedup_window="${OPS04_ALERT_DEDUP_SECONDS:-300}"
state_dir="${OPS04_ALERT_STATE_DIR:-}"

[[ "$condition" =~ ^[A-Za-z0-9_.:-]+$ ]] || { printf 'invalid alert condition\n' >&2; exit 2; }
[[ "$severity" =~ ^(info|warning|critical)$ ]] || { printf 'invalid alert severity\n' >&2; exit 2; }
[[ "$owner" =~ ^[A-Za-z0-9._@:/-]+$ ]] || { printf 'invalid alert owner\n' >&2; exit 2; }
[[ "$channel" =~ $safe_text_pattern ]] || { printf 'invalid alert channel\n' >&2; exit 2; }
[[ "$action" =~ $safe_text_pattern ]] || { printf 'invalid alert action\n' >&2; exit 2; }
[[ "$context" =~ $safe_text_pattern ]] || { printf 'invalid alert context\n' >&2; exit 2; }
[[ "$dedup_window" =~ ^[0-9]+$ ]] || { printf 'invalid deduplication window\n' >&2; exit 2; }

timestamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
epoch="$(date -u +%s)"
fingerprint="$(printf '%s\n' "$condition|$severity|$action|$context" | sha256sum | awk '{print $1}')"
deduplicated=false

if [[ -n "$state_dir" ]]; then
    if mkdir -p "$state_dir" 2>/dev/null; then
        state_file="$state_dir/$fingerprint.last"
        if [[ -f "$state_file" ]]; then
            last_epoch="$(sed -n '1p' "$state_file" 2>/dev/null || printf 0)"
            if [[ "$last_epoch" =~ ^[0-9]+$ ]] && (( epoch - last_epoch < dedup_window )); then
                deduplicated=true
            fi
        fi
        if [[ "$deduplicated" == false ]]; then
            printf '%s\n' "$epoch" > "$state_file"
        fi
    else
        printf 'alert=DEDUP_UNAVAILABLE reason=state_directory_unwritable\n' >&2
    fi
fi

delivery_status=not_configured
delivery_failed=false

if [[ -n "${OPS04_ALERT_WEBHOOK_URL:-}" ]]; then
    payload="{\"signal\":\"$condition\",\"severity\":\"$severity\",\"timestamp\":\"$timestamp\",\"context\":\"$context\",\"action\":\"$action\",\"owner\":\"$owner\",\"deduplicated\":$deduplicated}"
    if [[ "$deduplicated" == false ]] && curl --fail --silent --show-error --max-time "${OPS04_ALERT_TIMEOUT_SECONDS:-10}" \
        -H 'Content-Type: application/json' \
        --data "$payload" "$OPS04_ALERT_WEBHOOK_URL" >/dev/null 2>&1; then
        delivery_status=webhook_sent
    elif [[ "$deduplicated" == false ]]; then
        printf 'alert=WEBHOOK_FAILED signal=%s\n' "$condition" >&2
        delivery_failed=true
    fi
fi

email_to="${OPS04_ALERT_EMAIL_TO:-}"
if [[ -n "$email_to" ]]; then
    [[ "$email_to" =~ $email_pattern ]] || { printf 'invalid alert email recipient\n' >&2; exit 2; }
    sendmail_bin="${OPS04_SENDMAIL_BIN:-/usr/sbin/sendmail}"
    [[ "$sendmail_bin" =~ ^/[A-Za-z0-9._/-]+$ ]] || { printf 'invalid sendmail path\n' >&2; exit 2; }
    if [[ "$deduplicated" == false ]] && [[ -x "$sendmail_bin" ]] && printf 'To: %s\nFrom: %s\nSubject: [%s] %s\nContent-Type: text/plain; charset=utf-8\n\nSignal: %s\nSeverity: %s\nTimestamp: %s\nContext: %s\nAction: %s\n' \
        "$email_to" "${OPS04_ALERT_EMAIL_FROM:-no-reply@localhost}" "$severity" "$condition" "$condition" "$severity" "$timestamp" "$context" "$action" | "$sendmail_bin" -t; then
        delivery_status=email_sent
    elif [[ "$deduplicated" == false ]]; then
        printf 'alert=EMAIL_FAILED signal=%s\n' "$condition" >&2
        delivery_failed=true
    fi
fi

if [[ "$deduplicated" == true ]]; then
    delivery_status=deduplicated
elif [[ "$delivery_failed" == true ]]; then
    delivery_status=failed
fi

exit_code=1
printf 'alert_result={"problem_detected":true,"signal":"%s","severity":"%s","timestamp":"%s","context":"%s","action":"%s","owner":"%s","deduplicated":%s,"delivery_status":"%s","channel":"%s","exit_code":%s}\n' \
    "$condition" "$severity" "$timestamp" "$context" "$action" "$owner" "$deduplicated" "$delivery_status" "$channel" "$exit_code"
printf 'alert_result={"problem_detected":true,"signal":"%s","severity":"%s","timestamp":"%s","context":"%s","action":"%s","owner":"%s","deduplicated":%s,"delivery_status":"%s","channel":"%s","exit_code":%s}\n' \
    "$condition" "$severity" "$timestamp" "$context" "$action" "$owner" "$deduplicated" "$delivery_status" "$channel" "$exit_code" >&2

if [[ "$delivery_status" == not_configured || "$delivery_failed" == true ]]; then
    exit 1
fi

exit 1
