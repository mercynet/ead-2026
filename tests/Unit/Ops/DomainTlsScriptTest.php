<?php

use Tests\TestCase;

uses(TestCase::class);

it('allows a rehearsal to declare a separate HTTP probe endpoint', function (): void {
    $script = file_get_contents(base_path('scripts/ops/ops04-domain-tls.sh'));

    expect($script)
        ->toContain('OPS04_HTTP_BASE_URL')
        ->toContain('http_base_url');
});
