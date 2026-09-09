<?php

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

/**
 * @param  list<string>  $arguments
 * @param  array<string, string>  $environment
 */
function runPaidPilotActivation(array $arguments, array $environment = []): Process
{
    $process = new Process([
        base_path('scripts/ops/activate-paid-pilot.sh'),
        ...$arguments,
    ], base_path(), $environment);
    $process->run();

    return $process;
}

it('dry-runs without mutating or writing a receipt when external env is absent', function (): void {
    $receipt = sys_get_temp_dir().'/ead2026-paid-pilot-receipt-'.bin2hex(random_bytes(4)).'.json';

    try {
        $process = runPaidPilotActivation([
            '--dry-run',
            '--env-file',
            sys_get_temp_dir().'/ead2026-env-does-not-exist',
            '--receipt',
            $receipt,
        ]);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('paid_pilot_activation=DRY_RUN')
            ->and($process->getOutput())->toContain('mutation=none')
            ->and($process->getOutput())->toContain('status=EXTERNAL_PENDING')
            ->and($process->getOutput())->toContain('receipt=not_written')
            ->and(is_file($receipt))->toBeFalse();
    } finally {
        if (is_file($receipt)) {
            unlink($receipt);
        }
    }
});

it('fails closed before any mutation when execute has no env file', function (): void {
    $process = runPaidPilotActivation([
        '--execute',
        '--env-file',
        sys_get_temp_dir().'/ead2026-env-does-not-exist',
    ], [
        'PAID_PILOT_ALLOW_MUTATION' => 'true',
        'PAID_PILOT_ACTIVATION_CONFIRM' => 'I_UNDERSTAND',
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('env file not found')
        ->and($process->getOutput())->not->toContain('docker compose');
});
