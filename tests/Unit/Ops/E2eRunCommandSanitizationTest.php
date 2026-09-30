<?php

use App\Console\Commands\E2eRunCommand;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(Tests\TestCase::class);

it('redacts gateway secrets and signatures from runner output', function (): void {
    $command = app(E2eRunCommand::class);
    $sanitize = new \ReflectionMethod($command, 'sanitize');
    $sanitize->setAccessible(true);

    $raw = json_encode([
        'secret' => 'gateway-secret',
        'webhook_secret' => 'webhook-secret',
        'client_secret' => 'client-secret',
        'api_key' => 'api-secret',
        'signature' => 'sha256=signature-secret',
    ], JSON_THROW_ON_ERROR).' Authorization: Bearer token-secret X-Webhook-Signature:sha256=header-secret X-Api-Key:api-header-secret';
    $sanitized = $sanitize->invoke($command, $raw);

    expect($sanitized)->toContain('[REDACTED]')
        ->not->toContain('gateway-secret')
        ->not->toContain('webhook-secret')
        ->not->toContain('client-secret')
        ->not->toContain('api-secret')
        ->not->toContain('signature-secret')
        ->not->toContain('token-secret')
        ->not->toContain('header-secret')
        ->not->toContain('api-header-secret');
});

it('redacts sensitive values when a JSON assertion fails', function (): void {
    $command = app(E2eRunCommand::class);
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    $reportCase = new \ReflectionMethod($command, 'reportCase');
    $reportCase->setAccessible(true);

    $reportCase->invoke($command, 'sensitive assertion', [[
        'json: data.token',
        false,
        'expected',
        'token-secret-from-response',
    ]]);

    expect($output->fetch())->not->toContain('token-secret-from-response');
});
