#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
backup_root="${OPS04_BACKUP_ROOT:?OPS04_BACKUP_ROOT is required}"
max_age="${OPS04_BACKUP_MAX_AGE_SECONDS:-93600}"
now="$(date -u +%s)"
expected_rc_sha="${OPS04_EXPECTED_RC_SHA:?OPS04_EXPECTED_RC_SHA is required}"
expected_project="${OPS04_EXPECTED_COMPOSE_PROJECT:?OPS04_EXPECTED_COMPOSE_PROJECT is required}"
expected_database="${OPS04_EXPECTED_DB_DATABASE:?OPS04_EXPECTED_DB_DATABASE is required}"
expected_storage_volume="${OPS04_EXPECTED_STORAGE_VOLUME:?OPS04_EXPECTED_STORAGE_VOLUME is required}"
manifest_key="${OPS04_BACKUP_MANIFEST_KEY:?OPS04_BACKUP_MANIFEST_KEY is required}"
latest_manifest=''
latest_epoch=0
latest_status=''

while IFS= read -r -d '' manifest; do
    timestamp="$(sed -n 's/^timestamp_utc=//p' "$manifest" | head -n 1)"
    epoch="$(date -u -d "$timestamp" +%s 2>/dev/null || printf 0)"
    if (( epoch > latest_epoch )); then
        latest_epoch="$epoch"
        latest_manifest="$manifest"
        latest_status="$(sed -n 's/^status=//p' "$manifest" | head -n 1)"
    fi
done < <(find "$backup_root" -type f -name manifest.txt -print0 2>/dev/null)

fail_backup_alert() {
    "$script_dir/ops04-alert.sh" backup_missing_or_failed critical 'run backup and inspect the latest receipt'
    exit 1
}

[[ -n "$latest_manifest" && "$latest_status" == PASS ]] || fail_backup_alert
age=$((now - latest_epoch))
(( age >= 0 && age <= max_age )) || fail_backup_alert
backup_dir="$(dirname "$latest_manifest")"
backup_id="$(sed -n 's/^backup_id=//p' "$latest_manifest" | head -n 1)"
rc_sha="$(sed -n 's/^rc_sha=//p' "$latest_manifest" | head -n 1)"
migration_count="$(sed -n 's/^migration_count=//p' "$latest_manifest" | head -n 1)"
producer="$(sed -n 's/^producer=//p' "$latest_manifest" | head -n 1)"
manifest_project="$(sed -n 's/^compose_project=//p' "$latest_manifest" | head -n 1)"
manifest_database="$(sed -n 's/^database=//p' "$latest_manifest" | head -n 1)"
manifest_storage_volume="$(sed -n 's/^storage_volume=//p' "$latest_manifest" | head -n 1)"
manifest_error="$(sed -n 's/^error=//p' "$latest_manifest" | head -n 1)"
expected_manifest_signature="$(sed -n 's/^manifest_signature=//p' "$latest_manifest" | head -n 1)"
actual_manifest_signature="$({ sed '/^manifest_signature=/d' "$latest_manifest"; printf 'manifest_key=%s\n' "$manifest_key"; } | sha256sum | awk '{print $1}')"
[[ "$backup_id" == "$(basename "$backup_dir")" && "$backup_id" =~ ^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9-]+$ ]] || fail_backup_alert
[[ "$rc_sha" == "$expected_rc_sha" && "$rc_sha" =~ ^[0-9a-f]{7,40}$ ]] || fail_backup_alert
[[ "$migration_count" =~ ^[1-9][0-9]*$ ]] || fail_backup_alert
[[ "$producer" == ops03-backup-v2 ]] || fail_backup_alert
[[ "$manifest_project" == "$expected_project" ]] || fail_backup_alert
[[ "$manifest_database" == "$expected_database" ]] || fail_backup_alert
[[ "$manifest_storage_volume" == "$expected_storage_volume" ]] || fail_backup_alert
[[ -z "$manifest_error" ]] || fail_backup_alert
[[ "$expected_manifest_signature" =~ ^[a-f0-9]{64}$ && "$actual_manifest_signature" == "$expected_manifest_signature" ]] || fail_backup_alert
[[ -s "$backup_dir/database.sql.gz" && -s "$backup_dir/storage.tar.gz" ]] || fail_backup_alert
db_checksum="$(sed -n 's/^db_checksum=//p' "$latest_manifest" | head -n 1)"
storage_checksum="$(sed -n 's/^storage_checksum=//p' "$latest_manifest" | head -n 1)"
[[ "$db_checksum" =~ ^[a-f0-9]{64}$ && "$storage_checksum" =~ ^[a-f0-9]{64}$ ]] || fail_backup_alert
[[ "$(sha256sum "$backup_dir/database.sql.gz" | awk '{print $1}')" == "$db_checksum" ]] || fail_backup_alert
[[ "$(sha256sum "$backup_dir/storage.tar.gz" | awk '{print $1}')" == "$storage_checksum" ]] || fail_backup_alert

printf 'backup_monitor=PASS age_seconds=%s window_seconds=%s manifest=%s\n' "$age" "$max_age" "$latest_manifest"
