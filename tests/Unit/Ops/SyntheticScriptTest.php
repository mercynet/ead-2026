<?php

use Tests\TestCase;

uses(TestCase::class);

it('supports an independent disposable compose stack for synthetic gates', function (): void {
    $script = file_get_contents(base_path('scripts/ops/ops04-synthetic.sh'));

    expect($script)
        ->toContain('OPS04_SYNTHETIC_BASE_URL')
        ->toContain('OPS04_SYNTHETIC_COMPOSE_PROJECT_NAME')
        ->toContain('OPS04_SYNTHETIC_COMPOSE_FILES')
        ->toContain('OPS04_SYNTHETIC_ENV_FILE')
        ->toContain('OPS04_SYNTHETIC_APP_SERVICE')
        ->toContain('compose_args=(docker compose');
});
