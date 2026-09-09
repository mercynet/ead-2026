#!/usr/bin/env bash

set -euo pipefail

require_rehearsal() {
    [[ "${OPS03_REHEARSAL:-}" == true ]] || {
        printf 'OPS-03 backup requires OPS03_REHEARSAL=true\n' >&2
        exit 1
    }
    [[ "${COMPOSE_PROJECT_NAME:-}" == ead2026-ops03 ]] || {
        printf 'OPS-03 backup requires the canonical disposable Compose project\n' >&2
        exit 1
    }
    [[ "${DB_DATABASE:-}" == ead2026_ops03 ]] || {
        printf 'OPS-03 backup requires the canonical disposable database name\n' >&2
        exit 1
    }
    [[ "${PRODUCTION_STORAGE_VOLUME:-}" == ead2026-ops03-storage ]] || {
        printf 'OPS-03 backup requires the canonical disposable storage volume\n' >&2
        exit 1
    }
    [[ "${APP_BUILD_SHA:-}" =~ ^[0-9a-f]{7,40}$ ]] || {
        printf 'OPS-03 backup requires APP_BUILD_SHA provenance\n' >&2
        exit 1
    }
    [[ -n "${OPS04_BACKUP_MANIFEST_KEY:-}" && "${OPS04_BACKUP_MANIFEST_KEY}" != *REPLACE_WITH_* ]] || {
        printf 'OPS-03 backup requires a manifest signing key\n' >&2
        exit 1
    }
}

require_rehearsal

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

backup_root="${1:-/tmp/ead2026-ops03-backups}"
compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
backup_id="$(date -u +%Y%m%dT%H%M%SZ)-${RANDOM}"
partial_dir="${backup_root}/${backup_id}.partial"
backup_dir="${backup_root}/${backup_id}"
manifest="${partial_dir}/manifest.txt"

mkdir -p "$partial_dir"

write_manifest() {
    local status="$1"
    local error_message="${2:-}"

    {
        printf 'backup_id=%s\n' "$backup_id"
        printf 'status=%s\n' "$status"
        printf 'timestamp_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        printf 'rc_sha=%s\n' "${APP_BUILD_SHA:-unknown}"
        printf 'migration_count=%s\n' "$expected_migrations"
        printf 'producer=ops03-backup-v2\n'
        printf 'compose_project=%s\n' "$COMPOSE_PROJECT_NAME"
        printf 'database=%s\n' "$DB_DATABASE"
        printf 'storage_volume=%s\n' "$PRODUCTION_STORAGE_VOLUME"
        printf 'db_checksum=%s\n' "${db_checksum:-}" 
        printf 'storage_checksum=%s\n' "${storage_checksum:-}"
        printf 'error=%s\n' "$error_message"
    } > "$manifest"
    manifest_signature="$({ cat "$manifest"; printf 'manifest_key=%s\n' "$OPS04_BACKUP_MANIFEST_KEY"; } | sha256sum | awk '{print $1}')"
    printf 'manifest_signature=%s\n' "$manifest_signature" >> "$manifest"
}

fail_backup() {
    local message="$1"

    rm -f "$partial_dir/database.sql.gz" "$partial_dir/storage.tar.gz"
    write_manifest FAIL "$message"
    mv "$partial_dir" "${backup_root}/${backup_id}.failed"
    printf 'backup failed: %s\n' "$message" >&2
    exit 1
}

trap 'fail_backup "unexpected backup failure"' ERR

mkdir -p "$backup_root"

manifest_migration_output="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T app php artisan ops:migrate --manifest-only --no-interaction)"
expected_migrations="$(printf '%s\n' "$manifest_migration_output" | sed -n 's/^expected=//p')"
[[ -n "$expected_migrations" ]] || fail_backup 'release migration manifest was not validated'

if ! docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T db sh -c \
    'MYSQL_PWD="$DB_MIGRATION_PASSWORD" mysqldump --single-transaction --routines --triggers --events --no-tablespaces -u"$DB_MIGRATION_USERNAME" "$MYSQL_DATABASE" | gzip -c' \
    > "$partial_dir/database.sql.gz"; then
    fail_backup 'database dump command failed'
fi

if [[ ! -s "$partial_dir/database.sql.gz" ]]; then
    fail_backup 'database dump is empty'
fi

if ! docker run --rm \
    --mount "type=volume,src=${PRODUCTION_STORAGE_VOLUME},dst=/source,readonly" \
    --mount "type=bind,src=$(cd "$partial_dir" && pwd),dst=/backup" \
    alpine:3.20 sh -c 'tar -czf /backup/storage.tar.gz -C /source .'; then
    fail_backup 'storage archive command failed'
fi

if [[ ! -s "$partial_dir/storage.tar.gz" ]]; then
    fail_backup 'storage archive is empty'
fi

db_checksum="$(sha256sum "$partial_dir/database.sql.gz" | awk '{print $1}')"
storage_checksum="$(sha256sum "$partial_dir/storage.tar.gz" | awk '{print $1}')"
write_manifest PASS
mv "$partial_dir" "$backup_dir"

printf 'backup_id=%s\n' "$backup_id"
printf 'status=PASS\n'
printf 'db_checksum=%s\n' "$db_checksum"
printf 'storage_checksum=%s\n' "$storage_checksum"
printf 'path=%s\n' "$backup_dir"
