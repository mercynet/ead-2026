#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base_url="${OPS04_BASE_URL:-http://localhost}"
specs="${OPS04_SYNTHETIC_SPECS:-${OPS04_SYNTHETIC_SPEC:-mzrt/tenant-lifecycle ops04/synthetic-pilot}}"
compose_project="${OPS04_COMPOSE_PROJECT_NAME:-${COMPOSE_PROJECT_NAME:-}}"
compose_file="${OPS04_COMPOSE_FILE:-${COMPOSE_FILE:-compose.production.yaml}}"
app_service="${OPS04_APP_SERVICE:-app}"

curl --fail --silent --show-error --max-time "${OPS04_HTTP_TIMEOUT_SECONDS:-10}" "${base_url%/}/up" >/dev/null || {
    "$script_dir/ops04-alert.sh" synthetic_app_unavailable critical 'restore app availability before synthetic run'
    exit 1
}
curl --fail --silent --show-error --max-time "${OPS04_HTTP_TIMEOUT_SECONDS:-10}" "${base_url%/}/readiness" >/dev/null || {
    "$script_dir/ops04-alert.sh" synthetic_readiness_failed critical 'repair readiness before synthetic run'
    exit 1
}

[[ -n "$compose_project" ]] || {
    "$script_dir/ops04-alert.sh" synthetic_runner_unconfigured critical 'provide the E2E Compose project and rerun synthetic smoke'
    exit 1
}
runtime_identity="$(docker compose -f "$compose_file" -p "$compose_project" exec -T "$app_service" sh -lc 'printf "env=%s\ndebug=%s\ndatabase=%s\ndisposable=%s\nkey_present=%s\n" "$APP_ENV" "$APP_DEBUG" "$DB_DATABASE" "$DB_DISPOSABLE" "$([ -n "${APP_KEY:-}" ] && printf true || printf false)"')"
printf '%s\n' "$runtime_identity" | grep -qx 'env=e2e' || {
    "$script_dir/ops04-alert.sh" synthetic_environment_invalid critical 'run synthetic against an APP_ENV=e2e disposable stack'
    exit 1
}
printf '%s\n' "$runtime_identity" | grep -qx 'debug=false' || {
    "$script_dir/ops04-alert.sh" synthetic_environment_invalid critical 'disable debug before synthetic validation'
    exit 1
}
printf '%s\n' "$runtime_identity" | grep -Eq '^database=.*e2e' || {
    "$script_dir/ops04-alert.sh" synthetic_database_invalid critical 'run synthetic against a database with an e2e marker'
    exit 1
}
printf '%s\n' "$runtime_identity" | grep -qx 'disposable=e2e' || {
    "$script_dir/ops04-alert.sh" synthetic_database_invalid critical 'set DB_DISPOSABLE=e2e on the synthetic stack'
    exit 1
}
printf '%s\n' "$runtime_identity" | grep -qx 'key_present=true' || {
    "$script_dir/ops04-alert.sh" synthetic_environment_invalid critical 'configure APP_KEY on the synthetic stack'
    exit 1
}
for spec in $specs; do
    if ! docker compose -f "$compose_file" -p "$compose_project" exec -T "$app_service" \
        php artisan e2e:run "$spec" --base=http://localhost --timeout="${OPS04_HTTP_TIMEOUT_SECONDS:-10}"; then
        "$script_dir/ops04-alert.sh" synthetic_journey_failed critical "rerun failed synthetic spec $spec"
        exit 1
    fi
done
printf 'synthetic=PASS strategy=ephemeral-cleanup specs=%s timestamp=%s\n' "$specs" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
