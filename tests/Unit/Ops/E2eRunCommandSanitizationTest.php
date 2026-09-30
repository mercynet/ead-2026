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
        'headline' => 'private headline',
        'bio' => 'private biography',
        'avatar' => 'https://private.test/avatar.jpg',
        'linkedin_url' => 'https://linkedin.test/private',
        'twitter_url' => 'https://twitter.test/private',
    ], JSON_THROW_ON_ERROR).' Authorization: Bearer token-secret X-Webhook-Signature:sha256=header-secret X-Api-Key:api-header-secret';
    $sanitized = $sanitize->invoke($command, $raw);

    expect($sanitized)->toContain('[REDACTED]')
        ->not->toContain('gateway-secret')
        ->not->toContain('webhook-secret')
        ->not->toContain('client-secret')
        ->not->toContain('api-secret')
        ->not->toContain('signature-secret')
        ->not->toContain('private headline')
        ->not->toContain('private biography')
        ->not->toContain('private.test/avatar.jpg')
        ->not->toContain('linkedin.test/private')
        ->not->toContain('twitter.test/private')
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

it('redacts PII when a JSON assertion fails', function (): void {
    $command = app(E2eRunCommand::class);
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    $reportCase = new \ReflectionMethod($command, 'reportCase');
    $reportCase->setAccessible(true);

    $reportCase->invoke($command, 'PII assertion', [[
        'json: data.user',
        false,
        ['email' => 'expected@example.test'],
        ['email' => 'person@example.test', 'cpf' => '12345678900'],
    ]]);

    expect($output->fetch())
        ->not->toContain('expected@example.test')
        ->not->toContain('person@example.test')
        ->not->toContain('12345678900');
});

it('redacts scalar PII when a database assertion fails', function (): void {
    $command = app(E2eRunCommand::class);
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    $reportCase = new \ReflectionMethod($command, 'reportCase');
    $reportCase->setAccessible(true);

    $reportCase->invoke($command, 'database PII assertion', [[
        'db: user email',
        false,
        'expected@example.test',
        'person@example.test',
    ]]);

    expect($output->fetch())
        ->not->toContain('expected@example.test')
        ->not->toContain('person@example.test');
});

it('redacts free text and nested JSON messages before they reach runner output', function (): void {
    $command = app(E2eRunCommand::class);
    $sanitize = new \ReflectionMethod($command, 'sanitize');
    $sanitize->setAccessible(true);

    $raw = 'request failed for person@example.test with cpf 123.456.789-00 PSP secret tenant-secret';
    $sanitized = $sanitize->invoke($command, $raw);

    expect($sanitized)
        ->not->toContain('person@example.test')
        ->not->toContain('123.456.789-00')
        ->not->toContain('tenant-secret');

    $nested = json_encode([
        'message' => 'contact person@example.test',
        'nested' => ['email' => 'nested@example.test', 'bio' => 'private bio'],
    ], JSON_THROW_ON_ERROR);

    $sanitizedNested = $sanitize->invoke($command, $nested);

    expect($sanitizedNested)
        ->not->toContain('person@example.test')
        ->not->toContain('nested@example.test')
        ->not->toContain('private bio');
});
