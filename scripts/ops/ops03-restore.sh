#!/usr/bin/env bash

set -euo pipefail

[[ "${OPS03_REHEARSAL:-}" == true ]] || { printf 'OPS-03 restore requires OPS03_REHEARSAL=true\n' >&2; exit 1; }
[[ "${COMPOSE_PROJECT_NAME:-}" == *ops03* ]] || { printf 'OPS-03 restore requires an ops03 Compose project\n' >&2; exit 1; }
[[ "${DB_DATABASE:-}" == *ops03* ]] || { printf 'OPS-03 restore requires an ops03 database name\n' >&2; exit 1; }

backup_dir="${1:?usage: ops03-restore.sh <backup-directory>}"
compose_file="${COMPOSE_FILE:-compose.production.yaml}"
manifest="$backup_dir/manifest.txt"

[[ -f "$manifest" ]] || { printf 'backup manifest not found\n' >&2; exit 1; }
grep -qx 'status=PASS' "$manifest" || { printf 'backup manifest is not valid\n' >&2; exit 1; }
[[ -s "$backup_dir/database.sql.gz" && -s "$backup_dir/storage.tar.gz" ]] || { printf 'backup payload is incomplete\n' >&2; exit 1; }

expected_db_checksum="$(sed -n 's/^db_checksum=//p' "$manifest")"
expected_storage_checksum="$(sed -n 's/^storage_checksum=//p' "$manifest")"
actual_db_checksum="$(sha256sum "$backup_dir/database.sql.gz" | awk '{print $1}')"
actual_storage_checksum="$(sha256sum "$backup_dir/storage.tar.gz" | awk '{print $1}')"

[[ "$actual_db_checksum" == "$expected_db_checksum" ]] || { printf 'database checksum mismatch\n' >&2; exit 1; }
[[ "$actual_storage_checksum" == "$expected_storage_checksum" ]] || { printf 'storage checksum mismatch\n' >&2; exit 1; }

docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" up -d db
docker compose -f "$compose_file" -p "$COMPOSE_PROJECT_NAME" exec -T db sh -c \
    'MYSQL_PWD="$DB_MIGRATION_PASSWORD" gunzip -c | mysql --binary-mode -u"$DB_MIGRATION_USERNAME" "$MYSQL_DATABASE"' \
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
