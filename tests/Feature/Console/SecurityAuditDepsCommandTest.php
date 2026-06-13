<?php

use Illuminate\Support\Facades\Artisan;

it('passes on a clean lock-only fixture', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/clean');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--lock-only' => true,
        '--format' => 'json',
        '--no-baseline' => true,
    ]);

    $output = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($output['highest_severity'])->toBe('info')
        ->and($output['findings'])->toBeArray()->toHaveCount(0);
});

it('passes on a clean vendor scan fixture', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/clean');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--scan-vendor' => true,
        '--format' => 'json',
        '--no-baseline' => true,
    ]);

    $output = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($output['scanned_vendor'])->toBeTrue()
        ->and($output['highest_severity'])->toBe('info')
        ->and($output['findings'])->toBeArray()->toHaveCount(0);
});

it('renders a readable pretty summary for humans', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/clean');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--lock-only' => true,
        '--no-baseline' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Dependency Audit')
        ->and($output)->toContain('PASS')
        ->and($output)->toContain('Root:')
        ->and($output)->toContain('No findings above the current baseline / threshold.');
});

it('reports risky manifest and lock signals in json output', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/suspicious');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--lock-only' => true,
        '--include-dev' => true,
        '--format' => 'json',
        '--no-baseline' => true,
    ]);

    $output = json_decode(Artisan::output(), true);
    $rules = collect($output['findings'])->pluck('rule')->all();

    expect($exitCode)->toBe(1)
        ->and($output['highest_severity'])->toBe('critical')
        ->and($rules)->toContain(
            'wildcard_constraint',
            'unstable_constraint',
            'minimum_stability_dev',
            'secure_http_disabled',
            'allow_plugins_wildcard',
            'repository_insecure_http',
            'suspicious_root_script',
            'autoload_files',
            'laravel_auto_discovery',
            'composer_plugin_package',
        );
});

it('detects vendor drift and suspicious entrypoints when scan-vendor is enabled', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/suspicious');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--scan-vendor' => true,
        '--include-dev' => true,
        '--format' => 'json',
        '--no-baseline' => true,
    ]);

    $output = json_decode(Artisan::output(), true);
    $rules = collect($output['findings'])->pluck('rule')->all();

    expect($exitCode)->toBe(1)
        ->and($output['scanned_vendor'])->toBeTrue()
        ->and($rules)->toContain(
            'vendor_package_not_in_lock',
            'locked_package_missing_from_vendor',
            'vendor_installed_metadata_drift',
            'vendor_metadata_drift',
            'suspicious_vendor_entrypoint',
        );
});

