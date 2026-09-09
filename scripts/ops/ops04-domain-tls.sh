#!/usr/bin/env bash

set -euo pipefail

mode="${1:-live}"
base_url="${OPS04_BASE_URL:-${APP_URL:-}}"
timeout="${OPS04_HTTP_TIMEOUT_SECONDS:-10}"

[[ "$base_url" =~ ^https://[^/]+$ ]] || { printf 'domain_tls=FAIL reason=APP_URL_must_be_https\n' >&2; exit 1; }
host="${base_url#https://}"
hostname="${host%%:*}"
port=443
if [[ "$host" == *:* ]]; then
    port="${host##*:}"
fi
http_base_url="${OPS04_HTTP_BASE_URL:-http://$hostname}"

if [[ "$mode" == structural ]]; then
    [[ "${CADDY_TLS_DIRECTIVE:-}" != internal || "${APP_ENV:-}" == rehearsal ]] || {
        printf 'domain_tls=FAIL reason=internal_tls_is_rehearsal_only\n' >&2
        exit 1
    }
    printf 'domain_tls=STRUCTURAL_PASS hostname=%s redirect=https certificate=external-validation-required\n' "$host"
    exit 0
fi

[[ "$mode" == live ]] || { printf 'usage: ops04-domain-tls.sh [live|structural]\n' >&2; exit 2; }
http_headers="$(curl --silent --show-error --max-time "$timeout" --dump-header - --output /dev/null "${http_base_url%/}/health" || true)"
printf '%s\n' "$http_headers" | grep -Eiq '^HTTP/[0-9.]+ 30[1278][[:space:]]' || {
    printf 'domain_tls=FAIL reason=http_to_https_redirect_missing\n' >&2
    exit 1
}
printf '%s\n' "$http_headers" | grep -Eiq '^location:[[:space:]]*https://' || {
    printf 'domain_tls=FAIL reason=redirect_target_not_https\n' >&2
    exit 1
}

https_headers="$(curl --silent --show-error --fail --max-time "$timeout" --dump-header - --output /dev/null "$base_url/up")"
for header in strict-transport-security x-content-type-options x-frame-options; do
    printf '%s\n' "$https_headers" | grep -Eiq "^${header}:" || {
        printf 'domain_tls=FAIL reason=security_header_missing header=%s\n' "$header" >&2
        exit 1
    }
done

openssl s_client -connect "$hostname:$port" -servername "$hostname" -verify_hostname "$hostname" </dev/null 2>/dev/null | grep -q 'Verify return code: 0 (ok)' || {
    printf 'domain_tls=FAIL reason=certificate_hostname_or_chain_invalid\n' >&2
    exit 1
}

printf 'domain_tls=PASS hostname=%s https=verified headers=verified\n' "$hostname"
