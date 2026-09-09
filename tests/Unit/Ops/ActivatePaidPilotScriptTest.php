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

it('binds activation receipt fields to observed provenance and gate output', function (): void {
    $script = file_get_contents(base_path('scripts/ops/activate-paid-pilot.sh'));

    expect($script)->not->toBeFalse()
        ->and($script)->toContain('release_sha="$(git -C "$repo_root" rev-parse "${APP_BUILD_SHA}^{commit}"')
        ->and($script)->toContain('docker image inspect "$app_image"')
        ->and($script)->toContain('MIGRATION_MANIFEST_SHA')
        ->and($script)->toContain('env -u COMPOSE_PROJECT_NAME -u COMPOSE_FILE -u COMPOSE_PROFILES')
        ->and($script)->toContain('chown -R %s /var/www/html/.scribe /var/www/html/public/docs')
        ->and($script)->toContain('independent synthetic stack configuration is required')
        ->and($script)->toContain('run_gate "$temp_dir/remote-backup.txt"')
        ->and($script)->toContain('run_gate "$temp_dir/alert-delivery.txt"')
        ->and($script)->toContain('"$scheduler_status" == PASS')
        ->and($script)->toContain('final_verdict=PASS')
        ->and($script)->toContain('"final_verdict": "${final_verdict}"')
        ->and($script)->not->toContain('"final_verdict": "${readiness_status}"')
        ->and($script)->not->toContain('"remote_backup": "PASS"')
        ->and($script)->not->toContain('"readiness": "PASS"');
});
