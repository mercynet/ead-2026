#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 deploy requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == ead2026-ops03 ]] || { printf 'OPS-03 deploy requires the canonical disposable Compose project\n' >&2; exit 1; }
[[ "${APP_BUILD_SHA:-}" =~ ^[0-9a-f]{7,40}$ ]] || { printf 'APP_BUILD_SHA must be a git SHA\n' >&2; exit 1; }
[[ -n "${OPS03_ENV_FILE:-}" && -f "$OPS03_ENV_FILE" ]] || {
    printf 'OPS03_ENV_FILE is required for preflight\n' >&2
    exit 1
}
[[ -n "${OPS03_BACKUP_DIR:-}" && -f "${OPS03_BACKUP_DIR}/manifest.txt" ]] || {
    printf 'valid predeploy backup is required\n' >&2
    exit 1
}

env_value() {
    local key="$1"

    sed -n "s/^${key}=//p" "$OPS03_ENV_FILE" | head -n 1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

require_env_value() {
    local key="$1"

    [[ -n "$(env_value "$key")" ]] || {
        printf 'OPS03_ENV_FILE is missing required key: %s\n' "$key" >&2
        exit 1
    }
}

for key in APP_ENV APP_KEY APP_BUILD_SHA DB_DATABASE DB_RUNTIME_USERNAME DB_RUNTIME_PASSWORD \
    DB_MIGRATION_USERNAME DB_MIGRATION_PASSWORD PRODUCTION_DB_VOLUME PRODUCTION_STORAGE_VOLUME \
    PRODUCTION_CACHE_VOLUME OPS04_BACKUP_MANIFEST_KEY; do
    require_env_value "$key"
done

[[ "$(env_value APP_ENV)" == rehearsal ]] || { printf 'OPS-03 deploy requires APP_ENV=rehearsal\n' >&2; exit 1; }
[[ "$(env_value APP_BUILD_SHA)" == "$APP_BUILD_SHA" ]] || { printf 'env and deploy SHA differ\n' >&2; exit 1; }
[[ "$(env_value DB_DATABASE)" == ead2026_ops03 ]] || { printf 'OPS-03 deploy requires the canonical disposable database name\n' >&2; exit 1; }
[[ "$(env_value DB_RUNTIME_USERNAME)" != "$(env_value DB_MIGRATION_USERNAME)" ]] || {
    printf 'runtime and migration DB users must differ\n' >&2
    exit 1
}
[[ "$(env_value PRODUCTION_DB_VOLUME)" == ead2026-ops03-db && \
    "$(env_value PRODUCTION_STORAGE_VOLUME)" == ead2026-ops03-storage && \
    "$(env_value PRODUCTION_CACHE_VOLUME)" == ead2026-ops03-cache ]] || {
    printf 'OPS-03 deploy requires canonical disposable volume names\n' >&2
    exit 1
}

for volume_identity in \
    'ead2026-ops03-db|production_db_data' \
    'ead2026-ops03-storage|production_storage' \
    'ead2026-ops03-cache|production_cache'; do
    volume_name="${volume_identity%%|*}"
    compose_volume="${volume_identity##*|}"
    inspected="$(docker volume inspect --format '{{.Name}}|{{index .Labels "com.docker.compose.project"}}|{{index .Labels "com.docker.compose.volume"}}' "$volume_name" 2>/dev/null || true)"
    [[ "$inspected" == "$volume_name|ead2026-ops03|$compose_volume" ]] || {
        printf 'disposable volume identity is unsafe: %s\n' "$volume_name" >&2
        exit 1
    }
done

manifest="$OPS03_BACKUP_DIR/manifest.txt"
grep -qx 'status=PASS' "$manifest" || { printf 'predeploy backup is not valid\n' >&2; exit 1; }
expected_manifest_signature="$(sed -n 's/^manifest_signature=//p' "$manifest" | head -n 1)"
actual_manifest_signature="$({ sed '/^manifest_signature=/d' "$manifest"; printf 'manifest_key=%s\n' "$(env_value OPS04_BACKUP_MANIFEST_KEY)"; } | sha256sum | awk '{print $1}')"
[[ "$expected_manifest_signature" =~ ^[a-f0-9]{64}$ && "$actual_manifest_signature" == "$expected_manifest_signature" ]] || {
    printf 'predeploy backup manifest signature mismatch\n' >&2
    exit 1
}
grep -qx 'producer=ops03-backup-v2' "$manifest" || { printf 'predeploy backup producer is invalid\n' >&2; exit 1; }
grep -qx 'compose_project=ead2026-ops03' "$manifest" || { printf 'predeploy backup project is invalid\n' >&2; exit 1; }
grep -qx 'database=ead2026_ops03' "$manifest" || { printf 'predeploy backup database is invalid\n' >&2; exit 1; }
grep -qx 'storage_volume=ead2026-ops03-storage' "$manifest" || { printf 'predeploy backup storage volume is invalid\n' >&2; exit 1; }
[[ -s "$OPS03_BACKUP_DIR/database.sql.gz" && -s "$OPS03_BACKUP_DIR/storage.tar.gz" ]] || {
    printf 'predeploy backup payload is incomplete\n' >&2
    exit 1
}
expected_db_checksum="$(sed -n 's/^db_checksum=//p' "$manifest" | head -n 1)"
expected_storage_checksum="$(sed -n 's/^storage_checksum=//p' "$manifest" | head -n 1)"
[[ "$expected_db_checksum" =~ ^[a-f0-9]{64}$ && "$expected_storage_checksum" =~ ^[a-f0-9]{64}$ ]] || {
    printf 'predeploy backup checksums are missing or invalid\n' >&2
    exit 1
}
[[ "$(sha256sum "$OPS03_BACKUP_DIR/database.sql.gz" | awk '{print $1}')" == "$expected_db_checksum" ]] || {
    printf 'predeploy database checksum mismatch\n' >&2
    exit 1
}
[[ "$(sha256sum "$OPS03_BACKUP_DIR/storage.tar.gz" | awk '{print $1}')" == "$expected_storage_checksum" ]] || {
    printf 'predeploy storage checksum mismatch\n' >&2
    exit 1
}

compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
compose_env_args+=(--env-file "$OPS03_ENV_FILE")

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" config --quiet

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" stop scheduler web >/dev/null 2>&1 || true
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d db app
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan optimize:clear

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" run --rm \
    --env-from-file "$OPS03_ENV_FILE" \
    app sh -c 'DB_USERNAME="$DB_MIGRATION_USERNAME" DB_PASSWORD="$DB_MIGRATION_PASSWORD" php artisan ops:migrate --force --no-interaction'

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d scheduler web

printf 'deploy=PASS\n'
printf 'sha=%s\n' "$APP_BUILD_SHA"
