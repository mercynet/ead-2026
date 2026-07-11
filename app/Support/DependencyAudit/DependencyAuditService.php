<?php

namespace App\Support\DependencyAudit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

final class DependencyAuditService
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function audit(array $options = []): AuditReport
    {
        $rootPath = rtrim((string) ($options['path'] ?? base_path()), '/');
        $includeDev = (bool) ($options['include_dev'] ?? false);
        $scanVendor = (bool) ($options['scan_vendor'] ?? false);
        $useBaseline = ! (bool) ($options['no_baseline'] ?? false);
        $baselinePath = (string) ($options['baseline_path'] ?? base_path(config('dependency_audit.baseline_path')));

        $composerJsonPath = $rootPath.'/composer.json';
        $composerLockPath = $rootPath.'/composer.lock';

        $composerJson = $this->readJsonFile($composerJsonPath, 'composer.json');
        $composerLock = $this->readJsonFile($composerLockPath, 'composer.lock');

        $config = config('dependency_audit');
        $findings = [];

        $findings = [...$findings, ...$this->auditRootManifest($composerJson, $composerJsonPath, $includeDev, $config)];
        $findings = [...$findings, ...$this->auditLockfile($composerLock, $composerLockPath, $includeDev, $config)];

        if ($scanVendor) {
            $findings = [...$findings, ...$this->auditVendor($rootPath, $composerLock, $includeDev, $config)];
        }

        [$activeFindings, $suppressed] = $useBaseline
            ? $this->applyBaseline($findings, $baselinePath)
            : [$findings, []];

        usort($activeFindings, $this->findingSorter(...));
        usort($suppressed, $this->findingSorter(...));

        return new AuditReport($activeFindings, $suppressed, $rootPath, $scanVendor);
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<array<string, string>>
     */
    public function makeBaselineEntries(array $findings): array
    {
        $owner = getenv('USER') ?: 'unknown';

        return array_map(
            fn (Finding $finding): array => $finding->toBaselineEntry(owner: $owner),
            $findings,
        );
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @param  array<string, mixed>  $config
     * @return list<Finding>
     */
    private function auditRootManifest(array $composerJson, string $composerJsonPath, bool $includeDev, array $config): array
    {
        $findings = [];
        $packageSets = ['require' => Arr::get($composerJson, 'require', [])];

        if ($includeDev) {
            $packageSets['require-dev'] = Arr::get($composerJson, 'require-dev', []);
        }

        foreach ($packageSets as $section => $packages) {
            foreach ($packages as $package => $constraint) {
                if ($constraint === '*') {
                    $findings[] = new Finding(
                        'wildcard_constraint',
                        Severity::Medium,
                        (string) $package,
                        (string) $constraint,
                        $this->relativePath($composerJsonPath),
                        "{$section}.{$package} uses wildcard constraint",
                        'Pin to an explicit supported range instead of *.',
                    );
                }

                if ($this->isDevConstraint((string) $constraint)) {
                    $findings[] = new Finding(
                        'unstable_constraint',
                        Severity::Medium,
                        (string) $package,
                        (string) $constraint,
                        $this->relativePath($composerJsonPath),
                        "{$section}.{$package} targets dev branch/alias",
                        'Prefer stable tags/ranges unless there is an explicit temporary exception.',
                    );
                }
            }
        }

        if (($composerJson['minimum-stability'] ?? null) === 'dev') {
            $findings[] = new Finding(
                'minimum_stability_dev',
                Severity::Medium,
                '__root__',
                'dev',
                $this->relativePath($composerJsonPath),
                'minimum-stability is set to dev',
                'Keep minimum-stability stable and pin exceptional packages explicitly.',
            );
        }

        if (($composerJson['prefer-stable'] ?? true) === false) {
            $findings[] = new Finding(
                'prefer_stable_disabled',
                Severity::Medium,
                '__root__',
                'false',
                $this->relativePath($composerJsonPath),
                'prefer-stable is disabled',
                'Enable prefer-stable to reduce accidental unstable upgrades.',
            );
        }

        if (($composerJson['config']['secure-http'] ?? true) === false) {
            $findings[] = new Finding(
                'secure_http_disabled',
                Severity::Critical,
                '__root__',
                'false',
                $this->relativePath($composerJsonPath),
                'config.secure-http is false',
                'Keep secure-http enabled.',
            );
        }

        foreach (($composerJson['config']['allow-plugins'] ?? []) as $package => $allowed) {
            if (! $allowed) {
                continue;
            }

            if (str_contains((string) $package, '*')) {
                $findings[] = new Finding(
                    'allow_plugins_wildcard',
                    Severity::Critical,
                    (string) $package,
                    'enabled',
                    $this->relativePath($composerJsonPath),
                    'allow-plugins grants wildcard execution rights',
                    'Replace wildcard plugin trust with explicit package allowlist.',
                );

                continue;
            }

            if (! array_key_exists((string) $package, $config['trusted_composer_plugins'] ?? [])) {
                $findings[] = new Finding(
                    'allow_plugins_untrusted',
                    Severity::High,
                    (string) $package,
                    'enabled',
                    $this->relativePath($composerJsonPath),
                    'allow-plugins trusts a package outside policy allowlist',
                    'Add it to config/dependency_audit.php with justification or remove it.',
                );
            }
        }

        foreach (($composerJson['repositories'] ?? []) as $index => $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $url = (string) ($repository['url'] ?? '');
            $host = $this->extractHost($url);
            $file = $this->relativePath($composerJsonPath);

            if ($url !== '' && str_starts_with($url, 'http://')) {
                $findings[] = new Finding(
                    'repository_insecure_http',
                    Severity::Critical,
                    '__root__',
                    (string) ($repository['type'] ?? 'repository'),
                    $file,
                    "repositories[{$index}] uses insecure http URL {$url}",
                    'Use https repository endpoints only.',
                );

                continue;
            }

            if ($host !== null && ! in_array($host, $config['trusted_repository_hosts'] ?? [], true)) {
                $findings[] = new Finding(
                    'repository_host_untrusted',
                    Severity::High,
                    '__root__',
                    (string) ($repository['type'] ?? 'repository'),
                    $file,
                    "repositories[{$index}] points to untrusted host {$host}",
                    'Restrict custom repositories to trusted hosts documented in policy.',
                );
            }
        }

        foreach (($composerJson['scripts'] ?? []) as $event => $commands) {
            $commands = is_array($commands) ? $commands : [$commands];

            foreach ($commands as $command) {
                $signal = $this->matchSuspiciousScript((string) $command);
                if ($signal === null) {
                    continue;
                }

                $findings[] = new Finding(
                    'suspicious_root_script',
                    Severity::High,
                    '__root__',
                    (string) $event,
                    $this->relativePath($composerJsonPath),
                    "scripts.{$event} contains {$signal}",
                    'Keep root scripts minimal and remove dynamic shell/download execution.',
                );
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $composerLock
     * @param  array<string, mixed>  $config
     * @return list<Finding>
     */
    private function auditLockfile(array $composerLock, string $composerLockPath, bool $includeDev, array $config): array
    {
        $findings = [];

        foreach ($this->lockPackages($composerLock, $includeDev) as $package) {
            $packageName = (string) ($package['name'] ?? 'unknown/package');
            $version = (string) ($package['version'] ?? 'unknown');
            $file = $this->relativePath($composerLockPath);
            $integrity = $this->packageIntegrity($package);

            if (($package['type'] ?? null) === 'composer-plugin') {
                $severity = array_key_exists($packageName, $config['trusted_composer_plugins'] ?? [])
                    ? Severity::Medium
                    : Severity::High;

                $findings[] = new Finding(
                    'composer_plugin_package',
                    $severity,
                    $packageName,
                    $version,
                    $file,
                    'Package type is composer-plugin',
                    'Review plugin necessity and keep allow-plugins policy explicit.',
                    $integrity,
                );
            }

            $autoloadFiles = Arr::get($package, 'autoload.files', []);
            if ($autoloadFiles !== []) {
                $findings[] = new Finding(
                    'autoload_files',
                    Severity::Medium,
                    $packageName,
                    $version,
                    $file,
                    'autoload.files: '.implode(', ', $autoloadFiles),
                    'Review auto-loaded files because they execute during bootstrap.',
                    $integrity,
                );
            }

            $providers = Arr::get($package, 'extra.laravel.providers', []);
            $untrustedProviders = array_values(array_diff($providers, $config['trusted_laravel_providers'][$packageName] ?? []));

            if ($untrustedProviders !== []) {
                $findings[] = new Finding(
                    'laravel_auto_discovery',
                    Severity::Medium,
                    $packageName,
                    $version,
                    $file,
                    'extra.laravel.providers: '.implode(', ', $untrustedProviders),
                    'Review Laravel package discovery and baseline only trusted providers.',
                    $integrity,
                );
            }

            $bins = (array) ($package['bin'] ?? []);
            if ($bins !== []) {
                $trustedBins = $config['trusted_vendor_bins'][$packageName] ?? [];
                $unexpectedBins = array_values(array_diff($bins, $trustedBins));

                if ($unexpectedBins !== []) {
                    $findings[] = new Finding(
                        'unexpected_vendor_bin',
                        Severity::Medium,
                        $packageName,
                        $version,
                        $file,
                        'bin: '.implode(', ', $unexpectedBins),
                        'Review vendor binaries and allowlist only expected executables.',
                        $integrity,
                    );
                }
            }

            if (($package['abandoned'] ?? false) !== false) {
                $findings[] = new Finding(
                    'abandoned_package',
                    Severity::Medium,
                    $packageName,
                    $version,
                    $file,
                    'Package is marked as abandoned',
                    'Replace or justify abandoned packages before they become a maintenance liability.',
                    $integrity,
                );
            }

            foreach (['source', 'dist'] as $originType) {
                $url = (string) Arr::get($package, "{$originType}.url", '');
                if ($url === '') {
                    continue;
                }

                if (str_starts_with($url, 'http://')) {
                    $findings[] = new Finding(
                        'package_origin_insecure_http',
                        Severity::Critical,
                        $packageName,
                        $version,
                        $file,
                        "{$originType}.url uses insecure http: {$url}",
                        'Use https package origins only.',
                        $integrity,
                    );

                    continue;
                }

                $host = $this->extractHost($url);
                if ($host !== null && ! in_array($host, $config['trusted_repository_hosts'] ?? [], true)) {
                    $findings[] = new Finding(
                        'package_origin_untrusted_host',
                        Severity::High,
                        $packageName,
                        $version,
                        $file,
                        "{$originType}.url points to untrusted host {$host}",
                        'Prefer package origins hosted on trusted registries or audited mirrors.',
                        $integrity,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $composerLock
     * @param  array<string, mixed>  $config
     * @return list<Finding>
     */
    private function auditVendor(string $rootPath, array $composerLock, bool $includeDev, array $config): array
    {
        $vendorPath = $rootPath.'/vendor';
        $installedPath = $vendorPath.'/composer/installed.json';

        if (! is_dir($vendorPath) || ! is_file($installedPath)) {
            return [new Finding(
                'vendor_scan_unavailable',
                Severity::High,
                '__vendor__',
                'missing',
                $this->relativePath($installedPath, $rootPath),
                'vendor scan requested but vendor/composer/installed.json is missing',
                'Run composer install before using --scan-vendor.',
            )];
        }

        $installedJson = $this->readJsonFile($installedPath, 'vendor/composer/installed.json');
        $installedPackages = Collection::make($installedJson['packages'] ?? $installedJson)
            ->filter(fn (mixed $package): bool => is_array($package) && isset($package['name']))
            ->mapWithKeys(fn (array $package): array => [(string) $package['name'] => $package])
            ->all();

        $lockPackages = Collection::make($this->lockPackages($composerLock, $includeDev))
            ->keyBy(fn (array $package): string => (string) $package['name'])
            ->all();

        $findings = [];

        foreach (array_diff(array_keys($installedPackages), array_keys($lockPackages)) as $packageName) {
            if ($packageName === '__root__') {
                continue;
            }

            $findings[] = new Finding(
                'vendor_package_not_in_lock',
                Severity::Critical,
                $packageName,
                (string) ($installedPackages[$packageName]['version'] ?? 'unknown'),
                $this->relativePath($installedPath, $rootPath),
                'Package exists in vendor installed metadata but not in composer.lock',
                'Reinstall dependencies from lock and investigate vendor drift.',
            );
        }

        foreach ($lockPackages as $packageName => $package) {
            $version = (string) ($package['version'] ?? 'unknown');
            $vendorComposerPath = $vendorPath.'/'.$packageName.'/composer.json';

            if (isset($installedPackages[$packageName])) {
                $this->compareInstalledMetadata(
                    $findings,
                    $package,
                    $installedPackages[$packageName],
                    $installedPath,
                    $rootPath,
                );
            }

            if (! isset($installedPackages[$packageName]) || ! is_file($vendorComposerPath)) {
                $findings[] = new Finding(
                    'locked_package_missing_from_vendor',
                    Severity::High,
                    $packageName,
                    $version,
                    $this->relativePath($vendorComposerPath, $rootPath),
                    'Package exists in composer.lock but vendor copy is missing',
                    'Run composer install and ensure vendor matches the lockfile.',
                );

                continue;
            }

            $vendorComposer = $this->readJsonFile($vendorComposerPath, $this->relativePath($vendorComposerPath, $rootPath));
            $this->compareVendorMetadata($findings, $package, $vendorComposer, $vendorComposerPath, $rootPath);
            $this->scanPackageEntrypoints($findings, $package, $vendorPath.'/'.$packageName, $rootPath, $config);
        }

        return $findings;
    }

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $lockedPackage
     * @param  array<string, mixed>  $installedPackage
     */
    private function compareInstalledMetadata(
        array &$findings,
        array $lockedPackage,
        array $installedPackage,
        string $installedPath,
        string $rootPath,
    ): void {
        $packageName = (string) ($lockedPackage['name'] ?? 'unknown/package');
        $version = (string) ($lockedPackage['version'] ?? 'unknown');

        $checks = [
            'version' => [
                $lockedPackage['version'] ?? null,
                $installedPackage['version'] ?? null,
            ],
            'source.type' => [
                Arr::get($lockedPackage, 'source.type'),
                Arr::get($installedPackage, 'source.type'),
            ],
            'source.url' => [
                Arr::get($lockedPackage, 'source.url'),
                Arr::get($installedPackage, 'source.url'),
            ],
            'source.reference' => [
                Arr::get($lockedPackage, 'source.reference'),
                Arr::get($installedPackage, 'source.reference'),
            ],
            'dist.type' => [
                Arr::get($lockedPackage, 'dist.type'),
                Arr::get($installedPackage, 'dist.type'),
            ],
            'dist.url' => [
                Arr::get($lockedPackage, 'dist.url'),
                Arr::get($installedPackage, 'dist.url'),
            ],
            'dist.reference' => [
                Arr::get($lockedPackage, 'dist.reference'),
                Arr::get($installedPackage, 'dist.reference'),
            ],
        ];

        if (($lockedPackage['dist']['shasum'] ?? '') !== '' || ($installedPackage['dist']['shasum'] ?? '') !== '') {
            $checks['dist.shasum'] = [
                Arr::get($lockedPackage, 'dist.shasum'),
                Arr::get($installedPackage, 'dist.shasum'),
            ];
        }

        foreach ($checks as $field => [$expected, $actual]) {
            if ($actual === null || $actual === '' || $expected === $actual) {
                continue;
            }

            $findings[] = new Finding(
                'vendor_installed_metadata_drift',
                Severity::High,
                $packageName,
                $version,
                $this->relativePath($installedPath, $rootPath),
                "{$field} differs between lock and installed vendor metadata",
                'Reinstall dependencies and investigate whether installed metadata diverged from the lockfile or the upstream artifact was rewritten.',
            );
        }
    }

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $lockedPackage
     * @param  array<string, mixed>  $vendorComposer
     */
    private function compareVendorMetadata(array &$findings, array $lockedPackage, array $vendorComposer, string $vendorComposerPath, string $rootPath): void
    {
        $packageName = (string) ($lockedPackage['name'] ?? 'unknown/package');
        $version = (string) ($lockedPackage['version'] ?? 'unknown');

        $checks = [
            'autoload.files' => [
                Arr::get($lockedPackage, 'autoload.files', []),
                Arr::get($vendorComposer, 'autoload.files', []),
            ],
            'extra.laravel.providers' => [
                Arr::get($lockedPackage, 'extra.laravel.providers', []),
                Arr::get($vendorComposer, 'extra.laravel.providers', []),
            ],
            'bin' => [
                array_values((array) ($lockedPackage['bin'] ?? [])),
                array_values((array) ($vendorComposer['bin'] ?? [])),
            ],
        ];

        foreach ($checks as $field => [$expected, $actual]) {
            if ($expected === $actual) {
                continue;
            }

            $findings[] = new Finding(
                'vendor_metadata_drift',
                Severity::High,
                $packageName,
                $version,
                $this->relativePath($vendorComposerPath, $rootPath),
                "{$field} differs between lock and vendor copy",
                'Reinstall dependencies and investigate whether vendor was modified locally or upstream package changed unexpectedly.',
            );
        }
    }

    /**
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $package
     * @param  array{trusted_vendor_entrypoints?: array<string, list<string>>}  $config
     */
    private function scanPackageEntrypoints(array &$findings, array $package, string $packagePath, string $rootPath, array $config): void
    {
        $packageName = (string) ($package['name'] ?? 'unknown/package');
        $entryFiles = [];

        foreach (Arr::get($package, 'autoload.files', []) as $relativeFile) {
            $entryFiles[] = $packagePath.'/'.ltrim((string) $relativeFile, '/');
        }

        foreach ((array) ($package['bin'] ?? []) as $relativeFile) {
            $entryFiles[] = $packagePath.'/'.ltrim((string) $relativeFile, '/');
        }

        foreach (array_unique($entryFiles) as $entryFile) {
            if (! is_file($entryFile)) {
                continue;
            }

            $relativeEntrypoint = ltrim(str_replace($packagePath.'/', '', str_replace('\\', '/', $entryFile)), '/');

            if (in_array($relativeEntrypoint, $config['trusted_vendor_entrypoints'][$packageName] ?? [], true)) {
                continue;
            }

            $contents = (string) file_get_contents($entryFile);

            if ($this->looksBinary($contents)) {
                continue;
            }

            $signal = $this->matchSuspiciousCode($contents);
            if ($signal === null) {
                continue;
            }

            $findings[] = new Finding(
                'suspicious_vendor_entrypoint',
                Severity::High,
                $packageName,
                (string) ($package['version'] ?? 'unknown'),
                $this->relativePath($entryFile, $rootPath),
                "Entrypoint contains {$signal}",
                'Review this auto-executed vendor file before trusting the package.',
            );
        }
    }

    /**
     * @param  list<Finding>  $findings
     * @return array{0: list<Finding>, 1: list<Finding>}
     */
    private function applyBaseline(array $findings, string $baselinePath): array
    {
        if (! is_file($baselinePath)) {
            return [$findings, []];
        }

        $rawBaseline = json_decode((string) file_get_contents($baselinePath), true);
        if (! is_array($rawBaseline)) {
            throw new RuntimeException("Invalid baseline JSON at [{$baselinePath}].");
        }

        $active = [];
        $suppressed = [];
        $today = CarbonImmutable::today();

        foreach ($findings as $finding) {
            $matched = Collection::make($rawBaseline)
                ->contains(function (mixed $entry) use ($finding, $today): bool {
                    if (! is_array($entry) || ($entry['fingerprint'] ?? null) !== $finding->fingerprint) {
                        return false;
                    }

                    $expiresAt = $entry['expires_at'] ?? null;

                    return $expiresAt === null || CarbonImmutable::parse((string) $expiresAt)->gte($today);
                });

            if ($matched) {
                $suppressed[] = $finding;

                continue;
            }

            $active[] = $finding;
        }

        return [$active, $suppressed];
    }

    /**
     * Pinned artifact reference of a locked package. Folded into the finding fingerprint so a
     * same-version malicious re-tag (force-pushed tag → new dist.reference, unchanged version
     * string) re-surfaces past a baseline instead of being silently suppressed. A normal version
     * bump already changes the version, so this adds no review churn — it only closes the re-tag hole.
     *
     * @param  array<string, mixed>  $package
     */
    private function packageIntegrity(array $package): string
    {
        return (string) (
            Arr::get($package, 'dist.reference')
            ?: Arr::get($package, 'dist.shasum')
            ?: Arr::get($package, 'source.reference')
            ?: ''
        );
    }

    private function findingSorter(Finding $left, Finding $right): int
    {
        return $right->severity->value <=> $left->severity->value
            ?: $left->rule <=> $right->rule
            ?: $left->package <=> $right->package;
    }

    /**
     * @param  array<string, mixed>  $composerLock
     * @return list<array<string, mixed>>
     */
    private function lockPackages(array $composerLock, bool $includeDev): array
    {
        return [
            ...array_values((array) ($composerLock['packages'] ?? [])),
            ...($includeDev ? array_values((array) ($composerLock['packages-dev'] ?? [])) : []),
        ];
    }

    private function isDevConstraint(string $constraint): bool
    {
        $normalized = strtolower($constraint);

        return str_contains($normalized, 'dev-')
            || str_contains($normalized, '@dev')
            || preg_match('/\bas\b/i', $constraint) === 1;
    }

    private function matchSuspiciousScript(string $command): ?string
    {
        $patterns = [
            '/\b(curl|wget)\b/i' => 'network downloader',
            '/\b(bash|sh|powershell|cscript)\b/i' => 'shell execution',
            '/\beval\b/i' => 'eval usage',
            '/base64/i' => 'base64 decoding/encoding',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $command) === 1) {
                return $label;
            }
        }

        if (preg_match('/php\s+-r/i', $command) === 1
            && preg_match('/\b(eval|base64_decode|exec|shell_exec|system|proc_open|popen|curl|wget|fsockopen)\b/i', $command) === 1) {
            return 'inline php execution';
        }

        return null;
    }

    private function matchSuspiciousCode(string $contents): ?string
    {
        $hasStringAssert = preg_match('/\bassert\s*\(\s*[\'"]/i', $contents) === 1;
        $hasObfuscationCombo = preg_match('/\b(base64_decode|gzinflate)\s*\(/i', $contents) === 1
            && preg_match('/\b(eval|assert)\s*\(/i', $contents) === 1;

        $patterns = [
            '/\b(shell_exec|exec|system|passthru|proc_open|popen)\s*\(/i' => 'process execution',
            '/\b(curl_exec|curl_init|fsockopen|stream_socket_client)\s*\(/i' => 'network primitives',
            '/(\.env|auth\.json|\/proc\/|id_rsa|github_token|aws_|kube|vault)/i' => 'secret access pattern',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $contents) === 1) {
                return $label;
            }
        }

        if ($hasStringAssert || $hasObfuscationCombo) {
            return 'obfuscation/dynamic execution primitive';
        }

        return null;
    }

    private function looksBinary(string $contents): bool
    {
        if ($contents === '') {
            return false;
        }

        return preg_match('/[\x00-\x08\x0E-\x1F]/', $contents) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, string $label): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Required file [{$label}] not found at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in [{$label}] at [{$path}].");
        }

        return $decoded;
    }

    private function extractHost(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    private function relativePath(string $path, ?string $rootPath = null): string
    {
        $rootPath ??= base_path();
        $normalizedRoot = rtrim(str_replace('\\', '/', $rootPath), '/').'/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $normalizedRoot)
            ? substr($normalizedPath, strlen($normalizedRoot))
            : $normalizedPath;
    }
}
