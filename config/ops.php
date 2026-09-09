<?php

return [
    'migration_manifest' => env('OPS_MIGRATION_MANIFEST', 'release/migrations.manifest.json'),
    'queue_required' => (bool) env('OPS_QUEUE_REQUIRED', false),
    'outbox_max_age_seconds' => (int) env('OPS_OUTBOX_MAX_AGE_SECONDS', 300),
    'storage_min_free_bytes' => (int) env('OPS_STORAGE_MIN_FREE_BYTES', 1073741824),
];