it('renders grouped pretty output for findings', function () {
    $fixture = base_path('tests/Fixtures/DependencyAudit/suspicious');

    $exitCode = Artisan::call('security:audit-deps', [
        '--path' => $fixture,
        '--scan-vendor' => true,
        '--include-dev' => true,
        '--no-baseline' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Dependency Audit')
        ->and($output)->toContain('FAIL')
        ->and($output)->toContain('CRITICAL')
        ->and($output)->toContain('HIGH')
        ->and($output)->toContain('Rule')
        ->and($output)->toContain('Package')
        ->and($output)->toContain('Fingerprint:');
});

it('ignores safe assert usage and binary vendor entrypoints during suspicious scan', function () {
    $fixture = sys_get_temp_dir().'/dependency-audit-safe-entrypoints-'.uniqid();

    @mkdir($fixture.'/vendor/composer', 0777, true);
    @mkdir($fixture.'/vendor/acme/tool/bin', 0777, true);

    file_put_contents($fixture.'/composer.json', json_encode([
        'name' => 'acme/test-app',
        'require' => [
            'acme/tool' => '^1.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($fixture.'/composer.lock', json_encode([
        'packages' => [[
            'name' => 'acme/tool',
            'version' => '1.0.0',
            'type' => 'library',
            'bin' => ['bin/safe.php', 'bin/tool.phar'],
            'source' => [
                'type' => 'git',
                'url' => 'https://github.com/acme/tool.git',
                'reference' => 'abc123',
            ],
            'dist' => [
                'type' => 'zip',
                'url' => 'https://api.github.com/repos/acme/tool/zipball/abc123',
                'reference' => 'abc123',
            ],
        ]],
        'packages-dev' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($fixture.'/vendor/composer/installed.json', json_encode([
        'packages' => [[
            'name' => 'acme/tool',
            'version' => '1.0.0',
            'source' => [
                'type' => 'git',
                'url' => 'https://github.com/acme/tool.git',
                'reference' => 'abc123',
            ],
            'dist' => [
                'type' => 'zip',
                'url' => 'https://api.github.com/repos/acme/tool/zipball/abc123',
                'reference' => 'abc123',
            ],
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($fixture.'/vendor/acme/tool/composer.json', json_encode([
        'name' => 'acme/tool',
        'version' => '1.0.0',
        'bin' => ['bin/safe.php', 'bin/tool.phar'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($fixture.'/vendor/acme/tool/bin/safe.php', <<<'PHP'
<?php

$tool = new stdClass();
assert($tool instanceof stdClass);
PHP);

    file_put_contents($fixture.'/vendor/acme/tool/bin/tool.phar', "\x00\x01\x02PHARBINARY");

    try {
        $exitCode = Artisan::call('security:audit-deps', [
            '--path' => $fixture,
            '--scan-vendor' => true,
            '--format' => 'json',
            '--no-baseline' => true,
        ]);

        $output = json_decode(Artisan::output(), true);
        $rules = collect($output['findings'])->pluck('rule')->all();

        expect($exitCode)->toBe(0)
            ->and($rules)->not->toContain('suspicious_vendor_entrypoint');
    } finally {
        removeFixtureDirectory($fixture);
    }
});

it('defines a dedicated dependency qa script and wires it in CI', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $qaDeps = $composer['scripts']['qa:deps'] ?? [];
    $workflow = file_get_contents(base_path('.github/workflows/qa-gate.yml'));

    expect($qaDeps)->toContain(
        '@php artisan security:audit-deps --scan-vendor --include-dev --format=json --fail-on=high',
        '@composer audit --locked',
    )
        ->and($workflow)->toContain('run: composer qa:deps')
        ->and($workflow)->toContain('continue-on-error: true');
});

it('can generate and consume a baseline file', function () {
    $source = base_path('tests/Fixtures/DependencyAudit/suspicious');
    $fixture = sys_get_temp_dir().'/dependency-audit-'.uniqid();
    copyFixtureDirectory($source, $fixture);

    $baseline = $fixture.'/security/generated-baseline.json';

    try {
        $generateExit = Artisan::call('security:audit-deps', [
            '--path' => $fixture,
            '--lock-only' => true,
            '--include-dev' => true,
            '--baseline' => $baseline,
            '--generate-baseline' => true,
        ]);

        $verifyExit = Artisan::call('security:audit-deps', [
            '--path' => $fixture,
            '--lock-only' => true,
            '--include-dev' => true,
            '--baseline' => $baseline,
            '--format' => 'json',
        ]);

        $output = json_decode(Artisan::output(), true);

        expect($generateExit)->toBe(0)
            ->and($verifyExit)->toBe(0)
            ->and($output['findings'])->toHaveCount(0)
            ->and($output['suppressed'])->toBeGreaterThan(0);
    } finally {
        removeFixtureDirectory($fixture);
    }
});

function copyFixtureDirectory(string $source, string $target): void
{
    @mkdir($target, 0777, true);

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    ) as $item) {
        $destination = $target.'/'.substr($item->getPathname(), strlen($source) + 1);

        if ($item->isDir()) {
            @mkdir($destination, 0777, true);

            continue;
        }

        @mkdir(dirname($destination), 0777, true);
        copy($item->getPathname(), $destination);
    }
}

function removeFixtureDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}
