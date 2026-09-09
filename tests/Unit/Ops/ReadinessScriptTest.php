<?php

use Tests\TestCase;

uses(TestCase::class);

it('formats large storage capacities as integer bytes', function (): void {
    $script = file_get_contents(base_path('scripts/ops/ops04-readiness.sh'));

    expect($script)
        ->toContain("awk 'NR==2 {printf \\\"%.0f\\\", \\$4 * 1024}'")
        ->not->toContain("awk 'NR==2 {print \\$4 * 1024}'");
});
