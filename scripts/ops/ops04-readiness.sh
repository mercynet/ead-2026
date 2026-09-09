#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base_url="${OPS04_BASE_URL:-http://localhost}"
compose_file="${OPS04_COMPOSE_FILE:-${COMPOSE_FILE:-compose.production.yaml}}"
compose_project="${OPS04_COMPOSE_PROJECT_NAME:-${COMPOSE_PROJECT_NAME:-}}"
compose_env_args=()
if [[ -n "${OPS04_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS04_ENV_FILE")
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

alert() {
    "$script_dir/ops04-alert.sh" "$@"
}

probe() {
    local path="$1"
    local output_file="$tmp_dir/$(printf '%s' "$path" | tr -cd '[:alnum:]')"
    local status

    status="$(curl --silent --show-error --max-time "${OPS04_HTTP_TIMEOUT_SECONDS:-10}" \
        --output "$output_file" --write-out '%{http_code}' "${base_url%/}${path}" || true)"
    printf 'probe=%s status=%s\n' "$path" "$status"
    [[ "$status" == 200 ]] || return 1

    if [[ "$path" == /readiness ]]; then
        readiness_payload="$(tr -d '[:space:]' < "$output_file")"
        [[ "$readiness_payload" == *'"status":"ready"'* ]] || return 1
        for check in app db storage migration_manifest outbox; do
            [[ "$readiness_payload" == *"\"$check\":{\"status\":\"pass\""* ]] || return 1
        done
        [[ "$readiness_payload" == *'"queue":{"status":"pass"'* || "$readiness_payload" == *'"queue":{"status":"not_required"'* ]] || return 1
    fi
}

probe /up || { alert app_unavailable critical 'restart app and inspect logs'; exit 1; }
probe /readiness || { alert readiness_failed critical 'inspect failed dependency check and contain traffic'; exit 1; }

if [[ "${OPS04_SCHEDULER_REQUIRED:-true}" == true || "${OPS04_QUEUE_REQUIRED:-false}" == true ]]; then
    [[ -n "$compose_project" ]] || {
        alert runtime_process_check_unconfigured critical 'provide the Compose project and rerun readiness'
        exit 1
    }
    services="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$compose_project" ps --status running --services)"
fi

if [[ "${OPS04_SCHEDULER_REQUIRED:-true}" == true ]]; then
    printf '%s\n' "$services" | grep -qx scheduler || {
        alert scheduler_missing critical 'restart scheduler and inspect schedule logs'
        exit 1
    }
    schedule_output="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$compose_project" exec -T app php artisan schedule:list --no-interaction)"
    printf '%s\n' "$schedule_output" | grep -q 'financial:drain-order-paid-outbox' || {
        alert scheduler_contract_missing critical 'restore the scheduled outbox drainer'
        exit 1
    }
    printf 'scheduler=PASS\n'
else
    printf 'scheduler=not-required\n'
fi

if [[ "${OPS04_QUEUE_REQUIRED:-false}" == true ]]; then
    printf '%s\n' "$services" | grep -qx worker || {
        alert worker_missing critical 'start the required queue worker'
        exit 1
    }
    printf 'queue=PASS\n'
else
    printf 'queue=not-required\n'
fi

if [[ -n "$compose_project" ]]; then
    storage_available="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$compose_project" exec -T app sh -c "df -Pk /var/www/html/storage | awk 'NR==2 {printf \"%.0f\", \$4 * 1024}'")"
    minimum="${OPS04_MIN_FREE_BYTES:-${OPS_STORAGE_MIN_FREE_BYTES:-1073741824}}"
    [[ "$storage_available" =~ ^[0-9]+$ && "$storage_available" -ge "$minimum" ]] || {
        alert storage_capacity_low critical 'free space or expand the storage volume'
        exit 1
    }
    printf 'storage_capacity=PASS available_bytes=%s minimum_bytes=%s\n' "$storage_available" "$minimum"
else
    printf 'storage_capacity=not-checked-no-compose-context\n'
fi

printf 'readiness_probe=PASS timestamp=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
