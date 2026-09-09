#!/usr/bin/env bash

set -euo pipefail

ops03_project_name='ead2026-ops03'
ops03_database_name='ead2026_ops03'
ops03_db_volume='ead2026-ops03-db'
ops03_storage_volume='ead2026-ops03-storage'
ops03_cache_volume='ead2026-ops03-cache'

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 restore requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == "$ops03_project_name" ]] || {
    printf 'OPS-03 restore requires the canonical disposable Compose project\n' >&2
    exit 1
}
[[ "${DB_DATABASE:-}" == "$ops03_database_name" ]] || {
    printf 'OPS-03 restore requires the canonical disposable database name\n' >&2
    exit 1
}
[[ "${PRODUCTION_DB_VOLUME:-}" == "$ops03_db_volume" ]] || {
    printf 'OPS-03 restore requires the canonical disposable database volume\n' >&2
    exit 1
}
[[ "${PRODUCTION_STORAGE_VOLUME:-}" == "$ops03_storage_volume" ]] || {
    printf 'OPS-03 restore requires the canonical disposable storage volume\n' >&2
    exit 1
}
[[ "${PRODUCTION_CACHE_VOLUME:-}" == "$ops03_cache_volume" ]] || {
    printf 'OPS-03 restore requires the canonical disposable cache volume\n' >&2
    exit 1
}
[[ -n "${OPS04_BACKUP_MANIFEST_KEY:-}" && "${OPS04_BACKUP_MANIFEST_KEY}" != *REPLACE_WITH_* ]] || {
    printf 'OPS-03 restore requires a manifest signing key\n' >&2
    exit 1
}

for volume_identity in \
    "$ops03_db_volume|production_db_data" \
    "$ops03_storage_volume|production_storage" \
    "$ops03_cache_volume|production_cache"; do
    volume_name="${volume_identity%%|*}"
    compose_volume="${volume_identity##*|}"
    inspected="$(docker volume inspect --format '{{.Name}}|{{index .Labels "com.docker.compose.project"}}|{{index .Labels "com.docker.compose.volume"}}' "$volume_name" 2>/dev/null || true)"
    [[ "$inspected" == "$volume_name|$ops03_project_name|$compose_volume" ]] || {
        printf 'disposable volume identity is unsafe: %s\n' "$volume_name" >&2
        exit 1
    }
done

backup_dir="${1:?usage: ops03-restore.sh <backup-directory>}"
compose_file="${COMPOSE_FILE:-compose.production.yaml}"
compose_env_args=()
if [[ -n "${OPS03_ENV_FILE:-}" ]]; then
    compose_env_args+=(--env-file "$OPS03_ENV_FILE")
fi
manifest="$backup_dir/manifest.txt"

[[ -f "$manifest" ]] || { printf 'backup manifest not found\n' >&2; exit 1; }
grep -qx 'status=PASS' "$manifest" || { printf 'backup manifest is not valid\n' >&2; exit 1; }
[[ -s "$backup_dir/database.sql.gz" && -s "$backup_dir/storage.tar.gz" ]] || { printf 'backup payload is incomplete\n' >&2; exit 1; }

expected_manifest_signature="$(sed -n 's/^manifest_signature=//p' "$manifest" | head -n 1)"
actual_manifest_signature="$({ sed '/^manifest_signature=/d' "$manifest"; printf 'manifest_key=%s\n' "$OPS04_BACKUP_MANIFEST_KEY"; } | sha256sum | awk '{print $1}')"
[[ "$expected_manifest_signature" =~ ^[a-f0-9]{64}$ && "$actual_manifest_signature" == "$expected_manifest_signature" ]] || {
    printf 'backup manifest signature mismatch\n' >&2
    exit 1
}
grep -qx "producer=ops03-backup-v2" "$manifest" || { printf 'backup manifest producer is invalid\n' >&2; exit 1; }
grep -qx "compose_project=$ops03_project_name" "$manifest" || { printf 'backup manifest project is invalid\n' >&2; exit 1; }
grep -qx "database=$ops03_database_name" "$manifest" || { printf 'backup manifest database is invalid\n' >&2; exit 1; }
grep -qx "storage_volume=$ops03_storage_volume" "$manifest" || { printf 'backup manifest storage volume is invalid\n' >&2; exit 1; }

expected_db_checksum="$(sed -n 's/^db_checksum=//p' "$manifest")"
expected_storage_checksum="$(sed -n 's/^storage_checksum=//p' "$manifest")"
actual_db_checksum="$(sha256sum "$backup_dir/database.sql.gz" | awk '{print $1}')"
actual_storage_checksum="$(sha256sum "$backup_dir/storage.tar.gz" | awk '{print $1}')"

[[ "$actual_db_checksum" == "$expected_db_checksum" ]] || { printf 'database checksum mismatch\n' >&2; exit 1; }
[[ "$actual_storage_checksum" == "$expected_storage_checksum" ]] || { printf 'storage checksum mismatch\n' >&2; exit 1; }

docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d db
db_container="$(docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" ps -q db)"
db_ready=false
for _ in $(seq 1 60); do
    health="$(docker inspect -f '{{.State.Health.Status}}' "$db_container" 2>/dev/null || true)"
    if [[ "$health" == healthy ]]; then
        db_ready=true
        break
    fi
    if [[ "$health" == unhealthy ]]; then
        break
    fi
    sleep 1
done
[[ "$db_ready" == true ]] || { printf 'database did not become healthy before restore\n' >&2; exit 1; }
docker compose "${compose_env_args[@]}" -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T db sh -c \
    'gunzip -c | MYSQL_PWD="$DB_MIGRATION_PASSWORD" mysql --binary-mode -u"$DB_MIGRATION_USERNAME" "$MYSQL_DATABASE"' \
    < "$backup_dir/database.sql.gz"

docker run --rm \
    --mount "type=volume,src=${PRODUCTION_STORAGE_VOLUME},dst=/destination" \
    --mount "type=bind,src=$(cd "$backup_dir" && pwd),dst=/backup,readonly" \
    alpine:3.20 sh -c 'find /destination -mindepth 1 -delete && tar -xzf /backup/storage.tar.gz -C /destination'

docker run --rm \
    --mount "type=volume,src=${PRODUCTION_STORAGE_VOLUME},dst=/destination" \
    alpine:3.20 sh -c 'chown -R 33:33 /destination && find /destination -type d -exec chmod 0700 {} + && find /destination -type f -exec chmod 0600 {} +'

printf 'restore=PASS\n'
printf 'backup_id=%s\n' "$(sed -n 's/^backup_id=//p' "$manifest")"
