<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @param  array<string, string>  $environment
 */
function runErrorScanScript(string $logFile, array $environment = []): Process
{
    $process = new Process([
        base_path('scripts/ops/ops04-error-scan.sh'),
    ], base_path(), [
        'OPS04_LOG_FILE' => $logFile,
        'OPS04_ALERT_OWNER' => '',
        ...$environment,
    ]);
    $process->run();

    return $process;
}

it('does not report a passing scan when the five xx threshold is exceeded', function (): void {
    $logFile = tempnam(sys_get_temp_dir(), 'ead2026-error-scan-');
    expect($logFile)->not->toBeFalse();

    file_put_contents($logFile, implode(PHP_EOL, array_fill(0, 5, '{"status":500}')));

    try {
        $process = runErrorScanScript($logFile);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('error_scan=FAIL')
            ->and($process->getErrorOutput())->toContain('reason=http_5xx_spike')
            ->and($process->getErrorOutput())->not->toContain('error_scan=PASS');
    } finally {
        unlink($logFile);
    }
});

it('reports a passing scan only when the observed log is below thresholds', function (): void {
    $logFile = tempnam(sys_get_temp_dir(), 'ead2026-error-scan-');
    expect($logFile)->not->toBeFalse();

    file_put_contents($logFile, '{"status":200,"level_name":"INFO"}'.PHP_EOL);

    try {
        $process = runErrorScanScript($logFile);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('error_scan=PASS five_xx=0 critical=0');
    } finally {
        unlink($logFile);
    }
});
