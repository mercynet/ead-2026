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
            'vendor_metadata_drift',
            'suspicious_vendor_entrypoint',
        );
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
