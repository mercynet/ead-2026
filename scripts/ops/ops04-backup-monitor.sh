#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
backup_root="${OPS04_BACKUP_ROOT:?OPS04_BACKUP_ROOT is required}"
max_age="${OPS04_BACKUP_MAX_AGE_SECONDS:-93600}"
now="$(date -u +%s)"
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
[[ -s "$backup_dir/database.sql.gz" && -s "$backup_dir/storage.tar.gz" ]] || fail_backup_alert
db_checksum="$(sed -n 's/^db_checksum=//p' "$latest_manifest" | head -n 1)"
storage_checksum="$(sed -n 's/^storage_checksum=//p' "$latest_manifest" | head -n 1)"
[[ "$db_checksum" =~ ^[a-f0-9]{64}$ && "$storage_checksum" =~ ^[a-f0-9]{64}$ ]] || fail_backup_alert
[[ "$(sha256sum "$backup_dir/database.sql.gz" | awk '{print $1}')" == "$db_checksum" ]] || fail_backup_alert
[[ "$(sha256sum "$backup_dir/storage.tar.gz" | awk '{print $1}')" == "$storage_checksum" ]] || fail_backup_alert

printf 'backup_monitor=PASS age_seconds=%s window_seconds=%s manifest=%s\n' "$age" "$max_age" "$latest_manifest"
