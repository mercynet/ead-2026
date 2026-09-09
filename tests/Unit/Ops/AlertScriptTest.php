<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @param  array<string, string>  $environment
 */
function runAlertScript(array $arguments, array $environment = []): Process
{
    $process = new Process([
        base_path('scripts/ops/ops04-alert.sh'),
        ...$arguments,
    ], base_path(), $environment);
    $process->run();

    return $process;
}

it('keeps the detected problem visible when no notification provider is configured', function (): void {
    $process = runAlertScript([
        'readiness_failed',
        'critical',
        'inspect readiness',
        'db_check',
    ], [
        'OPS04_ALERT_OWNER' => '',
        'OPS04_ALERT_STATE_DIR' => sys_get_temp_dir().'/ead2026-alert-test-'.bin2hex(random_bytes(4)),
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('"problem_detected":true')
        ->and($process->getOutput())->toContain('"delivery_status":"not_configured"')
        ->and($process->getOutput())->not->toContain('Authorization')
        ->and($process->getOutput())->not->toContain('password');
});

it('classifies an unavailable webhook separately from the original problem', function (): void {
    $process = runAlertScript([
        'backup_missing_or_failed',
        'critical',
        'run backup',
    ], [
        'OPS04_ALERT_WEBHOOK_URL' => 'http://127.0.0.1:9/unavailable',
        'OPS04_ALERT_STATE_DIR' => sys_get_temp_dir().'/ead2026-alert-test-'.bin2hex(random_bytes(4)),
        'OPS04_ALERT_TIMEOUT_SECONDS' => '1',
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('"problem_detected":true')
        ->and($process->getOutput())->toContain('"delivery_status":"failed"')
        ->and($process->getErrorOutput())->toContain('alert=WEBHOOK_FAILED');
});

it('deduplicates repeated signals without hiding their non-zero result', function (): void {
    $stateDirectory = sys_get_temp_dir().'/ead2026-alert-test-'.bin2hex(random_bytes(4));
    $arguments = ['synthetic_failed', 'critical', 'inspect synthetic', 'journey'];
    $environment = [
        'OPS04_ALERT_STATE_DIR' => $stateDirectory,
        'OPS04_ALERT_DEDUP_SECONDS' => '300',
    ];

    try {
        $first = runAlertScript($arguments, $environment);
        $second = runAlertScript($arguments, $environment);

        expect($first->getExitCode())->toBe(1)
            ->and($second->getExitCode())->toBe(1)
            ->and($second->getOutput())->toContain('"deduplicated":true')
            ->and($second->getOutput())->toContain('"delivery_status":"deduplicated"');
    } finally {
        array_map('unlink', glob($stateDirectory.'/*') ?: []);
        @rmdir($stateDirectory);
    }
});
