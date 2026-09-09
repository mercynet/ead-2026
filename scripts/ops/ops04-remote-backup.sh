#!/usr/bin/env bash

set -euo pipefail

source_dir="${1:?backup directory is required}"
destination="${OPS04_REMOTE_BACKUP_DESTINATION:-}"
credential="${OPS04_REMOTE_BACKUP_CREDENTIAL:-}"
adapter="${OPS04_REMOTE_BACKUP_ADAPTER:-}"
verify_adapter="${OPS04_REMOTE_BACKUP_VERIFY_ADAPTER:-}"
retention_days="${OPS04_REMOTE_BACKUP_RETENTION_DAYS:-30}"

[[ -d "$source_dir" ]] || { printf 'remote_backup=FAIL reason=source_missing\n' >&2; exit 1; }
[[ -s "$source_dir/database.sql.gz" && -s "$source_dir/storage.tar.gz" ]] || {
    printf 'remote_backup=FAIL reason=source_payload_missing\n' >&2
    exit 1
}
[[ -n "$destination" && "$destination" != *REPLACE_WITH_* ]] || {
    printf 'REMOTE_BACKUP_EXTERNAL_BLOCKER: destination is not provisioned\n' >&2
    exit 2
}
[[ -n "$credential" && "$credential" != *REPLACE_WITH_* ]] || {
    printf 'REMOTE_BACKUP_EXTERNAL_BLOCKER: credential is not provisioned\n' >&2
    exit 2
}
[[ "$retention_days" =~ ^[1-9][0-9]*$ ]] || { printf 'invalid remote retention days\n' >&2; exit 2; }
[[ -n "$adapter" && -x "$adapter" ]] || {
    printf 'REMOTE_BACKUP_EXTERNAL_BLOCKER: executable adapter is not provisioned\n' >&2
    exit 2
}
[[ -n "$verify_adapter" && -x "$verify_adapter" ]] || {
    printf 'REMOTE_BACKUP_EXTERNAL_BLOCKER: independent verification adapter is not provisioned\n' >&2
    exit 2
}
[[ "$(realpath "$adapter")" != "$(realpath "$verify_adapter")" ]] || {
    printf 'remote verification adapter must be independent from upload adapter\n' >&2
    exit 2
}

db_checksum="$(sha256sum "$source_dir/database.sql.gz" | awk '{print $1}')"
storage_checksum="$(sha256sum "$source_dir/storage.tar.gz" | awk '{print $1}')"
combined_checksum="$(printf '%s  %s\n%s  %s\n' "$db_checksum" database.sql.gz "$storage_checksum" storage.tar.gz | sha256sum | awk '{print $1}')"
receipt="$(mktemp "${source_dir%/}/remote-transfer.XXXXXX")"
verification_receipt="$(mktemp "${source_dir%/}/remote-verify.XXXXXX")"
trap 'rm -f "$receipt" "$verification_receipt"' EXIT

# The adapter owns provider-specific upload/authentication. Credentials remain in
# its environment and never become command arguments or receipt contents.
if ! "$adapter" "$source_dir" "$destination" "$receipt" "$retention_days"; then
    printf 'remote_backup=FAIL reason=adapter_failed\n' >&2
    exit 1
fi

status="$(sed -n 's/^status=//p' "$receipt" | head -n 1)"
remote_checksum="$(sed -n 's/^checksum=//p' "$receipt" | head -n 1)"
[[ "$status" == PASS && "$remote_checksum" == "$combined_checksum" ]] || {
    printf 'remote_backup=FAIL reason=checksum_or_receipt_mismatch\n' >&2
    exit 1
}

# A second provider operation must read back the remote objects. The upload
# receipt alone is not evidence that the destination contains the payload.
if ! "$verify_adapter" "$destination" "$verification_receipt" "$retention_days"; then
    printf 'remote_backup=FAIL reason=independent_verification_failed\n' >&2
    exit 1
fi

verification_status="$(sed -n 's/^status=//p' "$verification_receipt" | head -n 1)"
verified_db_checksum="$(sed -n 's/^db_checksum=//p' "$verification_receipt" | head -n 1)"
verified_storage_checksum="$(sed -n 's/^storage_checksum=//p' "$verification_receipt" | head -n 1)"
verified_retention_days="$(sed -n 's/^retention_days=//p' "$verification_receipt" | head -n 1)"
artifact_version="$(sed -n 's/^artifact_version=//p' "$verification_receipt" | head -n 1)"
verified_combined_checksum="$(printf '%s  %s\n%s  %s\n' "$verified_db_checksum" database.sql.gz "$verified_storage_checksum" storage.tar.gz | sha256sum | awk '{print $1}')"
[[ "$verification_status" == PASS \
    && "$verified_db_checksum" == "$db_checksum" \
    && "$verified_storage_checksum" == "$storage_checksum" \
    && "$verified_combined_checksum" == "$combined_checksum" \
    && "$verified_retention_days" == "$retention_days" \
    && "$artifact_version" =~ ^[A-Za-z0-9._:-]+$ ]] || {
    printf 'remote_backup=FAIL reason=remote_readback_mismatch\n' >&2
    exit 1
}

destination_id="$(printf '%s' "$destination" | sha256sum | awk '{print substr($1, 1, 12)}')"
printf 'remote_backup=PASS destination_id=%s checksum=%s retention_days=%s\n' \
    "$destination_id" "$combined_checksum" "$retention_days"
