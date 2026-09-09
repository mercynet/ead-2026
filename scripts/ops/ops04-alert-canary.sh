#!/usr/bin/env bash

set -euo pipefail

email_pattern='^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'

if [[ -n "${OPS04_ALERT_WEBHOOK_URL:-}" ]]; then
    [[ "$OPS04_ALERT_WEBHOOK_URL" == https://* ]] || {
        printf 'alert_delivery_canary=FAIL reason=webhook_must_use_https\n' >&2
        exit 1
    }
    payload='{"signal":"activation_canary","severity":"info","action":"confirm alert delivery"}'
    curl --fail --silent --show-error --max-time "${OPS04_ALERT_TIMEOUT_SECONDS:-10}" \
        -H 'Content-Type: application/json' \
        --data "$payload" "$OPS04_ALERT_WEBHOOK_URL" >/dev/null || {
        printf 'alert_delivery_canary=FAIL reason=webhook_delivery_failed\n' >&2
        exit 1
    }
    printf 'alert_delivery_canary=PASS channel=webhook\n'
    exit 0
fi

email_to="${OPS04_ALERT_EMAIL_TO:-}"
if [[ -n "$email_to" ]]; then
    [[ "$email_to" =~ $email_pattern ]] || {
        printf 'alert_delivery_canary=FAIL reason=email_recipient_invalid\n' >&2
        exit 1
    }
    email_from="${OPS04_ALERT_EMAIL_FROM:-no-reply@localhost}"
    [[ "$email_from" =~ $email_pattern ]] || {
        printf 'alert_delivery_canary=FAIL reason=email_sender_invalid\n' >&2
        exit 1
    }
    sendmail_bin="${OPS04_SENDMAIL_BIN:-/usr/sbin/sendmail}"
    [[ "$sendmail_bin" =~ ^/[A-Za-z0-9._/-]+$ && -x "$sendmail_bin" ]] || {
        printf 'alert_delivery_canary=FAIL reason=sendmail_unavailable\n' >&2
        exit 1
    }
    printf 'To: %s\nFrom: %s\nSubject: activation alert canary\nContent-Type: text/plain; charset=utf-8\n\nalert delivery canary\n' \
        "$email_to" "$email_from" | "$sendmail_bin" -t || {
        printf 'alert_delivery_canary=FAIL reason=email_delivery_failed\n' >&2
        exit 1
    }
    printf 'alert_delivery_canary=PASS channel=email\n'
    exit 0
fi

printf 'alert_delivery_canary=FAIL reason=provider_not_configured\n' >&2
exit 1
