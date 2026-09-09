#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base_url="${OPS04_SYNTHETIC_BASE_URL:-${OPS04_BASE_URL:-http://localhost}}"
specs="${OPS04_SYNTHETIC_SPECS:-${OPS04_SYNTHETIC_SPEC:-mzrt/tenant-lifecycle ops04/synthetic-pilot}}"
compose_project="${OPS04_SYNTHETIC_COMPOSE_PROJECT_NAME:-${OPS04_COMPOSE_PROJECT_NAME:-${COMPOSE_PROJECT_NAME:-}}}"
compose_files="${OPS04_SYNTHETIC_COMPOSE_FILES:-${OPS04_COMPOSE_FILE:-${COMPOSE_FILE:-compose.production.yaml}}}"
app_service="${OPS04_SYNTHETIC_APP_SERVICE:-${OPS04_APP_SERVICE:-app}}"
compose_env_args=()
if [[ -n "${OPS04_SYNTHETIC_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS04_SYNTHETIC_ENV_FILE")
fi
compose_args=(docker compose "${compose_env_args[@]}")
IFS=':' read -r -a compose_file_list <<< "$compose_files"
for compose_file in "${compose_file_list[@]}"; do
    compose_args+=(-f "$compose_file")
done
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

curl --fail --silent --show-error --max-time "${OPS04_HTTP_TIMEOUT_SECONDS:-10}" "${base_url%/}/up" >/dev/null || {
    "$script_dir/ops04-alert.sh" synthetic_app_unavailable critical 'restore app availability before synthetic run'
    exit 1
}
readiness_file="$tmp_dir/readiness.json"
curl --fail --silent --show-error --max-time "${OPS04_HTTP_TIMEOUT_SECONDS:-10}" \
    --output "$readiness_file" "${base_url%/}/readiness" || {
    "$script_dir/ops04-alert.sh" synthetic_readiness_failed critical 'repair readiness before synthetic run'
    exit 1
}
readiness_payload="$(tr -d '[:space:]' < "$readiness_file")"
[[ "$readiness_payload" == *'"status":"ready"'* ]] || {
    "$script_dir/ops04-alert.sh" synthetic_readiness_failed critical 'repair readiness before synthetic run'
    exit 1
}
for check in app db storage migration_manifest outbox; do
    [[ "$readiness_payload" == *"\"$check\":{\"status\":\"pass\""* ]] || {
        "$script_dir/ops04-alert.sh" synthetic_readiness_failed critical 'repair readiness before synthetic run'
        exit 1
    }
done
[[ "$readiness_payload" == *'"queue":{"status":"pass"'* || "$readiness_payload" == *'"queue":{"status":"not_required"'* ]] || {
    "$script_dir/ops04-alert.sh" synthetic_readiness_failed critical 'repair readiness before synthetic run'
    exit 1
}

[[ -n "$compose_project" ]] || {
    "$script_dir/ops04-alert.sh" synthetic_runner_unconfigured critical 'provide the E2E Compose project and rerun synthetic smoke'
    exit 1
}
runtime_identity="$("${compose_args[@]}" -p "$compose_project" exec -T "$app_service" sh -lc 'printf "env=%s\ndebug=%s\ndatabase=%s\ndisposable=%s\nkey_present=%s\n" "$APP_ENV" "$APP_DEBUG" "$DB_DATABASE" "$DB_DISPOSABLE" "$([ -n "${APP_KEY:-}" ] && printf true || printf false)"')"
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
run_spec() {
    local spec="$1"
    local output

    if ! output="$("${compose_args[@]}" -p "$compose_project" exec -T "$app_service" \
        php artisan e2e:run "$spec" --base=http://localhost --timeout="${OPS04_HTTP_TIMEOUT_SECONDS:-10}" 2>&1)"; then
        printf '%s\n' "$output"
        return 1
    fi

    printf '%s\n' "$output"
    printf '%s\n' "$output" | grep -Eq 'Resultado: [1-9][0-9]* passou, 0 falhou\.'
}

for spec in $specs; do
    if ! run_spec "$spec"; then
        "$script_dir/ops04-alert.sh" synthetic_journey_failed critical "rerun failed synthetic spec $spec"
        exit 1
    fi
done
printf 'synthetic=PASS strategy=ephemeral-cleanup specs=%s timestamp=%s\n' "$specs" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
